<?php

declare(strict_types=1);

namespace GenderApi;

use GenderApi\Internal\Validator;

/**
 * A validated prediction item in V2 wire format, for Client::genderBatch().
 *
 * Invalid input throws GenderApi\Exception\ValidationException immediately.
 * Plain arrays using the exact wire field names (type, value, country, id,
 * forceToGenderize, options => [ai_mode]) are accepted too.
 */
final class Item implements \JsonSerializable
{
    /** @param array<string, mixed> $wire */
    private function __construct(private readonly array $wire)
    {
    }

    /**
     * @param string      $type             "name", "email" or "username"
     * @param string|null $country          uppercase ISO alpha-2 code; omit when unknown
     * @param string|null $aiMode           "off", "fallback" or "always"; null uses the server default
     *                                      (fallback for single requests, off for batch items)
     * @param bool        $forceToGenderize dataset first, then nickname-aware AI; not with ai_mode off/always
     * @param string|null $id               your own reference (1-64 characters, unique within a batch)
     */
    public static function of(
        string $type,
        string $value,
        ?string $country = null,
        ?string $aiMode = null,
        bool $forceToGenderize = false,
        ?string $id = null,
    ): self {
        $item = ['type' => $type, 'value' => $value, 'country' => $country, 'id' => $id];
        if ($forceToGenderize) {
            $item['forceToGenderize'] = true;
        }
        if ($aiMode !== null) {
            $item['options'] = ['ai_mode' => $aiMode];
        }

        return new self(Validator::item($item));
    }

    public static function name(string $value, ?string $country = null, ?string $aiMode = null, bool $forceToGenderize = false, ?string $id = null): self
    {
        return self::of('name', $value, $country, $aiMode, $forceToGenderize, $id);
    }

    public static function email(string $value, ?string $country = null, ?string $aiMode = null, bool $forceToGenderize = false, ?string $id = null): self
    {
        return self::of('email', $value, $country, $aiMode, $forceToGenderize, $id);
    }

    public static function username(string $value, ?string $country = null, ?string $aiMode = null, bool $forceToGenderize = false, ?string $id = null): self
    {
        return self::of('username', $value, $country, $aiMode, $forceToGenderize, $id);
    }

    /**
     * Validate a wire-format array.
     *
     * @param array<string, mixed> $item
     */
    public static function fromArray(array $item, string $pointer = ''): self
    {
        return new self(Validator::item($item, $pointer));
    }

    /** @return array<string, mixed> the exact JSON body sent for this item */
    public function toArray(): array
    {
        return $this->wire;
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->wire;
    }
}
