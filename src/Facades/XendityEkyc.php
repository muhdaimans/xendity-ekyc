<?php

declare(strict_types=1);

namespace XendityEkyc\XendityEkyc\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \XendityEkyc\XendityEkyc\Models\EkycTransaction createTransaction(\XendityEkyc\XendityEkyc\Data\CreateTransactionRequest $request, \Illuminate\Database\Eloquent\Model|null $kycable = null)
 * @method static \XendityEkyc\XendityEkyc\Data\StatusResult getStatus(string $refId, string $onboardingId, \XendityEkyc\XendityEkyc\Enums\StatusMode $mode = \XendityEkyc\XendityEkyc\Enums\StatusMode::Summary)
 * @method static \XendityEkyc\XendityEkyc\Data\StatusResult refresh(\XendityEkyc\XendityEkyc\Models\EkycTransaction $transaction, \XendityEkyc\XendityEkyc\Enums\StatusMode $mode = \XendityEkyc\XendityEkyc\Enums\StatusMode::Summary)
 * @method static \XendityEkyc\XendityEkyc\Models\EkycTransaction handleWebhook(array<string, mixed> $payload)
 * @method static \XendityEkyc\XendityEkyc\Models\EkycTransaction|null transactionFromRedirect(\Illuminate\Http\Request $request)
 *
 * @see \XendityEkyc\XendityEkyc\XendityEkyc
 */
class XendityEkyc extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \XendityEkyc\XendityEkyc\XendityEkyc::class;
    }
}
