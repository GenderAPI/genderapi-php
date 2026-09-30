<?php

declare(strict_types=1);

namespace GenderApi\Exception;

/**
 * The configured timeout elapsed before a complete response was received.
 *
 * The operation may still complete and be billed on the server. Do not retry automatically.
 */
class TimeoutException extends TransportException
{
}
