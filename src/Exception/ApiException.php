<?php

declare(strict_types=1);

namespace GenderApi\Exception;

use GenderApi\Internal\Json;
use GenderApi\Internal\ResponseShape;
use GenderApi\Response\BatchItemResult;
use GenderApi\Response\Meta;
use GenderApi\Response\Problem;

/**
 * The API answered with HTTP status >= 400.
 *
 * Exposes the RFC 9457 Problem Details fields (errorCode is the stable machine "code"),
 * billing state and, for an all-failed batch, the per-item rows. Match on errorCode and
 * status, never on detail text. The SDK never retries: read action and billingStatus first.
 *
 * $body may contain your submitted inputs; do not log it wholesale.
 */
class ApiException extends \RuntimeException implements GenderApiException
{
    /** Stable machine-readable error code (Problem "code"), e.g. "insufficient_credits". */
    public readonly ?string $errorCode;
    public readonly ?string $title;
    public readonly ?string $detail;
    public readonly ?string $type;
    public readonly ?string $instance;
    /** Suggested action from the error catalog, e.g. "wait_then_retry" or "contact_support". */
    public readonly ?string $action;
    public readonly ?string $documentation;
    /** @var list<array{pointer?: string, message?: string}> validation pointers */
    public readonly array $errors;
    public readonly ?string $requestId;
    /** Seconds to wait from the Retry-After header (HTTP 429), if sent. */
    public readonly ?int $retryAfter;
    /** meta.usage.billing_status: "not_charged", "confirmed", "unconfirmed" or null. */
    public readonly ?string $billingStatus;
    public readonly ?Meta $meta;
    /** @var list<BatchItemResult> rows of an all-failed batch ("data"), empty otherwise */
    public readonly array $batchItems;

    /**
     * @param array<string, mixed>|null $body    decoded Problem JSON, null for a non-JSON body (e.g. proxy HTML)
     * @param array<string, string>     $headers lower-cased response headers
     */
    final public function __construct(
        public readonly int $status,
        public readonly ?array $body,
        public readonly string $rawBody = '',
        public readonly array $headers = [],
    ) {
        $problem = Problem::fromArray($body ?? []);
        $metaArray = $body !== null ? Json::arr($body, 'meta') : null;

        $this->errorCode = $problem->code;
        $this->title = $problem->title;
        $this->detail = $problem->detail;
        $this->type = $problem->type;
        $this->instance = $problem->instance;
        $this->action = $problem->action;
        $this->documentation = $problem->documentation;
        $this->errors = $problem->errors;
        $this->requestId = ResponseShape::requestId($body, $headers);
        $this->retryAfter = ResponseShape::retryAfter($headers['retry-after'] ?? null);
        $this->meta = $metaArray !== null ? Meta::fromArray($metaArray) : null;
        $this->billingStatus = $this->meta?->usage?->billingStatus;
        $this->batchItems = self::parseBatchItems($body);

        $message = sprintf('GenderAPI returned HTTP %d', $status);
        if ($this->errorCode !== null) {
            $message .= ' (' . $this->errorCode . ')';
        }
        if ($this->detail !== null && $this->detail !== '') {
            $message .= ': ' . $this->detail;
        }
        if ($this->billingStatus === 'unconfirmed') {
            $message .= ' Billing is unconfirmed; contact support with the request ID and do not retry automatically.';
        }

        parent::__construct($message, $status);
    }

    /**
     * Build the most specific exception subclass for an HTTP error response.
     *
     * @param array<string, mixed>|null $body
     * @param array<string, string>     $headers
     */
    public static function fromResponse(int $status, ?array $body, string $rawBody, array $headers): self
    {
        $code = $body !== null ? Json::str($body, 'code') : null;
        $class = match (true) {
            $status === 401 => AuthenticationException::class,
            $status === 403 && $code === 'insufficient_credits' => InsufficientCreditsException::class,
            $status === 403 => ForbiddenException::class,
            $status === 429 => RateLimitException::class,
            $status >= 500 => ServerException::class,
            default => InvalidRequestException::class,
        };

        return new $class($status, $body, $rawBody, $headers);
    }

    public function isBillingUnconfirmed(): bool
    {
        return $this->billingStatus === 'unconfirmed';
    }

    /** @return list<BatchItemResult> */
    public function failedBatchItems(): array
    {
        return array_values(array_filter($this->batchItems, static fn (BatchItemResult $i): bool => $i->isFailure()));
    }

    /**
     * @param array<string, mixed>|null $body
     * @return list<BatchItemResult>
     */
    private static function parseBatchItems(?array $body): array
    {
        if ($body === null) {
            return [];
        }
        // "results" is the deprecated alias of "data" on all-failed batches.
        $rows = Json::arr($body, 'data') ?? Json::arr($body, 'results');
        if ($rows === null || !array_is_list($rows)) {
            return [];
        }

        return ResponseShape::batchItems($rows) ?? [];
    }
}
