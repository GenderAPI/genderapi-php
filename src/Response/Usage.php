<?php

declare(strict_types=1);

namespace GenderApi\Response;

use GenderApi\Internal\Json;

/**
 * meta.usage: the billing outcome of this operation.
 *
 * billingStatus:
 *  - "not_charged": rejected before debit or an unbilled read; chargedCredits is 0.
 *  - "confirmed":   the net charge is known (it may be 0 after a confirmed refund).
 *  - "unconfirmed": chargedCredits is null; the outcome must be checked before resending.
 *
 * remainingCredits is a completion-time balance. It can be negative, or null when unknown.
 * resetsAt, limit and periodSeconds are only set for IP-trial access; resetsAt is not a
 * subscription expiry.
 */
final class Usage
{
    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public readonly ?string $billingStatus,
        public readonly ?int $chargedCredits,
        public readonly ?int $remainingCredits,
        public readonly ?string $resetsAt,
        public readonly ?int $limit,
        public readonly ?int $periodSeconds,
        public readonly array $raw,
    ) {
    }

    /** @param array<string, mixed> $raw */
    public static function fromArray(array $raw): self
    {
        return new self(
            Json::str($raw, 'billing_status'),
            Json::int($raw, 'charged_credits'),
            Json::int($raw, 'remaining_credits'),
            Json::str($raw, 'resets_at'),
            Json::int($raw, 'limit'),
            Json::int($raw, 'period_seconds'),
            $raw,
        );
    }

    public function isConfirmed(): bool
    {
        return $this->billingStatus === 'confirmed';
    }

    public function isUnconfirmed(): bool
    {
        return $this->billingStatus === 'unconfirmed';
    }

    public function isNotCharged(): bool
    {
        return $this->billingStatus === 'not_charged';
    }
}
