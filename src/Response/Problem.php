<?php

declare(strict_types=1);

namespace GenderApi\Response;

use GenderApi\Internal\Json;

/**
 * RFC 9457 Problem Details, as used for batch item errors ("error" of a failed item).
 * Match on code, never on the human-readable detail text.
 */
final class Problem
{
    /**
     * @param list<array{pointer?: string, message?: string}> $errors
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public readonly ?string $code,
        public readonly ?int $status,
        public readonly ?string $title,
        public readonly ?string $detail,
        public readonly ?string $type,
        public readonly ?string $instance,
        public readonly ?string $action,
        public readonly ?string $requestId,
        public readonly ?string $documentation,
        public readonly array $errors,
        public readonly array $raw,
    ) {
    }

    /** @param array<string, mixed> $raw */
    public static function fromArray(array $raw): self
    {
        return new self(
            Json::str($raw, 'code'),
            Json::int($raw, 'status'),
            Json::str($raw, 'title'),
            Json::str($raw, 'detail'),
            Json::str($raw, 'type'),
            Json::str($raw, 'instance'),
            Json::str($raw, 'action'),
            Json::str($raw, 'request_id'),
            Json::str($raw, 'documentation'),
            self::errorList($raw),
            $raw,
        );
    }

    /**
     * @param array<string, mixed> $raw
     * @return list<array{pointer?: string, message?: string}>
     */
    public static function errorList(array $raw): array
    {
        $errors = [];
        foreach (Json::arr($raw, 'errors') ?? [] as $error) {
            if (is_array($error)) {
                $errors[] = $error;
            }
        }

        return $errors;
    }
}
