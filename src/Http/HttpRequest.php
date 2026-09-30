<?php

declare(strict_types=1);

namespace GenderApi\Http;

/**
 * An outgoing request handed to a Transport.
 */
final class HttpRequest
{
    /**
     * @param array<string, string> $headers
     * @param float                 $timeout total time limit in seconds
     */
    public function __construct(
        public readonly string $method,
        public readonly string $url,
        public readonly array $headers,
        public readonly ?string $body,
        public readonly float $timeout,
    ) {
    }

    /** @return list<string> "Name: value" header lines */
    public function headerLines(): array
    {
        $lines = [];
        foreach ($this->headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }

        return $lines;
    }

    /** @return array<string, mixed> */
    public function __debugInfo(): array
    {
        $headers = $this->headers;
        if (isset($headers['Authorization'])) {
            $headers['Authorization'] = 'Bearer [redacted]';
        }

        return ['method' => $this->method, 'url' => $this->url, 'headers' => $headers, 'timeout' => $this->timeout];
    }
}
