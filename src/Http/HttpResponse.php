<?php

declare(strict_types=1);

namespace GenderApi\Http;

/**
 * A raw HTTP response returned by a Transport.
 */
final class HttpResponse
{
    /** @var array<string, string> lower-cased header names */
    public readonly array $headers;

    /**
     * @param array<string, string> $headers header names are lower-cased on construction
     */
    public function __construct(
        public readonly int $status,
        array $headers,
        public readonly string $body,
    ) {
        $normalised = [];
        foreach ($headers as $name => $value) {
            $normalised[strtolower((string) $name)] = $value;
        }
        $this->headers = $normalised;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /**
     * Parse raw "Name: value" header lines (status lines are ignored; repeated names are joined with ", ").
     *
     * @param iterable<string> $lines
     * @return array<string, string>
     */
    public static function parseHeaderLines(iterable $lines): array
    {
        $headers = [];
        foreach ($lines as $line) {
            $pos = strpos($line, ':');
            if ($pos === false || str_starts_with($line, 'HTTP/')) {
                continue;
            }
            $name = strtolower(trim(substr($line, 0, $pos)));
            $value = trim(substr($line, $pos + 1));
            $headers[$name] = isset($headers[$name]) ? $headers[$name] . ', ' . $value : $value;
        }

        return $headers;
    }
}
