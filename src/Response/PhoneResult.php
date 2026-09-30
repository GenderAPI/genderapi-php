<?php

declare(strict_types=1);

namespace GenderApi\Response;

use GenderApi\Http\HttpResponse;
use GenderApi\Internal\Json;
use GenderApi\Internal\ResponseShape;

/**
 * Response of POST /phone/validate. This checks number structure, not subscriber existence.
 * A completed validation is billed (1 credit) even when valid is false.
 */
final class PhoneResult
{
    /**
     * @param array<string, mixed>  $data
     * @param array<string, mixed>  $raw
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly ?bool $valid,
        public readonly ?bool $possible,
        public readonly ?string $e164,
        public readonly ?string $country,
        public readonly ?int $countryCallingCode,
        public readonly array $data,
        public readonly Meta $meta,
        public readonly array $raw,
        public readonly array $headers = [],
    ) {
    }

    /** @param array<string, mixed> $body */
    public static function fromResponse(array $body, HttpResponse $response): self
    {
        $data = Json::arr($body, 'data');
        if ($data === null) {
            throw ResponseShape::unexpected('Phone response has no "data" object.', $body, $response);
        }

        return new self(
            Json::bool($data, 'valid'),
            Json::bool($data, 'possible'),
            Json::str($data, 'e164'),
            Json::str($data, 'country'),
            Json::int($data, 'country_calling_code'),
            $data,
            ResponseShape::meta($body),
            $body,
            $response->headers,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->raw;
    }
}
