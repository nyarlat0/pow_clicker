<?php

declare(strict_types=1);

namespace Nyarlat0\PowClicker;

use PDO;

final class TaskStore
{
    private const TASK_TTL_SECONDS = 120;

    public function __construct(
        private PDO $pdo,
    ) {}

    public function createTask(string $publicKey): string
    {
        $publicKey = strtolower($publicKey);

        $challenge = bin2hex(random_bytes(32));

        $stmt = $this->pdo->prepare(
            'INSERT INTO pow_tasks (
                public_key,
                challenge,
                expires_at
            ) VALUES (
                :public_key,
                :challenge,
                DATE_ADD(UTC_TIMESTAMP(), INTERVAL 120 SECOND)
            )
            ON DUPLICATE KEY UPDATE
                challenge = VALUES(challenge),
                expires_at = VALUES(expires_at)',
        );

        $stmt->execute([
            ':public_key' => $publicKey,
            ':challenge' => $challenge,
        ]);

        return $challenge;
    }

    public function findValidTask(string $publicKey): ?string
    {
        $publicKey = strtolower($publicKey);

        $stmt = $this->pdo->prepare(
            'SELECT public_key, challenge, expires_at
             FROM pow_tasks
             WHERE public_key = :public_key
               AND expires_at >= UTC_TIMESTAMP()
             LIMIT 1',
        );

        $stmt->execute([
            ':public_key' => $publicKey,
        ]);

        $task = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($task === false) {
            return null;
        }

        return $task['challenge'];
    }

    public function deleteTask(string $publicKey)
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM pow_tasks
             WHERE public_key = :public_key',
        );

        $stmt->execute([
            ':public_key' => $publicKey,
        ]);
    }

    public function tryConsumeTask(string $publicKey, string $challenge): bool
    {
        $publicKey = strtolower($publicKey);

        $stmt = $this->pdo->prepare(
            'DELETE FROM pow_tasks
             WHERE public_key = :public_key
               AND challenge = :challenge
               AND expires_at >= UTC_TIMESTAMP()',
        );

        $stmt->execute([
            ':public_key' => $publicKey,
            ':challenge' => $challenge,
        ]);

        return $stmt->rowCount() === 1;
    }
}
