<?php

declare(strict_types=1);

namespace GenderApi\Internal;

/**
 * Tolerant typed readers for decoded JSON objects.
 *
 * A field with an unexpected type is read as null; the original value is always
 * kept in the owning object's raw array.
 *
 * @internal
 */
final class Json
{
    /** @param array<array-key, mixed> $a */
    public static function str(array $a, string $key): ?string
    {
        return isset($a[$key]) && is_string($a[$key]) ? $a[$key] : null;
    }

    /** @param array<array-key, mixed> $a */
    public static function int(array $a, string $key): ?int
    {
        return isset($a[$key]) && is_int($a[$key]) ? $a[$key] : null;
    }

    /** @param array<array-key, mixed> $a */
    public static function float(array $a, string $key): ?float
    {
        if (!isset($a[$key])) {
            return null;
        }
        $value = $a[$key];

        return is_int($value) || is_float($value) ? (float) $value : null;
    }

    /** @param array<array-key, mixed> $a */
    public static function bool(array $a, string $key): ?bool
    {
        return isset($a[$key]) && is_bool($a[$key]) ? $a[$key] : null;
    }

    /**
     * @param array<array-key, mixed> $a
     * @return array<array-key, mixed>|null
     */
    public static function arr(array $a, string $key): ?array
    {
        return isset($a[$key]) && is_array($a[$key]) ? $a[$key] : null;
    }

    /**
     * Decode a response body that must be a JSON object.
     *
     * @return array<string, mixed>|null null when the body is not a JSON object
     */
    public static function decodeObject(string $body): ?array
    {
        $trimmed = ltrim($body);
        if ($trimmed === '' || $trimmed[0] !== '{') {
            return null;
        }
        try {
            $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (\JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }
}
