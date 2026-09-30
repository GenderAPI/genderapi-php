<?php

declare(strict_types=1);

namespace GenderApi\Exception;

/**
 * HTTP 4xx other than 401/403/429: the request must be corrected (400, 404, 405, 413, 415, 422).
 */
class InvalidRequestException extends ApiException
{
}
