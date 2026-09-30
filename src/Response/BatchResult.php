<?php

declare(strict_types=1);

namespace GenderApi\Response;

use GenderApi\Http\HttpResponse;
use GenderApi\Internal\Json;
use GenderApi\Internal\ResponseShape;

/**
 * Response of POST /gender/batch with HTTP 200: every item succeeded or the batch partially succeeded.
 *
 * Partial success is not an exception: check hasFailures() / failed(). When retrying, resubmit only
 * the failed items after billing is confirmed; resubmitting successful items charges them again.
 *
 * @implements \IteratorAggregate<int, BatchItemResult>
 */
final class BatchResult implements \IteratorAggregate, \Countable
{
    /**
     * @param list<BatchItemResult>  $items
     * @param array<string, mixed>   $raw
     * @param array<string, string>  $headers
     */
    public function __construct(
        public readonly array $items,
        public readonly Meta $meta,
        public readonly array $raw,
        public readonly array $headers = [],
    ) {
    }

    /** @param array<string, mixed> $body */
    public static function fromResponse(array $body, HttpResponse $response): self
    {
        $rows = Json::arr($body, 'data');
        if ($rows === null || !array_is_list($rows)) {
            throw ResponseShape::unexpected('Batch response has no "data" array.', $body, $response);
        }
        $items = ResponseShape::batchItems($rows);
        if ($items === null) {
            throw ResponseShape::unexpected(
                'Batch response row lacks an integer index or exactly one of data/error.',
                $body,
                $response,
            );
        }

        return new self($items, ResponseShape::meta($body), $body, $response->headers);
    }

    public function summary(): ?BatchSummary
    {
        return $this->meta->summary;
    }

    public function hasFailures(): bool
    {
        return $this->failed() !== [];
    }

    /** @return list<BatchItemResult> */
    public function failed(): array
    {
        return array_values(array_filter($this->items, static fn (BatchItemResult $i): bool => $i->isFailure()));
    }

    /** @return list<BatchItemResult> successful items, including unknown results */
    public function succeeded(): array
    {
        return array_values(array_filter($this->items, static fn (BatchItemResult $i): bool => $i->isSuccess()));
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->raw;
    }
}
