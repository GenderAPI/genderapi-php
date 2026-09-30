<?php

declare(strict_types=1);

namespace GenderApi\Exception;

/**
 * HTTP 403: access denied, disabled/expired key or insufficient credits (see errorCode).
 */
class ForbiddenException extends ApiException
{
}
