<?php

declare(strict_types=1);

namespace XendityEkyc\XendityEkyc\Exceptions;

class EncryptionException extends XendityEkycException
{
    public static function invalidCipherKey(): self
    {
        return new self('The Xendity cipher key must be exactly 16 bytes long.');
    }

    public static function failedToEncrypt(): self
    {
        return new self('Unable to encrypt the Xendity request payload.');
    }

    public static function failedToDecrypt(): self
    {
        return new self('Unable to decrypt the Xendity payload. Check the api_key and cipher_key configuration.');
    }
}
