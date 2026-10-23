<?php

declare(strict_types=1);

namespace XendityEkyc\XendityEkyc\Enums;

enum ErrorCode: string
{
    case MissingParameter = '100';
    case InvalidApiKey = '101';
    case InvalidPackageName = '102';
    case InvalidRefId = '103';
    case InvalidSignature = '104';
    case ServiceUnavailable = '105';
    case EncryptFailed = '400';
    case InvalidCredential = '401';

    public function message(): string
    {
        return match ($this) {
            self::MissingParameter => 'Missing parameter.',
            self::InvalidApiKey => 'Invalid API key.',
            self::InvalidPackageName => 'Invalid package name.',
            self::InvalidRefId => 'Invalid ref ID.',
            self::InvalidSignature => 'Invalid security token (signature).',
            self::ServiceUnavailable => 'Service not available, please try again later.',
            self::EncryptFailed => 'Bad request. Encrypt failed.',
            self::InvalidCredential => 'Invalid credential.',
        };
    }
}
