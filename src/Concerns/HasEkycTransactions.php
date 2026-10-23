<?php

declare(strict_types=1);

namespace XendityEkyc\XendityEkyc\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use XendityEkyc\XendityEkyc\Enums\TransactionResult;
use XendityEkyc\XendityEkyc\Models\EkycTransaction;
use XendityEkyc\XendityEkyc\XendityEkyc;

/**
 * Add to any Eloquent model (e.g. User) that goes through eKYC.
 */
trait HasEkycTransactions
{
    /**
     * @return MorphMany<EkycTransaction, $this>
     */
    public function ekycTransactions(): MorphMany
    {
        return $this->morphMany(XendityEkyc::transactionModel(), 'kycable');
    }

    /**
     * @return MorphOne<EkycTransaction, $this>
     */
    public function latestEkycTransaction(): MorphOne
    {
        return $this->morphOne(XendityEkyc::transactionModel(), 'kycable')->latestOfMany();
    }

    public function hasPassedEkyc(): bool
    {
        return $this->ekycTransactions()->where('result', TransactionResult::Approved->value)->exists();
    }
}
