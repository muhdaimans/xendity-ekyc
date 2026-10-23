<?php

declare(strict_types=1);

namespace XendityEkyc\XendityEkyc\Exceptions;

use RuntimeException;

class XendityEkycException extends RuntimeException
{
    public static function missingConfiguration(string $key): self
    {
        return new self("The [xendity-ekyc.{$key}] configuration value is not set.");
    }

    public static function missingResponseUrl(): self
    {
        return new self('A response URL is required. Pass one on the request or set [xendity-ekyc.response_url].');
    }

    public static function invalidPayload(string $field): self
    {
        return new self("The Xendity payload is missing or has an invalid [{$field}] field.");
    }
}
