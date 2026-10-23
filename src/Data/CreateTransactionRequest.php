<?php

declare(strict_types=1);

namespace XendityEkyc\XendityEkyc\Data;

use InvalidArgumentException;
use XendityEkyc\XendityEkyc\Enums\CallbackMode;
use XendityEkyc\XendityEkyc\Enums\DocumentType;
use XendityEkyc\XendityEkyc\Enums\Platform;
use XendityEkyc\XendityEkyc\Enums\ResponseMode;

/**
 * Input for the create-transaction endpoint (section 5.3.2).
 *
 * Unset optional values fall back to the package configuration.
 */
final readonly class CreateTransactionRequest
{
    /**
     * @param  array<string, mixed>|null  $productInfo
     */
    public function __construct(
        public string $documentName,
        public string $documentNumber,
        public DocumentType $documentType = DocumentType::MyKad,
        public ?string $refId = null,
        public ?string $responseUrl = null,
        public ?string $backendUrl = null,
        public ?string $remark = null,
        public ?array $productInfo = null,
        public ?Platform $platform = null,
        public ?CallbackMode $callbackMode = null,
        public ?ResponseMode $responseMode = null,
    ) {
        self::assertLength('refId', $refId, 32);
        self::assertLength('documentName', $documentName, 100, required: true);
        self::assertLength('documentNumber', $documentNumber, 14, required: true);
        self::assertLength('remark', $remark, 200);
        self::assertLength('responseUrl', $responseUrl, 200);
        self::assertLength('backendUrl', $backendUrl, 200);
    }

    private static function assertLength(string $field, ?string $value, int $max, bool $required = false): void
    {
        if ($required && ($value === null || trim($value) === '')) {
            throw new InvalidArgumentException("The [{$field}] field is required.");
        }

        if ($value !== null && mb_strlen($value) > $max) {
            throw new InvalidArgumentException("The [{$field}] field may not be longer than {$max} characters.");
        }
    }
}
