<?php

declare(strict_types=1);

namespace GenderApi\Response;

use GenderApi\Http\HttpResponse;
use GenderApi\Internal\Json;
use GenderApi\Internal\ResponseShape;

/**
 * Response of the free GET /usage endpoint (account balance, or the IP-trial allowance without a key).
 * remainingCredits can be negative or null; resetsAt/limit/periodSeconds are set for IP trials only.
 */
final class UsageResult
{
    /**
     * @param array<string, mixed>  $data
     * @param array<string, mixed>  $raw
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly ?int $remainingCredits,
        public readonly ?string $expiresAt,
        public readonly ?string $resetsAt,
        public readonly ?int $limit,
        public readonly ?int $periodSeconds,
        public readonly array $data,
        public readonly Meta $meta,
        public readonly array $raw,
        public readonly array $headers = [],
    ) {
    }

    /** @param array<string, mixed> $body */
    public static function fromResponse(array $body, HttpResponse $response): self
    {
        $data = Json::arr($body, 'data');
        if ($data === null) {
            throw ResponseShape::unexpected('Usage response has no "data" object.', $body, $response);
        }

        return new self(
            Json::int($data, 'remaining_credits'),
            Json::str($data, 'expires_at'),
            Json::str($data, 'resets_at'),
            Json::int($data, 'limit'),
            Json::int($data, 'period_seconds'),
            $data,
            ResponseShape::meta($body),
            $body,
            $response->headers,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->raw;
    }
}
