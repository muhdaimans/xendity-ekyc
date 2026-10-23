<?php

declare(strict_types=1);

namespace XendityEkyc\XendityEkyc\Enums;

enum TransactionResult: int
{
    case Rejected = 0;
    case Approved = 1;
    case NotAvailable = 2;
}
