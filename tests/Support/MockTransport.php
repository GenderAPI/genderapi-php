<?php

declare(strict_types=1);

namespace GenderApi\Tests\Support;

use GenderApi\Http\HttpRequest;
use GenderApi\Http\HttpResponse;
use GenderApi\Http\Transport;

/**
 * Records requests and replays queued responses or exceptions. Never touches the network.
 */
final class MockTransport implements Transport
{
    /** @var list<HttpRequest> */
    public array $requests = [];

    /** @var list<HttpResponse|\Throwable> */
    private array $queue;

    public function __construct(HttpResponse|\Throwable ...$responses)
    {
        $this->queue = array_values($responses);
    }

    public function send(HttpRequest $request): HttpResponse
    {
        $this->requests[] = $request;
        if ($this->queue === []) {
            throw new \LogicException('MockTransport received an unexpected request (a retry?).');
        }
        $next = array_shift($this->queue);
        if ($next instanceof \Throwable) {
            throw $next;
        }

        return $next;
    }

    /**
     * @param array<string, mixed>  $body
     * @param array<string, string> $headers
     */
    public static function json(int $status, array $body, array $headers = []): HttpResponse
    {
        $type = $status >= 400 ? 'application/problem+json' : 'application/json';

        return new HttpResponse(
            $status,
            $headers + ['Content-Type' => $type, 'X-Request-ID' => 'header-request-id'],
            json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );
    }

    public function lastRequest(): HttpRequest
    {
        if ($this->requests === []) {
            throw new \LogicException('No request was sent.');
        }

        return $this->requests[array_key_last($this->requests)];
    }

    /** @return array<string, mixed>|null */
    public function lastJsonBody(): ?array
    {
        $body = $this->lastRequest()->body;

        return $body === null ? null : json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    }
}
