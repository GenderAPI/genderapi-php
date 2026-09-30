<?php

declare(strict_types=1);

namespace GenderApi\Response;

use GenderApi\Internal\Json;

/**
 * data.match: which dataset candidate matched and how.
 *
 * method is "normalized", "token", "substring", "model_inference" (AI) or null.
 * scope is "country", "global" or null. All fields are null when there was no match.
 */
final class PredictionMatch
{
    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public readonly ?string $name,
        public readonly ?string $method,
        public readonly ?string $scope,
        public readonly ?string $country,
        public readonly array $raw,
    ) {
    }

    /** @param array<string, mixed> $raw */
    public static function fromArray(array $raw): self
    {
        return new self(
            Json::str($raw, 'name'),
            Json::str($raw, 'method'),
            Json::str($raw, 'scope'),
            Json::str($raw, 'country'),
            $raw,
        );
    }
}
