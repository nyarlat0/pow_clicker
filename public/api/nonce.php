<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Nyarlat0\PowClicker\NonceStore;
use Nyarlat0\PowClicker\Database;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

Dotenv::createImmutable(dirname(__DIR__, 2))->load();

header('Content-Type: application/json');

$input = json_decode(file_get_contents("php://input"), true);
$publicKey = strtolower($input["public_key"]);

if (!is_string($publicKey) || !preg_match('/^[0-9a-fA-F]{64}$/', $publicKey)) {
    http_response_code(400);

    echo json_encode([
        'ok' => false,
        'error' => 'Invalid public key',
    ]) . "\n";

    exit;
}

$store = new NonceStore(Database::connect());
$nonce = $store->create_nonce($publicKey);

echo json_encode([
    'ok' => true,
    'nonce' => $nonce,
]);
