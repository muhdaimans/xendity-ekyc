<?php

declare(strict_types=1);

namespace XendityEkyc\XendityEkyc\Support;

/**
 * Typed readers for loosely typed gateway payloads.
 *
 * The gateway samples contain keys with stray whitespace ("mode ") and
 * numbers encoded as strings ("80.99"), so values are normalized on read.
 */
final class Payload
{
    /**
     * Keys holding base64 images, which are never persisted.
     */
    private const array IMAGE_KEYS = ['front_document_image', 'back_document_image', 'face_image', 'best_frame'];

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalize(array $data): array
    {
        $normalized = [];

        foreach ($data as $key => $value) {
            $normalized[trim((string) $key)] = is_array($value) ? self::normalize($value) : $value;
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function withoutImages(array $data): array
    {
        $stripped = [];

        foreach ($data as $key => $value) {
            if (in_array($key, self::IMAGE_KEYS, true)) {
                continue;
            }

            $stripped[$key] = is_array($value) ? self::withoutImages($value) : $value;
        }

        return $stripped;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function string(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        if (is_string($value)) {
            return $value === '' ? null : $value;
        }

        return is_int($value) || is_float($value) ? (string) $value : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function int(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function float(array $data, string $key): ?float
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function bool(array $data, string $key): ?bool
    {
        $value = $data[$key] ?? null;

        if (is_bool($value)) {
            return $value;
        }

        return is_scalar($value) ? filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function array(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        return is_array($value) ? self::normalize($value) : [];
    }
}
