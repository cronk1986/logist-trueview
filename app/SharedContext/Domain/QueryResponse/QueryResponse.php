<?php

declare(strict_types=1);

namespace Logis\SharedContext\Domain\QueryResponse;

final class QueryResponse
{
    /**
     * @param array<mixed> $data
     */
    public function __construct(
        private readonly array $data,
        private readonly ?Meta $meta = null,
    ) {
    }

    /**
     * @param array<mixed> $data
     */
    public static function from(array $data, ?Meta $meta = null): self
    {
        return new self(data: $data, meta: $meta);
    }

    /**
     * @return array<mixed>
     */
    public function data(): array
    {
        return $this->data;
    }

    public function meta(): ?Meta
    {
        return $this->meta;
    }

    /**
     * @return array{data: array<mixed>, meta: array<string, scalar|null>|null}
     */
    public function toArray(): array
    {
        return [
            'data' => $this->data,
            'meta' => $this->meta?->toArray(),
        ];
    }
}
