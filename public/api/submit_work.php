<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Nyarlat0\PowClicker\AuthException;
use Nyarlat0\PowClicker\BalanceStore;
use Nyarlat0\PowClicker\Database;
use Nyarlat0\PowClicker\NonceStore;
use Nyarlat0\PowClicker\SignatureVerifier;
use Nyarlat0\PowClicker\TaskStore;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

Dotenv::createImmutable(dirname(__DIR__, 2))->load();

header('Content-Type: application/json');

$input = json_decode(file_get_contents("php://input"), true);

function rejectInvalidProof(): never
{
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'Invalid proof',
    ]) . "\n";
    exit;
}

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

$task_store = new TaskStore($pdo);
$challenge = $task_store->findValidTask($publicKey);
if (!$challenge) {
    rejectInvalidProof();
}

$work_nonce = $input['message'];

$hash = hash('sha256', $challenge . $work_nonce);
$requiredZeroHexChars = 5;

if (!str_starts_with($hash, str_repeat('0', $requiredZeroHexChars))) {
    rejectInvalidProof();
}

try {
    $pdo->beginTransaction();

    if (!$task_store->tryConsumeTask($publicKey, $challenge)) {
        $pdo->rollBack();
        rejectInvalidProof();
    }

    $balance_store = new BalanceStore($pdo);
    $balance_store->addBalance($publicKey, 1);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    throw $e;
}

echo json_encode([
    'ok' => true,
]);
