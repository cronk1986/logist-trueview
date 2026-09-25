<?php

declare(strict_types=1);

namespace Logis\LogisContext\Application\ModelView\Location;

final readonly class LocationSearchResultView
{
    private function __construct(
        public int $id,
        public string $title,
        public ?string $city,
        public ?string $state,
        public ?string $postalCode,
    ) {
    }

    public static function from(int $id, string $title, ?string $city, ?string $state, ?string $postalCode): self
    {
        return new self(id: $id, title: $title, city: $city, state: $state, postalCode: $postalCode);
    }

    /**
     * @return array{id: int, title: string, city: ?string, state: ?string, postal_code: ?string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postalCode,
        ];
    }
}
