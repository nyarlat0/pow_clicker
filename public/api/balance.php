<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Nyarlat0\PowClicker\AuthException;
use Nyarlat0\PowClicker\BalanceStore;
use Nyarlat0\PowClicker\Database;
use Nyarlat0\PowClicker\NonceStore;
use Nyarlat0\PowClicker\SignatureVerifier;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

Dotenv::createImmutable(dirname(__DIR__, 2))->load();

header('Content-Type: application/json');

$input = json_decode(file_get_contents("php://input"), true);

$pdo = Database::connect();
$nonce_store = new NonceStore($pdo);
$verifier = new SignatureVerifier($nonce_store);

try {
    $auth = $verifier->verifyRequest($input);
    $publicKey = $auth['public_key'];
} catch (AuthException $e) {
    http_response_code($e->statusCode());
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ]) . "\n";
    exit;
}


$balance_store = new BalanceStore($pdo);

echo json_encode([
    'ok' => true,
    'balance' => $balance_store->getBalance($publicKey),
]) . "\n";
