<?php

declare(strict_types=1);

namespace GenderApi\Response;

use GenderApi\Http\HttpResponse;
use GenderApi\Internal\Json;
use GenderApi\Internal\ResponseShape;

/**
 * Response of POST /gender. An unknown result (data->gender === null) is a successful,
 * billable outcome; inspect meta->usage for the charge.
 */
final class GenderResult
{
    /**
     * @param array<string, mixed>  $raw     the complete decoded response body, unknown fields included
     * @param array<string, string> $headers lower-cased response headers
     */
    public function __construct(
        public readonly Prediction $data,
        public readonly Meta $meta,
        public readonly array $raw,
        public readonly array $headers = [],
    ) {
    }

    /** @param array<string, mixed> $body */
    public static function fromResponse(array $body, HttpResponse $response): self
    {
        $data = Json::arr($body, 'data');
        if ($data === null || array_is_list($data) && $data !== []) {
            throw ResponseShape::unexpected('Response has no prediction "data" object.', $body, $response);
        }

        return new self(Prediction::fromArray($data), ResponseShape::meta($body), $body, $response->headers);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->raw;
    }
}
