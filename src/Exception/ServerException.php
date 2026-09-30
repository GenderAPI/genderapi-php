<?php

declare(strict_types=1);

namespace GenderApi\Exception;

/**
 * HTTP 5xx. Inspect billingStatus before sending another request; unconfirmed billing requires contacting support with requestId.
 */
class ServerException extends ApiException
{
}
