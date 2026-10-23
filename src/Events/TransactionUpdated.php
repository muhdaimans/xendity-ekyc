<?php

declare(strict_types=1);

namespace XendityEkyc\XendityEkyc\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use XendityEkyc\XendityEkyc\Data\StatusResult;
use XendityEkyc\XendityEkyc\Models\EkycTransaction;

/**
 * Dispatched when a webhook or status refresh changes a transaction's status, result or reject message.
 */
class TransactionUpdated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public EkycTransaction $transaction,
        public StatusResult $status,
    ) {}
}
