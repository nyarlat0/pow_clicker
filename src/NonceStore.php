<?php

declare(strict_types=1);

namespace Nyarlat0\PowClicker;

use PDO;

final class NonceStore
{
    public function __construct(
        private readonly PDO $pdo,
    ) {}

    public function create_nonce(string $public_key): string
    {
        $nonce = bin2hex(random_bytes(32));

        $stmt = $this->pdo->prepare(
            'INSERT INTO auth_nonces(nonce, public_key, expires_at)
          VALUES (:nonce, :public_key, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 120 SECOND))',
        );

        $stmt->execute([
            'nonce' => $nonce,
            'public_key' => $public_key,
        ]);

        return $nonce;
    }

    public function is_valid_nonce(string $nonce, string $public_key): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM auth_nonces
              WHERE nonce = :nonce
                AND public_key = :public_key
                AND expires_at >= UTC_TIMESTAMP()',
        );

        $stmt->execute([
            'nonce' => $nonce,
            'public_key' => $public_key,
        ]);

        return $stmt->fetchColumn() !== false;
    }

    public function try_consume_nonce(string $nonce, string $public_key): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM auth_nonces
              WHERE nonce = :nonce
                AND public_key = :public_key
                AND expires_at >= UTC_TIMESTAMP()',
        );

        $stmt->execute([
            'nonce' => $nonce,
            'public_key' => $public_key,
        ]);

        return $stmt->rowCount() === 1;
    }
}
