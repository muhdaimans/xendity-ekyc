<?php

declare(strict_types=1);

namespace XendityEkyc\XendityEkyc\Enums;

enum TransactionStatus: int
{
    case UrlNotOpened = 0;
    case Processing = 1;
    case Completed = 2;
    case Expired = 3;

    public function isFinal(): bool
    {
        return $this === self::Completed || $this === self::Expired;
    }
}
