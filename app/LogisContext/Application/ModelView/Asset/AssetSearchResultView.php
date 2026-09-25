<?php

declare(strict_types=1);

namespace Logis\LogisContext\Application\ModelView\Asset;

final readonly class AssetSearchResultView
{
    private function __construct(
        public int $id,
        public string $serialNumber,
        public string $manufacturer,
        public string $model,
        public string $itemName,
        public string $status,
    ) {
    }

    public static function from(int $id, string $serialNumber, string $manufacturer, string $model, string $itemName, string $status): self
    {
        return new self(id: $id, serialNumber: $serialNumber, manufacturer: $manufacturer, model: $model, itemName: $itemName, status: $status);
    }

    /**
     * @return array{id: int, serial_number: string, manufacturer: string, model: string, item_name: string, status: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'serial_number' => $this->serialNumber,
            'manufacturer' => $this->manufacturer,
            'model' => $this->model,
            'item_name' => $this->itemName,
            'status' => $this->status,
        ];
    }
}
