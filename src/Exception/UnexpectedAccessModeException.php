<?php

declare(strict_types=1);

namespace GenderApi\Exception;

use GenderApi\Internal\ResponseShape;
use GenderApi\Response\BatchResult;
use GenderApi\Response\GenderResult;
use GenderApi\Response\PhoneResult;
use GenderApi\Response\UsageResult;

/**
 * An API key was configured, but the successful response reports another access mode in
 * meta.access.mode (usually "ip_trial" because the key was not recognised).
 *
 * The request has already been processed and may have consumed IP-trial credits; it is not retried.
 * The complete parsed result (what the method would have returned) is available via getResult().
 * Disable the check with `new Client(..., requireApiKeyAccess: false)`.
 */
class UnexpectedAccessModeException extends \RuntimeException implements GenderApiException
{
    /** Stable machine-readable error code. */
    public const ERROR_CODE = 'unexpected_access_mode';

    public readonly string $errorCode;
    /** meta.access.mode, e.g. "ip_trial" or "unauthenticated". */
    public readonly ?string $accessMode;
    /** meta.access.reason, e.g. "api_key_invalid" or "api_key_not_found". */
    public readonly ?string $accessReason;
    public readonly ?string $requestId;
    /** @var array<string, mixed> the complete decoded response body */
    public readonly array $body;

    /**
     * @param array<string, string> $headers lower-cased response headers
     */
    public function __construct(
        public readonly GenderResult|BatchResult|UsageResult|PhoneResult $result,
        public readonly int $status,
        public readonly array $headers = [],
        public readonly string $rawBody = '',
    ) {
        $access = $result->meta->access;
        $this->errorCode = self::ERROR_CODE;
        $this->accessMode = $access?->mode;
        $this->accessReason = $access?->reason;
        $this->body = $result->raw;
        $this->requestId = ResponseShape::requestId($result->raw, $headers);

        parent::__construct(sprintf(
            'Expected API-key access but the response reports access mode %s. '
            . 'Check your API key; this request may have consumed IP-trial credits.',
            json_encode($this->accessMode, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ));
    }

    /** The complete parsed result the method would have returned (already processed and possibly billed). */
    public function getResult(): GenderResult|BatchResult|UsageResult|PhoneResult
    {
        return $this->result;
    }

    public function getAccessMode(): ?string
    {
        return $this->accessMode;
    }

    public function getAccessReason(): ?string
    {
        return $this->accessReason;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    /** @return array<string, mixed> */
    public function getBody(): array
    {
        return $this->body;
    }
}
