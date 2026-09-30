<?php

declare(strict_types=1);

namespace GenderApi\Response;

use GenderApi\Internal\Json;

/**
 * One gender inference ("data" of a single response, or of a successful batch item).
 *
 * Results are inferences, not identity verification, and can be unknown: gender is then null,
 * resultStatus is "unknown" and reason explains why. confidence is on a 0-1 scale and must be
 * read together with confidenceKind: "observed_frequency" (dataset share) or "model_reported"
 * (an AI model score, not a calibrated probability). It is null when gender is null.
 */
final class Prediction
{
    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public readonly array $input,
        public readonly ?string $name,
        public readonly ?string $gender,
        public readonly ?string $country,
        public readonly ?float $confidence,
        public readonly ?string $confidenceKind,
        public readonly ?int $sampleCount,
        public readonly ?string $source,
        public readonly ?string $resultStatus,
        public readonly ?string $reason,
        public readonly ?string $countrySource,
        public readonly ?PredictionMatch $match,
        public readonly array $raw,
    ) {
    }

    /** @param array<string, mixed> $raw */
    public static function fromArray(array $raw): self
    {
        $match = Json::arr($raw, 'match');

        return new self(
            Json::arr($raw, 'input') ?? [],
            Json::str($raw, 'name'),
            Json::str($raw, 'gender'),
            Json::str($raw, 'country'),
            Json::float($raw, 'confidence'),
            Json::str($raw, 'confidence_kind'),
            Json::int($raw, 'sample_count'),
            Json::str($raw, 'source'),
            Json::str($raw, 'result_status'),
            Json::str($raw, 'reason'),
            Json::str($raw, 'country_source'),
            $match !== null ? PredictionMatch::fromArray($match) : null,
            $raw,
        );
    }

    /** True when the inference produced "male" or "female". */
    public function isIdentified(): bool
    {
        return $this->resultStatus !== null ? $this->resultStatus === 'identified' : $this->gender !== null;
    }

    /** True for a successful (and billable) prediction without a gender. */
    public function isUnknown(): bool
    {
        return !$this->isIdentified();
    }
}
