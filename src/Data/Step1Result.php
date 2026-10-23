<?php

declare(strict_types=1);

namespace XendityEkyc\XendityEkyc\Data;

use XendityEkyc\XendityEkyc\Support\Payload;

/**
 * Step 1 (document OCR, text similarity and landmark checks).
 *
 * OCR and landmark keys differ per document type, so they are exposed as arrays.
 */
final readonly class Step1Result
{
    /**
     * @param  array<string, mixed>  $ocrResult
     * @param  array<string, mixed>  $textSimilarityResult
     * @param  array<string, mixed>  $landmark
     * @param  array<string, mixed>  $landmarkStatus
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public bool $status,
        public array $ocrResult,
        public array $textSimilarityResult,
        public array $landmark,
        public array $landmarkStatus,
        public ?string $frontDocumentImage,
        public ?string $backDocumentImage,
        public ?string $faceImage,
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
            ocrResult: Payload::array($data, 'ocr_result'),
            textSimilarityResult: Payload::array($data, 'text_similarity_result'),
            landmark: Payload::array($data, 'landmark'),
            landmarkStatus: Payload::array($data, 'landmark_status'),
            frontDocumentImage: Payload::string($data, 'front_document_image'),
            backDocumentImage: Payload::string($data, 'back_document_image'),
            faceImage: Payload::string($data, 'face_image'),
            attempt: Payload::int($data, 'attempt') ?? 0,
            updatedAt: Payload::string($data, 'updated_at'),
            raw: $data,
        );
    }

    public function ocr(string $key): ?string
    {
        return Payload::string($this->ocrResult, $key);
    }

    public function fullName(): ?string
    {
        return $this->ocr('full_name');
    }

    public function documentNumber(): ?string
    {
        return $this->ocr('front_document_number');
    }

    public function nameMatches(): ?bool
    {
        return Payload::bool($this->textSimilarityResult, 'name_similarity_status');
    }

    public function documentNumberMatches(): ?bool
    {
        return Payload::bool($this->textSimilarityResult, 'document_number_similarity_status');
    }

    /**
     * Passports have no landmark check, so null means "not applicable".
     */
    public function landmarkIsValid(): ?bool
    {
        return Payload::bool($this->landmarkStatus, 'landmark_is_valid');
    }
}
