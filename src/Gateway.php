<?php

declare(strict_types=1);

namespace XendityEkyc\XendityEkyc;

use Illuminate\Config\Repository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Throwable;
use XendityEkyc\XendityEkyc\Exceptions\ApiException;
use XendityEkyc\XendityEkyc\Exceptions\EncryptionException;
use XendityEkyc\XendityEkyc\Exceptions\XendityEkycException;
use XendityEkyc\XendityEkyc\Support\Encrypter;
use XendityEkyc\XendityEkyc\Support\Payload;
use XendityEkyc\XendityEkyc\Support\Signature;

/**
 * Low-level transport for the Xendity eKYC gateway: signs, encrypts, sends and decodes.
 */
class Gateway
{
    public function __construct(
        private readonly Repository $config,
    ) {}

    /**
     * Send an encrypted request and return the decoded response payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function post(string $endpoint, array $payload): array
    {
        try {
            $response = Http::baseUrl($this->baseUrl())
                ->acceptJson()
                ->asJson()
                ->timeout($this->config->integer('xendity-ekyc.http.timeout', 30))
                ->retry(
                    $this->config->integer('xendity-ekyc.http.retry_times', 1),
                    $this->config->integer('xendity-ekyc.http.retry_sleep', 200),
                    fn (Throwable $exception): bool => $exception instanceof ConnectionException
                        || ($exception instanceof RequestException && $exception->response->serverError()),
                    throw: false,
                )
                ->post(ltrim($endpoint, '/'), [
                    'api_key' => $this->apiKey(),
                    'data' => $this->encrypter()->encrypt($payload),
                ]);
        } catch (ConnectionException $exception) {
            throw ApiException::connectionFailed($exception);
        }

        try {
            $data = $this->decode($response->body());
        } catch (EncryptionException $exception) {
            throw $response->failed() ? ApiException::requestFailed($response->status()) : $exception;
        }

        if ($data === null) {
            throw ApiException::invalidResponse($response->status());
        }

        $errorCode = Payload::string($data, 'error_code');

        if ($errorCode !== null) {
            throw ApiException::fromErrorCode($errorCode, $data, $response->status());
        }

        if (($data['success'] ?? true) === false || $response->failed()) {
            throw ApiException::requestFailed($response->status(), $data);
        }

        return $data;
    }

    /**
     * Decode a gateway body that may be an encrypted envelope, a bare encrypted string or plain JSON.
     *
     * @return array<string, mixed>|null
     */
    public function decode(string $body): ?array
    {
        $json = json_decode($body, true);

        if (is_string($json)) {
            $json = ['data' => $json];
        }

        if (! is_array($json)) {
            $body = trim($body);

            if (strlen($body) % 4 !== 0 || preg_match('/^[A-Za-z0-9+\/]+={0,2}$/', $body) !== 1) {
                return null;
            }

            $json = ['data' => $body];
        }

        $json = Payload::normalize($json);

        if (! isset($json['data']) || ! is_string($json['data'])) {
            return $this->unwrap($json);
        }

        try {
            return $this->unwrap($this->encrypter()->decrypt($json['data']));
        } catch (EncryptionException $exception) {
            if (isset($json['error_code']) || ($json['success'] ?? null) === false) {
                return $json;
            }

            throw $exception;
        }
    }

    public function requestTime(): string
    {
        return Date::now($this->config->string('xendity-ekyc.timezone', 'Asia/Kuala_Lumpur'))->format('Y-m-d H:i:s');
    }

    public function signature(): Signature
    {
        return new Signature($this->apiKey(), $this->packageName(), $this->credential('security_key'));
    }

    public function encrypter(): Encrypter
    {
        return new Encrypter($this->apiKey(), $this->credential('cipher_key'));
    }

    public function apiKey(): string
    {
        return $this->credential('api_key');
    }

    public function packageName(): string
    {
        return $this->credential('package_name');
    }

    private function baseUrl(): string
    {
        return rtrim($this->credential('base_url'), '/').'/';
    }

    /**
     * Unwrap the {"success": …, "data": {…}} envelope used by get-status and the webhook.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function unwrap(array $payload): array
    {
        if (isset($payload['data']) && is_array($payload['data'])) {
            return [...Payload::normalize($payload['data']), 'success' => $payload['success'] ?? true];
        }

        return $payload;
    }

    private function credential(string $key): string
    {
        $value = $this->config->get("xendity-ekyc.{$key}");

        if (! is_string($value) || $value === '') {
            throw XendityEkycException::missingConfiguration($key);
        }

        return $value;
    }
}
