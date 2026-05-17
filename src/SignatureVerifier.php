<?php

declare(strict_types=1);

namespace Nyarlat0\PowClicker;

use Nyarlat0\PowClicker\NonceStore;

final class AuthException extends \RuntimeException
{
    public function __construct(
        string $message,
        private int $statusCode,
    ) {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }
}

final class SignatureVerifier
{
    public function __construct(
        private NonceStore $nonceStore,
    ) {}

    /**
     * @return array{public_key: string, nonce: string}
     */
    public function verifyRequest(array $input): array
    {
        $publicKey = $this->requireString($input, 'public_key');
        $nonce = $this->requireString($input, 'nonce');
        $message = $this->requireString($input, 'message');
        $signature = $this->requireString($input, 'signature');

        if (!preg_match('/^[0-9a-fA-F]{64}$/', $publicKey)) {
            throw new AuthException('invalid_public_key', 400);
        }

        if (!preg_match('/^[0-9a-fA-F]{128}$/', $signature)) {
            throw new AuthException('invalid_signature_format', 400);
        }

        if (!$this->nonceStore->is_valid_nonce($nonce, $publicKey)) {
            throw new AuthException('invalid_or_expired_nonce', 401);
        }

        $publicKeyBytes = hex2bin($publicKey);
        $signatureBytes = hex2bin($signature);

        if ($publicKeyBytes === false || $signatureBytes === false) {
            throw new AuthException('invalid_hex', 400);
        }

        $signedMessage = $message . $nonce;

        $ok = sodium_crypto_sign_verify_detached(
            $signatureBytes,
            $signedMessage,
            $publicKeyBytes,
        );

        if (!$ok) {
            throw new AuthException('invalid_signature', 401);
        }

        if (!$this->nonceStore->try_consume_nonce($nonce, $publicKey)) {
            throw new AuthException('nonce_already_used', 401);
        }

        return [
            'public_key' => strtolower($publicKey),
            'nonce' => $nonce,
        ];
    }

    private function requireString(array $input, string $key): string
    {
        if (!isset($input[$key]) || !is_string($input[$key]) || $input[$key] === '') {
            throw new AuthException("missing_or_invalid_$key", 400);
        }

        return $input[$key];
    }
}
