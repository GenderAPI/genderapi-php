<?php

declare(strict_types=1);

namespace GenderApi\Response;

use GenderApi\Internal\Json;

/**
 * The response "meta" object: request reference, access mode, usage and (for batches) summary.
 */
final class Meta
{
    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public readonly ?string $requestId,
        public readonly ?int $durationMs,
        public readonly ?Access $access,
        public readonly ?Usage $usage,
        public readonly ?BatchSummary $summary,
        public readonly array $raw,
    ) {
    }

    /** @param array<string, mixed> $raw */
    public static function fromArray(array $raw): self
    {
        $access = Json::arr($raw, 'access');
        $usage = Json::arr($raw, 'usage');
        $summary = Json::arr($raw, 'summary');

        return new self(
            Json::str($raw, 'request_id'),
            Json::int($raw, 'duration_ms'),
            $access !== null ? Access::fromArray($access) : null,
            $usage !== null ? Usage::fromArray($usage) : null,
            $summary !== null ? BatchSummary::fromArray($summary) : null,
            $raw,
        );
    }

    public function billingStatus(): ?string
    {
        return $this->usage?->billingStatus;
    }
}
