<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Workbench\App\Models\User;
use XendityEkyc\XendityEkyc\Facades\XendityEkyc;
use XendityEkyc\XendityEkyc\Models\EkycTransaction;

it('uses the configured table and hides the signature', function () {
    $transaction = storedTransaction();

    expect($transaction->getTable())->toBe('xendity_ekyc_transactions')
        ->and($transaction->toArray())->not->toHaveKey('signature');
});

it('scopes pending and approved transactions', function () {
    storedTransaction();
    storedTransaction(['ref_id' => 'XDY2', 'onboarding_id' => 'onb_2', 'status' => 2, 'result' => 1]);

    expect(EkycTransaction::query()->pending()->count())->toBe(1)
        ->and(EkycTransaction::query()->approved()->count())->toBe(1);
});

it('treats a transaction past its expiry as expired', function () {
    $transaction = storedTransaction(['expired_at' => now()->subMinute()]);

    expect($transaction->isExpired())->toBeTrue()
        ->and($transaction->isPending())->toBeFalse();
});

it('tells whether a kycable model passed ekyc', function () {
    $user = User::query()->create(['name' => 'Ali', 'email' => 'ali@example.com', 'password' => 'secret']);

    expect($user->hasPassedEkyc())->toBeFalse();

    storedTransaction(['kycable_type' => $user->getMorphClass(), 'kycable_id' => $user->getKey(), 'status' => 2, 'result' => 1]);

    expect($user->hasPassedEkyc())->toBeTrue();
});

it('resolves the transaction from the response url redirect', function () {
    $transaction = storedTransaction();

    $request = Request::create('/ekyc/done', 'GET', ['refId' => 'XDYORDER-1', 'status' => '2', 'result' => '1']);

    expect(XendityEkyc::transactionFromRedirect($request)?->is($transaction))->toBeTrue()
        ->and(XendityEkyc::transactionFromRedirect(Request::create('/ekyc/done')))->toBeNull();
});
