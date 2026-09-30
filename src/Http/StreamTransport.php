<?php

declare(strict_types=1);

namespace GenderApi\Http;

use GenderApi\Exception\TimeoutException;
use GenderApi\Exception\TransportException;

/**
 * Transport based on PHP's http:// stream wrapper, for environments without ext-curl
 * (HTTPS needs ext-openssl). Sends once, never follows redirects, verifies TLS.
 * The timeout bounds connecting, waiting for headers and reading the body.
 */
final class StreamTransport implements Transport
{
    public function send(HttpRequest $request): HttpResponse
    {
        $deadline = microtime(true) + $request->timeout;
        $context = stream_context_create([
            'http' => [
                'method' => $request->method,
                'header' => implode("\r\n", array_merge($request->headerLines(), ['Connection: close'])),
                'content' => $request->body ?? '',
                'timeout' => $request->timeout,
                'follow_location' => 0,
                'ignore_errors' => true,
                'protocol_version' => 1.1,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $error = null;
        set_error_handler(static function (int $level, string $message) use (&$error): bool {
            $error = $message;

            return true;
        });
        try {
            $stream = fopen($request->url, 'rb', false, $context);
        } finally {
            restore_error_handler();
        }

        if ($stream === false) {
            if (microtime(true) >= $deadline - 0.01 || stripos((string) $error, 'timed out') !== false) {
                throw $this->timeout($request);
            }
            throw new TransportException(sprintf(
                'GenderAPI request failed (%s). The billing outcome is unknown; do not retry automatically.',
                self::cleanError($error),
            ));
        }

        try {
            $meta = stream_get_meta_data($stream);
            $body = '';
            while (!feof($stream)) {
                $remaining = $deadline - microtime(true);
                if ($remaining <= 0) {
                    throw $this->timeout($request);
                }
                stream_set_timeout($stream, (int) floor($remaining), (int) (fmod($remaining, 1.0) * 1_000_000));
                $chunk = fread($stream, 65536);
                if ($chunk === false) {
                    throw new TransportException('GenderAPI response could not be read. The billing outcome is unknown; do not retry automatically.');
                }
                $body .= $chunk;
                if (stream_get_meta_data($stream)['timed_out']) {
                    throw $this->timeout($request);
                }
            }
        } finally {
            fclose($stream);
        }

        /** @var list<string> $lines */
        $lines = is_array($meta['wrapper_data'] ?? null) ? $meta['wrapper_data'] : [];
        $status = 0;
        $start = 0;
        foreach ($lines as $i => $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m) === 1) {
                $status = (int) $m[1];
                $start = $i + 1;
            }
        }
        if ($status === 0) {
            throw new TransportException('GenderAPI response had no HTTP status line. The billing outcome is unknown; do not retry automatically.');
        }

        return new HttpResponse($status, HttpResponse::parseHeaderLines(array_slice($lines, $start)), $body);
    }

    private function timeout(HttpRequest $request): TimeoutException
    {
        return new TimeoutException(sprintf(
            'GenderAPI request timed out after %.3g s. The operation may still complete and be billed; do not retry automatically.',
            $request->timeout,
        ));
    }

    private static function cleanError(?string $error): string
    {
        if ($error === null || $error === '') {
            return 'connection failed';
        }
        // Drop the "fopen(url): " prefix so the message stays short.
        $pos = strpos($error, '): ');

        return $pos !== false ? substr($error, $pos + 3) : $error;
    }
}
