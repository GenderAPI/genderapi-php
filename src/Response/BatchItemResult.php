<?php

declare(strict_types=1);

namespace GenderApi\Response;

use GenderApi\Internal\Json;

/**
 * One batch row. Exactly one of $data (success, possibly unknown) or $error (failure) is set.
 * index is the position of the submitted item; id echoes the optional id you sent.
 */
final class BatchItemResult
{
    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public readonly int $index,
        public readonly ?string $id,
        public readonly ?int $chargedCredits,
        public readonly ?Prediction $data,
        public readonly ?Problem $error,
        public readonly array $raw,
    ) {
    }

    /**
     * @param array<array-key, mixed> $raw
     * @return self|null null when the row does not have the documented shape
     */
    public static function tryFromArray(array $raw): ?self
    {
        $index = Json::int($raw, 'index');
        $data = Json::arr($raw, 'data');
        $error = Json::arr($raw, 'error');
        if ($index === null || ($data === null) === ($error === null)) {
            return null;
        }

        /** @var array<string, mixed> $raw */
        return new self(
            $index,
            Json::str($raw, 'id'),
            Json::int($raw, 'charged_credits'),
            $data !== null ? Prediction::fromArray($data) : null,
            $error !== null ? Problem::fromArray($error) : null,
            $raw,
        );
    }

    public function isSuccess(): bool
    {
        return $this->data !== null;
    }

    public function isFailure(): bool
    {
        return $this->error !== null;
    }
}
