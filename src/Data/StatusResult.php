<?php

declare(strict_types=1);

namespace XendityEkyc\XendityEkyc\Data;

use XendityEkyc\XendityEkyc\Enums\StatusMode;
use XendityEkyc\XendityEkyc\Enums\TransactionResult;
use XendityEkyc\XendityEkyc\Enums\TransactionStatus;
use XendityEkyc\XendityEkyc\Exceptions\XendityEkycException;
use XendityEkyc\XendityEkyc\Support\Payload;

/**
 * Get-status response and webhook postback payload (section 5.4.3).
 */
final readonly class StatusResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $refId,
        public string $onboardingId,
        public TransactionStatus $status,
        public ?TransactionResult $result,
        public ?StatusMode $mode,
        public ?string $rejectMessage,
        public ?string $signature,
        public ?Step1Result $step1,
        public ?Step2Result $step2,
        public array $raw,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $data = Payload::normalize($data);

        $status = TransactionStatus::tryFrom(Payload::int($data, 'status') ?? -1)
            ?? throw XendityEkycException::invalidPayload('status');

        $step1 = Payload::array($data, 'step1') ?: Payload::array($data, 'step1_result');
        $step2 = Payload::array($data, 'step2') ?: Payload::array($data, 'step2_result');
        $result = Payload::int($data, 'result');
        $mode = Payload::int($data, 'mode');

        return new self(
            refId: Payload::string($data, 'ref_id') ?? throw XendityEkycException::invalidPayload('ref_id'),
            onboardingId: Payload::string($data, 'onboarding_id') ?? throw XendityEkycException::invalidPayload('onboarding_id'),
            status: $status,
            result: $result === null ? null : TransactionResult::tryFrom($result),
            mode: $mode === null ? null : StatusMode::tryFrom($mode),
            rejectMessage: Payload::string($data, 'reject_message'),
            signature: Payload::string($data, 'signature'),
            step1: $step1 === [] ? null : Step1Result::fromArray($step1),
            step2: $step2 === [] ? null : Step2Result::fromArray($step2),
            raw: $data,
        );
    }

    public function isCompleted(): bool
    {
        return $this->status === TransactionStatus::Completed;
    }

    public function isExpired(): bool
    {
        return $this->status === TransactionStatus::Expired;
    }

    public function isFinal(): bool
    {
        return $this->status->isFinal();
    }

    public function isApproved(): bool
    {
        return $this->result === TransactionResult::Approved;
    }

    public function isRejected(): bool
    {
        return $this->result === TransactionResult::Rejected;
    }
}
