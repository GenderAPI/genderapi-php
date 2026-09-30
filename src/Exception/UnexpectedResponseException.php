<?php

declare(strict_types=1);

namespace GenderApi\Exception;

/**
 * A 2xx/3xx response that is not a usable V2 JSON document.
 *
 * The request may have been processed and billed. Keep requestId for support.
 */
class UnexpectedResponseException extends \RuntimeException implements GenderApiException
{
    /**
     * @param array<string, string>      $headers lower-cased response headers
     * @param array<string, mixed>|null  $body    decoded JSON object, when the body was one
     */
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly ?string $requestId = null,
        public readonly array $headers = [],
        public readonly ?array $body = null,
        public readonly string $rawBody = '',
    ) {
        parent::__construct($message);
    }
}
