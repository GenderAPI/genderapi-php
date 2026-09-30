<?php

declare(strict_types=1);

namespace GenderApi\Exception;

/**
 * The server answered with a 3xx redirect. The SDK never follows redirects,
 * so the API key is never forwarded to another location.
 */
class RedirectException extends UnexpectedResponseException
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        int $status,
        public readonly ?string $location,
        ?string $requestId = null,
        array $headers = [],
        string $rawBody = '',
    ) {
        parent::__construct(
            sprintf('GenderAPI responded with HTTP %d redirect; redirects are not followed. Check the base URL.', $status),
            $status,
            $requestId,
            $headers,
            null,
            $rawBody,
        );
    }
}
