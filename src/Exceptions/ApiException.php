<?php

declare(strict_types=1);

namespace XendityEkyc\XendityEkyc\Exceptions;

use Throwable;
use XendityEkyc\XendityEkyc\Enums\ErrorCode;

class ApiException extends XendityEkycException
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        string $message,
        public readonly ?string $errorCode = null,
        public readonly int $status = 0,
        public readonly array $payload = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromErrorCode(string $code, array $payload, int $status): self
    {
        $message = ErrorCode::tryFrom($code)?->message() ?? 'The Xendity gateway returned an error.';

        return new self("Xendity eKYC error [{$code}]: {$message}", $code, $status, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function requestFailed(int $status, array $payload = []): self
    {
        return new self("The Xendity eKYC request failed with HTTP status [{$status}].", null, $status, $payload);
    }

    public static function invalidResponse(int $status): self
    {
        return new self('The Xendity eKYC gateway returned an unreadable response.', null, $status);
    }

    public static function connectionFailed(Throwable $previous): self
    {
        return new self('Unable to connect to the Xendity eKYC gateway.', null, 0, [], $previous);
    }

    public function error(): ?ErrorCode
    {
        return $this->errorCode === null ? null : ErrorCode::tryFrom($this->errorCode);
    }
}
