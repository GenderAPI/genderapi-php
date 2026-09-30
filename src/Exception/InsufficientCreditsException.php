<?php

declare(strict_types=1);

namespace GenderApi\Exception;

/**
 * HTTP 403 with code "insufficient_credits": add credits or wait for the reported trial reset
 * (meta.usage.resets_at).
 */
class InsufficientCreditsException extends ForbiddenException
{
}
