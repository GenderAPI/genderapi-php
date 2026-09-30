<?php

declare(strict_types=1);

namespace GenderApi\Exception;

/**
 * No usable HTTP response was received (DNS, TLS, connection reset, ...).
 *
 * The request may still have reached the API and been processed and billed.
 * The SDK never retries; check GET /usage or contact support before resending.
 */
class TransportException extends \RuntimeException implements GenderApiException
{
}
