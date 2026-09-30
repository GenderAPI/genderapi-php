<?php

declare(strict_types=1);

namespace GenderApi\Internal;

use GenderApi\Exception\ValidationException;

/**
 * Cheap, certain client-side checks that mirror the V2 request schema.
 * The API remains authoritative (email syntax, ISO country membership, trial batch limit).
 *
 * @internal
 */
final class Validator
{
    public const TYPES = ['name', 'email', 'username'];
    public const AI_MODES = ['off', 'fallback', 'always'];
    public const MAX_VALUE_LENGTH = 254;
    public const MAX_ID_LENGTH = 64;
    public const MAX_BATCH_ITEMS = 50;

    private const ITEM_KEYS = ['id', 'type', 'value', 'country', 'forceToGenderize', 'options'];

    /**
     * Validate a wire-format item and return it normalised (null fields and empty options removed).
     *
     * @param array<array-key, mixed> $item
     * @return array<string, mixed>
     */
    public static function item(array $item, string $pointer = ''): array
    {
        foreach (array_keys($item) as $key) {
            if (!in_array($key, self::ITEM_KEYS, true)) {
                throw new ValidationException(
                    sprintf('Unsupported item field "%s". Use the V2 wire names: %s.', $key, implode(', ', self::ITEM_KEYS)),
                    $pointer . '/' . $key,
                );
            }
        }

        $type = $item['type'] ?? null;
        if (!is_string($type) || !in_array($type, self::TYPES, true)) {
            throw new ValidationException('type must be "name", "email" or "username".', $pointer . '/type');
        }
        $value = $item['value'] ?? null;
        if (!is_string($value)) {
            throw new ValidationException('value must be a string.', $pointer . '/value');
        }
        self::value($value, $pointer . '/value');

        $out = [];
        if (isset($item['id'])) {
            $out['id'] = self::id($item['id'], $pointer . '/id');
        }
        $out['type'] = $type;
        $out['value'] = $value;
        if (isset($item['country'])) {
            $out['country'] = self::country($item['country'], $pointer . '/country');
        }

        $force = $item['forceToGenderize'] ?? null;
        if ($force !== null && !is_bool($force)) {
            throw new ValidationException('forceToGenderize must be a boolean.', $pointer . '/forceToGenderize');
        }
        if ($force !== null) {
            $out['forceToGenderize'] = $force;
        }

        $options = $item['options'] ?? null;
        $aiMode = null;
        if ($options !== null) {
            if (!is_array($options)) {
                throw new ValidationException('options must be an array such as [\'ai_mode\' => \'off\'].', $pointer . '/options');
            }
            foreach (array_keys($options) as $key) {
                if ($key !== 'ai_mode') {
                    throw new ValidationException(sprintf('Unsupported options field "%s"; only ai_mode is allowed.', $key), $pointer . '/options/' . $key);
                }
            }
            $aiMode = $options['ai_mode'] ?? null;
            if ($aiMode !== null) {
                self::aiMode($aiMode, $pointer . '/options/ai_mode');
                $out['options'] = ['ai_mode' => $aiMode];
            }
        }

        if ($force === true && $aiMode !== null && $aiMode !== 'fallback') {
            throw new ValidationException(
                'forceToGenderize cannot be combined with ai_mode "off" or "always"; omit ai_mode or use "fallback".',
                $pointer . '/options/ai_mode',
            );
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $items already validated items
     */
    public static function batch(array $items): void
    {
        $count = count($items);
        if ($count < 1 || $count > self::MAX_BATCH_ITEMS) {
            throw new ValidationException(sprintf(
                'A batch needs 1-%d items (the IP trial allows at most 10); %d given. Split larger jobs yourself.',
                self::MAX_BATCH_ITEMS,
                $count,
            ), '/items');
        }
        $seen = [];
        foreach ($items as $index => $item) {
            if (!isset($item['id'])) {
                continue;
            }
            if (isset($seen[$item['id']])) {
                throw new ValidationException('Batch item ids must be unique.', '/items/' . $index . '/id');
            }
            $seen[$item['id']] = true;
        }
    }

    public static function value(string $value, string $pointer = '/value'): void
    {
        $length = self::length($value, $pointer);
        if ($length < 1 || $length > self::MAX_VALUE_LENGTH || preg_match('/\S/', $value) !== 1) {
            throw new ValidationException(sprintf('value must contain 1-%d characters and not only whitespace.', self::MAX_VALUE_LENGTH), $pointer);
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            throw new ValidationException('value must not contain control characters.', $pointer);
        }
    }

    public static function country(mixed $country, string $pointer = '/country'): string
    {
        if (!is_string($country) || preg_match('/^[A-Z]{2}$/D', $country) !== 1) {
            throw new ValidationException('country must be an uppercase ISO 3166-1 alpha-2 code such as "US"; omit it when unknown.', $pointer);
        }

        return $country;
    }

    public static function aiMode(mixed $aiMode, string $pointer = '/options/ai_mode'): string
    {
        if (!is_string($aiMode) || !in_array($aiMode, self::AI_MODES, true)) {
            throw new ValidationException('ai_mode must be "off", "fallback" or "always".', $pointer);
        }

        return $aiMode;
    }

    public static function id(mixed $id, string $pointer = '/id'): string
    {
        if (!is_string($id)) {
            throw new ValidationException('id must be a string.', $pointer);
        }
        $length = self::length($id, $pointer);
        if ($length < 1 || $length > self::MAX_ID_LENGTH) {
            throw new ValidationException(sprintf('id must contain 1-%d characters.', self::MAX_ID_LENGTH), $pointer);
        }

        return $id;
    }

    /**
     * @return array{number: string, country?: string}
     */
    public static function phone(string $number, ?string $country): array
    {
        $length = strlen($number);
        if ($length < 3 || $length > 32 || preg_match('/^\+?[0-9 ()\-]+$/D', $number) !== 1) {
            throw new ValidationException('number must be 3-32 characters of digits, spaces, parentheses or hyphens, optionally starting with +.', '/number');
        }
        $payload = ['number' => $number];
        if ($country !== null) {
            $payload['country'] = self::country($country);
        } elseif ($number[0] !== '+') {
            throw new ValidationException('country is required for a national number (one that does not start with +).', '/country');
        }

        return $payload;
    }

    /** Length in Unicode code points (as JSON Schema counts it). */
    private static function length(string $value, string $pointer): int
    {
        $count = preg_match_all('/./su', $value);
        if ($count === false) {
            throw new ValidationException('Text must be valid UTF-8.', $pointer);
        }

        return $count;
    }
}
