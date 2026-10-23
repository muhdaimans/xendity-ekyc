<?php

declare(strict_types=1);

namespace XendityEkyc\XendityEkyc\Support;

use JsonException;
use XendityEkyc\XendityEkyc\Exceptions\EncryptionException;

/**
 * Body encryption as defined in section 5.1.3 of the gateway specification:
 * AES-256-CBC with key = cipher_key + api_key (first 32 bytes) and iv = cipher_key.
 */
final readonly class Encrypter
{
    private const string CIPHER = 'aes-256-cbc';

    public function __construct(
        private string $apiKey,
        private string $cipherKey,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function encrypt(array $payload): string
    {
        try {
            $json = json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw EncryptionException::failedToEncrypt();
        }

        $encrypted = openssl_encrypt($json, self::CIPHER, $this->key(), OPENSSL_RAW_DATA, $this->iv());

        if ($encrypted === false) {
            throw EncryptionException::failedToEncrypt();
        }

        return base64_encode($encrypted);
    }

    /**
     * @return array<string, mixed>
     */
    public function decrypt(string $data): array
    {
        $raw = base64_decode($data, true);

        $json = $raw === false ? false : openssl_decrypt($raw, self::CIPHER, $this->key(), OPENSSL_RAW_DATA, $this->iv());

        $decoded = $json === false ? null : json_decode($json, true);

        if (! is_array($decoded)) {
            throw EncryptionException::failedToDecrypt();
        }

        return Payload::normalize($decoded);
    }

    private function key(): string
    {
        return str_pad(substr($this->cipherKey.$this->apiKey, 0, 32), 32, "\0");
    }

    private function iv(): string
    {
        if (strlen($this->cipherKey) !== 16) {
            throw EncryptionException::invalidCipherKey();
        }

        return $this->cipherKey;
    }
}
