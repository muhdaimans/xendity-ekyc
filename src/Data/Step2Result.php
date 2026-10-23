<?php

declare(strict_types=1);

namespace XendityEkyc\XendityEkyc\Data;

use XendityEkyc\XendityEkyc\Support\Payload;

/**
 * Step 2 (liveness and facial recognition).
 */
final readonly class Step2Result
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public bool $status,
        public bool $isIdentical,
        public ?float $percentage,
        public ?string $bestFrame,
        public int $attempt,
        public ?string $updatedAt,
        public array $raw,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            status: Payload::bool($data, 'status') ?? false,
            isIdentical: Payload::bool($data, 'is_identical') ?? false,
            percentage: Payload::float($data, 'percentage'),
            bestFrame: Payload::string($data, 'best_frame'),
            attempt: Payload::int($data, 'attempt') ?? 0,
            updatedAt: Payload::string($data, 'updated_at'),
            raw: $data,
        );
    }
}
