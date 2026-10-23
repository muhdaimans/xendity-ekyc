<?php

declare(strict_types=1);

namespace XendityEkyc\XendityEkyc\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use XendityEkyc\XendityEkyc\Data\StatusResult;
use XendityEkyc\XendityEkyc\Enums\DocumentType;
use XendityEkyc\XendityEkyc\Enums\Platform;
use XendityEkyc\XendityEkyc\Enums\TransactionResult;
use XendityEkyc\XendityEkyc\Enums\TransactionStatus;
use XendityEkyc\XendityEkyc\Support\Payload;

/**
 * @property int $id
 * @property string|null $kycable_type
 * @property int|string|null $kycable_id
 * @property string $client_ref_id
 * @property string $ref_id
 * @property string $onboarding_id
 * @property string $signature
 * @property DocumentType $document_type
 * @property Platform $platform
 * @property string $onboarding_url
 * @property Carbon|null $expired_at
 * @property TransactionStatus $status
 * @property TransactionResult|null $result
 * @property string|null $reject_message
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|null $kycable
 */
class EkycTransaction extends Model
{
    /**
     * @var list<string>
     */
    protected $guarded = ['id'];

    /**
     * @var list<string>
     */
    protected $hidden = ['signature'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 0,
    ];

    public function getTable(): string
    {
        return config()->string('xendity-ekyc.table', 'xendity_ekyc_transactions');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document_type' => DocumentType::class,
            'platform' => Platform::class,
            'status' => TransactionStatus::class,
            'result' => TransactionResult::class,
            'payload' => 'array',
            'expired_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function kycable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Sync the stored state with a get-status or webhook result.
     *
     * Returns true when the status, result or reject message changed.
     */
    public function applyStatus(StatusResult $status): bool
    {
        $this->fill([
            'status' => $status->status,
            'result' => $status->result,
            'reject_message' => $status->rejectMessage,
            'payload' => Payload::withoutImages($status->raw),
        ]);

        if ($status->isFinal() && $this->completed_at === null) {
            $this->completed_at = Carbon::now();
        }

        $changed = $this->isDirty(['status', 'result', 'reject_message']);

        $this->save();

        return $changed;
    }

    public function isApproved(): bool
    {
        return $this->result === TransactionResult::Approved;
    }

    public function isRejected(): bool
    {
        return $this->result === TransactionResult::Rejected;
    }

    public function isCompleted(): bool
    {
        return $this->status === TransactionStatus::Completed;
    }

    public function isExpired(): bool
    {
        return $this->status === TransactionStatus::Expired
            || ($this->status !== TransactionStatus::Completed && $this->expired_at?->isPast() === true);
    }

    public function isPending(): bool
    {
        return ! $this->status->isFinal() && ! $this->isExpired();
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('result', TransactionResult::Approved->value);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', [TransactionStatus::UrlNotOpened->value, TransactionStatus::Processing->value]);
    }
}
