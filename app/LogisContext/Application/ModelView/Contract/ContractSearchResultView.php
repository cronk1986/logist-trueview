<?php

declare(strict_types=1);

namespace Logis\LogisContext\Application\ModelView\Contract;

final readonly class ContractSearchResultView
{
    private function __construct(public int $id, public string $name, public string $status)
    {
    }

    public static function from(int $id, string $name, string $status): self
    {
        return new self(id: $id, name: $name, status: $status);
    }

    /**
     * @return array{id: int, name: string, status: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status,
        ];
    }
}
