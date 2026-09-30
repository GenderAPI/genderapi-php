<?php

declare(strict_types=1);

namespace GenderApi\Http;

use GenderApi\Exception\TimeoutException;
use GenderApi\Exception\TransportException;

/**
 * Transport based on ext-curl. Sends once, never follows redirects, verifies TLS,
 * and applies the request timeout to the whole exchange.
 */
final class CurlTransport implements Transport
{
    public function __construct()
    {
        if (!\extension_loaded('curl')) {
            throw new TransportException('ext-curl is not loaded; use GenderApi\Http\StreamTransport instead.');
        }
    }

    public function send(HttpRequest $request): HttpResponse
    {
        $handle = curl_init();
        if ($handle === false) {
            throw new TransportException('Could not initialise cURL.');
        }

        $timeoutMs = max(1, (int) ceil($request->timeout * 1000));
        $headerLines = [];
        $options = [
            CURLOPT_URL => $request->url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_TIMEOUT_MS => $timeoutMs,
            CURLOPT_CONNECTTIMEOUT_MS => $timeoutMs,
            CURLOPT_NOSIGNAL => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            // An empty "Expect:" stops cURL from waiting for "100 Continue".
            CURLOPT_HTTPHEADER => array_merge($request->headerLines(), ['Expect:']),
            CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$headerLines): int {
                if (str_starts_with($line, 'HTTP/')) {
                    $headerLines = []; // keep only the final response's headers
                } else {
                    $headerLines[] = rtrim($line, "\r\n");
                }

                return strlen($line);
            },
        ];
        if (\defined('CURLOPT_PROTOCOLS_STR')) {
            $options[\CURLOPT_PROTOCOLS_STR] = 'https,http';
        } else {
            $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS | CURLPROTO_HTTP;
        }
        if ($request->method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = $request->body ?? '';
        } elseif ($request->method === 'GET') {
            $options[CURLOPT_HTTPGET] = true;
        } else {
            $options[CURLOPT_CUSTOMREQUEST] = $request->method;
        }

        if (!curl_setopt_array($handle, $options)) {
            throw new TransportException('Could not configure cURL.');
        }

        $body = curl_exec($handle);
        if ($body === false) {
            $errno = curl_errno($handle);
            $error = curl_error($handle);
            if ($errno === CURLE_OPERATION_TIMEDOUT) {
                throw new TimeoutException(sprintf(
                    'GenderAPI request timed out after %.3g s. The operation may still complete and be billed; do not retry automatically.',
                    $request->timeout,
                ));
            }
            throw new TransportException(sprintf(
                'GenderAPI request failed (cURL error %d: %s). The billing outcome is unknown; do not retry automatically.',
                $errno,
                $error,
            ));
        }

        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

        return new HttpResponse($status, HttpResponse::parseHeaderLines($headerLines), (string) $body);
    }
}
