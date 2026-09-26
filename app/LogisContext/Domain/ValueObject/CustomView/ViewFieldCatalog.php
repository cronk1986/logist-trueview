<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\ValueObject\CustomView;

use Logis\LogisContext\Domain\ValueObject\Asset\AssetStatus;
use Logis\LogisContext\Domain\ValueObject\Contract\ContractStatus;

final readonly class ViewFieldCatalog
{
    /**
     * @param array<string, ViewField> $fields
     */
    private function __construct(private ViewType $type, private array $fields)
    {
    }

    public static function for(ViewType $type): self
    {
        $fields = match ($type) {
            ViewType::MyAssets => self::assetFields(),
            ViewType::MyTickets => self::ticketFields(),
            ViewType::MyContracts => self::contractFields(),
            ViewType::MyLocations => self::locationFields(),
        };

        $indexed = [];

        foreach ($fields as $field) {
            $indexed[$field->name()] = $field;
        }

        return new self(type: $type, fields: $indexed);
    }

    public function type(): ViewType
    {
        return $this->type;
    }

    public function find(string $name): ?ViewField
    {
        return $this->fields[$name] ?? null;
    }

    public function findSortable(string $name): ?ViewField
    {
        $field = $this->find(name: $name);

        if ($field === null || !$field->isSortable()) {
            return null;
        }

        return $field;
    }

    /**
     * @return list<ViewField>
     */
    public function fields(): array
    {
        return array_values(array: $this->fields);
    }

    /**
     * @return list<ViewField>
     */
    private static function assetFields(): array
    {
        return [
            ViewField::identifier(name: 'id', sortable: true),
            ViewField::text(name: 'serial_number', sortable: true),
            ViewField::text(name: 'manufacturer', sortable: true),
            ViewField::text(name: 'model', sortable: true),
            ViewField::text(name: 'item_name', sortable: true),
            ViewField::choice(name: 'status', choices: self::values(cases: AssetStatus::cases())),
            ViewField::identifier(name: 'customer', sortable: false),
            ViewField::identifier(name: 'contract', sortable: false),
            ViewField::identifier(name: 'location', sortable: false),
            ...self::timestamps(),
        ];
    }

    /**
     * @return list<ViewField>
     */
    private static function ticketFields(): array
    {
        return [
            ViewField::identifier(name: 'id', sortable: true),
            ViewField::text(name: 'repair_id', sortable: true),
            ViewField::text(name: 'incident_number', sortable: true),
            ViewField::text(name: 'subject', sortable: true),
            ViewField::identifier(name: 'status', sortable: false),
            ViewField::identifier(name: 'asset', sortable: false),
            ViewField::identifier(name: 'customer', sortable: false),
            ...self::timestamps(),
        ];
    }

    /**
     * @return list<ViewField>
     */
    private static function contractFields(): array
    {
        return [
            ViewField::identifier(name: 'id', sortable: true),
            ViewField::text(name: 'name', sortable: true),
            ViewField::choice(name: 'status', choices: self::values(cases: ContractStatus::cases())),
            ViewField::identifier(name: 'customer', sortable: false),
            ...self::timestamps(),
        ];
    }

    /**
     * @return list<ViewField>
     */
    private static function locationFields(): array
    {
        return [
            ViewField::identifier(name: 'id', sortable: true),
            ViewField::text(name: 'title', sortable: true),
            ViewField::text(name: 'full_address', sortable: false),
            ViewField::text(name: 'city', sortable: false),
            ViewField::text(name: 'state', sortable: false),
            ViewField::text(name: 'postal_code', sortable: false),
            ViewField::identifier(name: 'customer', sortable: false),
            ...self::timestamps(),
        ];
    }

    /**
     * @return list<ViewField>
     */
    private static function timestamps(): array
    {
        return [
            ViewField::date(name: 'created_at'),
            ViewField::date(name: 'updated_at'),
        ];
    }

    /**
     * @param list<AssetStatus>|list<ContractStatus> $cases
     * @return list<string>
     */
    private static function values(array $cases): array
    {
        return array_map(callback: fn (AssetStatus|ContractStatus $case): string => $case->value, array: $cases);
    }
}
