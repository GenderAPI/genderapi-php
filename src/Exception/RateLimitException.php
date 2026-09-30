<?php

declare(strict_types=1);

namespace GenderApi\Exception;

/**
 * HTTP 429. Wait for retryAfter seconds; a later request is a new, billable operation.
 */
class RateLimitException extends ApiException
{
}
