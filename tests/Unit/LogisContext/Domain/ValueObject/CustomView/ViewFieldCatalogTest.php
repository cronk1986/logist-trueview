<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\ValueObject\CustomView;

use Logis\LogisContext\Domain\ValueObject\CustomView\ViewField;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewFieldCatalog;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ViewFieldCatalogTest extends TestCase
{
    /**
     * @param array<string, string> $fields
     * @param list<string> $sortable
     */
    #[DataProvider('catalogs')]
    public function test_each_view_type_has_its_fields(ViewType $type, array $fields, array $sortable): void
    {
        $catalog = ViewFieldCatalog::for(type: $type);

        $this->assertSame($type, $catalog->type());
        $this->assertSame($fields, $this->typesByName(catalog: $catalog));
        $this->assertSame($sortable, $this->sortableNames(catalog: $catalog));
    }

    /**
     * @return array<string, array{ViewType, array<string, string>, list<string>}>
     */
    public static function catalogs(): array
    {
        $timestamps = ['created_at' => 'date', 'updated_at' => 'date'];

        return [
            'assets' => [
                ViewType::MyAssets,
                [
                    'id' => 'identifier',
                    'serial_number' => 'text',
                    'manufacturer' => 'text',
                    'model' => 'text',
                    'item_name' => 'text',
                    'status' => 'choice',
                    'customer' => 'identifier',
                    'contract' => 'identifier',
                    'location' => 'identifier',
                    ...$timestamps,
                ],
                ['id', 'serial_number', 'manufacturer', 'model', 'item_name', 'status', 'created_at', 'updated_at'],
            ],
            'tickets' => [
                ViewType::MyTickets,
                [
                    'id' => 'identifier',
                    'repair_id' => 'text',
                    'incident_number' => 'text',
                    'subject' => 'text',
                    'status' => 'identifier',
                    'asset' => 'identifier',
                    'customer' => 'identifier',
                    ...$timestamps,
                ],
                ['id', 'repair_id', 'incident_number', 'subject', 'created_at', 'updated_at'],
            ],
            'contracts' => [
                ViewType::MyContracts,
                ['id' => 'identifier', 'name' => 'text', 'status' => 'choice', 'customer' => 'identifier', ...$timestamps],
                ['id', 'name', 'status', 'created_at', 'updated_at'],
            ],
            'locations' => [
                ViewType::MyLocations,
                [
                    'id' => 'identifier',
                    'title' => 'text',
                    'full_address' => 'text',
                    'city' => 'text',
                    'state' => 'text',
                    'postal_code' => 'text',
                    'customer' => 'identifier',
                    ...$timestamps,
                ],
                ['id', 'title', 'created_at', 'updated_at'],
            ],
        ];
    }

    public function test_choice_values_follow_the_domain_enums(): void
    {
        $this->assertSame(['active', 'inactive', 'retired'], ViewFieldCatalog::for(type: ViewType::MyAssets)->find(name: 'status')?->choices());
        $this->assertSame(['active', 'inactive'], ViewFieldCatalog::for(type: ViewType::MyContracts)->find(name: 'status')?->choices());
    }

    public function test_internal_columns_are_not_fields(): void
    {
        $catalog = ViewFieldCatalog::for(type: ViewType::MyTickets);

        foreach (['customer_id', 'deleted_at', 'created_by', 'issue_description', 'status_id', 'asset_id'] as $column) {
            $this->assertNull($catalog->find(name: $column), $column);
        }
    }

    public function test_it_finds_only_sortable_fields_for_sorting(): void
    {
        $catalog = ViewFieldCatalog::for(type: ViewType::MyAssets);

        $this->assertSame('created_at', $catalog->findSortable(name: 'created_at')?->name());
        $this->assertNull($catalog->findSortable(name: 'location'));
        $this->assertNull($catalog->findSortable(name: 'unknown'));
    }

    /**
     * @return array<string, string>
     */
    private function typesByName(ViewFieldCatalog $catalog): array
    {
        $types = [];

        foreach ($catalog->fields() as $field) {
            $types[$field->name()] = $field->type()->value;
        }

        return $types;
    }

    /**
     * @return list<string>
     */
    private function sortableNames(ViewFieldCatalog $catalog): array
    {
        $sortable = array_filter(array: $catalog->fields(), callback: fn (ViewField $field): bool => $field->isSortable());

        return array_values(array: array_map(callback: fn (ViewField $field): string => $field->name(), array: $sortable));
    }
}
