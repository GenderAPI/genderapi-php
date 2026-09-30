<?php

declare(strict_types=1);

namespace GenderApi\Response;

use GenderApi\Internal\Json;

/**
 * meta.access: how the request was authorized.
 *
 * mode is "api_key", "ip_trial" or "unauthenticated". reason is null, "api_key_missing",
 * "api_key_invalid" or "api_key_not_found". An unrecognised key can fall back to the IP trial,
 * so check isApiKey() when you integrate a paid account.
 */
final class Access
{
    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public readonly ?string $mode,
        public readonly ?string $reason,
        public readonly array $raw,
    ) {
    }

    /** @param array<string, mixed> $raw */
    public static function fromArray(array $raw): self
    {
        return new self(Json::str($raw, 'mode'), Json::str($raw, 'reason'), $raw);
    }

    public function isApiKey(): bool
    {
        return $this->mode === 'api_key';
    }

    public function isIpTrial(): bool
    {
        return $this->mode === 'ip_trial';
    }
}
