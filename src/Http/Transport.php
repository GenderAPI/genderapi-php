<?php

declare(strict_types=1);

namespace GenderApi\Http;

use GenderApi\Exception\TimeoutException;
use GenderApi\Exception\TransportException;

/**
 * Sends one HTTP request. Implementations must:
 *  - send the request exactly once (no retries),
 *  - never follow redirects (return the 3xx response as is),
 *  - enforce $request->timeout and throw TimeoutException when it elapses,
 *  - return 4xx/5xx responses rather than throwing.
 */
interface Transport
{
    /**
     * @throws TransportException when no usable response was received
     * @throws TimeoutException   when the timeout elapsed
     */
    public function send(HttpRequest $request): HttpResponse;
}
