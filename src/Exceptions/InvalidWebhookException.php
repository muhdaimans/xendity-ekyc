<?php

declare(strict_types=1);

namespace XendityEkyc\XendityEkyc\Exceptions;

class InvalidWebhookException extends XendityEkycException
{
    public static function unknownTransaction(): self
    {
        return new self('The webhook does not match a known eKYC transaction.');
    }

    public static function invalidSignature(): self
    {
        return new self('The webhook signature does not match the transaction signature.');
    }

    public static function invalidCredentials(): self
    {
        return new self('The webhook api_key or package_name does not match the configuration.');
    }
}
