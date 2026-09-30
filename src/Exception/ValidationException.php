<?php

declare(strict_types=1);

namespace GenderApi\Exception;

/**
 * Client-side input or configuration error. Thrown before any network request is made.
 */
class ValidationException extends \InvalidArgumentException implements GenderApiException
{
    /**
     * @param string|null $pointer JSON pointer of the offending request field, e.g. "/items/2/value"
     */
    public function __construct(string $message, public readonly ?string $pointer = null)
    {
        parent::__construct($message);
    }
}
