<?php

declare(strict_types=1);

namespace GenderApi\Response;

use GenderApi\Internal\Json;

/**
 * meta.summary for batch responses: total = succeeded + failed; succeeded = identified + unknown.
 * An unknown result is a successful, billable prediction, not an error.
 */
final class BatchSummary
{
    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public readonly ?int $total,
        public readonly ?int $succeeded,
        public readonly ?int $identified,
        public readonly ?int $unknown,
        public readonly ?int $failed,
        public readonly array $raw,
    ) {
    }

    /** @param array<string, mixed> $raw */
    public static function fromArray(array $raw): self
    {
        return new self(
            Json::int($raw, 'total'),
            Json::int($raw, 'succeeded'),
            Json::int($raw, 'identified'),
            Json::int($raw, 'unknown'),
            Json::int($raw, 'failed'),
            $raw,
        );
    }
}
