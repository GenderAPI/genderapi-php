<?php

declare(strict_types=1);

namespace GenderApi\Internal;

use GenderApi\Exception\UnexpectedResponseException;
use GenderApi\Http\HttpResponse;
use GenderApi\Response\BatchItemResult;
use GenderApi\Response\Meta;

/**
 * @internal
 */
final class ResponseShape
{
    /** @param array<string, mixed> $body */
    public static function meta(array $body): Meta
    {
        return Meta::fromArray(Json::arr($body, 'meta') ?? []);
    }

    /**
     * @param list<mixed> $rows
     * @return list<BatchItemResult>|null null if any row is malformed
     */
    public static function batchItems(array $rows): ?array
    {
        $items = [];
        foreach ($rows as $row) {
            $item = is_array($row) ? BatchItemResult::tryFromArray($row) : null;
            if ($item === null) {
                return null;
            }
            $items[] = $item;
        }

        return $items;
    }

    /**
     * Request reference: body.request_id, then meta.request_id, then the X-Request-ID header.
     *
     * @param array<string, mixed>|null $body
     * @param array<string, string>     $headers
     */
    public static function requestId(?array $body, array $headers): ?string
    {
        if ($body !== null) {
            $id = Json::str($body, 'request_id') ?? Json::str(Json::arr($body, 'meta') ?? [], 'request_id');
            if ($id !== null && $id !== '') {
                return $id;
            }
        }
        $header = $headers['x-request-id'] ?? '';

        return $header !== '' ? $header : null;
    }

    /**
     * Retry-After in seconds (delta-seconds or HTTP-date), null when absent or unparsable.
     */
    public static function retryAfter(?string $value, ?int $now = null): ?int
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (ctype_digit($value)) {
            return (int) $value;
        }
        $date = \DateTimeImmutable::createFromFormat(\DATE_RFC7231, $value, new \DateTimeZone('UTC'));
        if ($date === false) {
            return null;
        }

        return max(0, $date->getTimestamp() - ($now ?? time()));
    }

    /** @param array<string, mixed>|null $body */
    public static function unexpected(string $message, ?array $body, HttpResponse $response): UnexpectedResponseException
    {
        return new UnexpectedResponseException(
            $message . ' The request may have been processed and billed; keep the request ID and do not resend automatically.',
            $response->status,
            self::requestId($body, $response->headers),
            $response->headers,
            $body,
            $response->body,
        );
    }
}
