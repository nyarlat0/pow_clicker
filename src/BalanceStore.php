<?php

declare(strict_types=1);

namespace Nyarlat0\PowClicker;

use PDO;

final class BalanceStore
{
    public function __construct(
        private readonly PDO $pdo,
    ) {}

    public function getBalance(string $publicKey): int
    {
        $publicKeyBytes = hex2bin($publicKey);
        $address = hash('sha256', $publicKeyBytes);

        $stmt = $this->pdo->prepare(
            'SELECT balance FROM balances WHERE address = :address',
        );

        $stmt->execute([
            'address' => $address,
        ]);

        $balance = $stmt->fetchColumn();

        if ($balance === false) {
            return 0;
        }

        return (int) $balance;
    }

    public function addBalance(string $publicKey, int $amount): int
    {
        $publicKeyBytes = hex2bin($publicKey);
        $address = hash('sha256', $publicKeyBytes);

        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive');
        }

        $stmt = $this->pdo->prepare(
            '
            INSERT INTO balances(address, balance)
            VALUES (:address, :amount)
            ON DUPLICATE KEY UPDATE
                balance = balance + VALUES(balance)
            ',
        );

        $stmt->execute([
            'address' => $address,
            'amount' => $amount,
        ]);

        return $this->getBalance($publicKey);
    }
}
