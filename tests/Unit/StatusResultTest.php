<?php

declare(strict_types=1);

use XendityEkyc\XendityEkyc\Data\StatusResult;
use XendityEkyc\XendityEkyc\Enums\StatusMode;
use XendityEkyc\XendityEkyc\Enums\TransactionResult;
use XendityEkyc\XendityEkyc\Enums\TransactionStatus;
use XendityEkyc\XendityEkyc\Exceptions\XendityEkycException;

it('hydrates a summary result', function () {
    $status = StatusResult::fromArray(gatewayFixture('status-summary-mykad')['data']);

    expect($status->refId)->toBe('XDYORDER-1')
        ->and($status->status)->toBe(TransactionStatus::Completed)
        ->and($status->result)->toBe(TransactionResult::Approved)
        ->and($status->mode)->toBe(StatusMode::Summary)
        ->and($status->isApproved())->toBeTrue()
        ->and($status->isFinal())->toBeTrue()
        ->and($status->step1?->fullName())->toBe('ALI BIN ABU')
        ->and($status->step1?->nameMatches())->toBeTrue()
        ->and($status->step1?->landmarkIsValid())->toBeTrue()
        ->and($status->step2?->percentage)->toBe(80.99)
        ->and($status->step2?->isIdentical)->toBeTrue();
});

it('hydrates a passport detail result without landmarks', function () {
    $status = StatusResult::fromArray(gatewayFixture('status-detail-passport')['data']);

    expect($status->isRejected())->toBeTrue()
        ->and($status->rejectMessage)->toBe('Step 2 failed')
        ->and($status->step1?->ocr('nationality'))->toBe('SGP')
        ->and($status->step1?->faceImage)->toBe('base64string')
        ->and($status->step1?->landmarkIsValid())->toBeNull()
        ->and($status->step2?->bestFrame)->toBe('base64string')
        ->and($status->step2?->raw['step2_result'])->toBe('FAIL');
});

it('requires a known status', function () {
    StatusResult::fromArray(['ref_id' => 'a', 'onboarding_id' => 'b', 'status' => 9]);
})->throws(XendityEkycException::class, '[status]');
