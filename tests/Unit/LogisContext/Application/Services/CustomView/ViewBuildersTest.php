<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Application\Services\CustomView;

use Logis\LogisContext\Application\Exception\CustomView\ViewBuilderNotRegisteredException;
use Logis\LogisContext\Application\ModelView\CustomView\ViewPage;
use Logis\LogisContext\Application\Services\CustomView\ViewBuilders;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewType;
use PHPUnit\Framework\TestCase;

final class ViewBuildersTest extends TestCase
{
    public function test_it_resolves_the_builder_of_every_view_type(): void
    {
        $builders = new ViewBuilders(builders: $this->builders(types: ViewType::cases()));

        foreach (ViewType::cases() as $type) {
            $this->assertSame($type, $builders->for(type: $type)->type());
        }
    }

    public function test_a_view_type_without_a_builder_fails_fast(): void
    {
        $this->expectException(ViewBuilderNotRegisteredException::class);
        $this->expectExceptionMessage('No view builder is registered for "my_locations".');

        new ViewBuilders(builders: $this->builders(types: [ViewType::MyAssets, ViewType::MyTickets, ViewType::MyContracts]));
    }

    public function test_a_view_type_with_two_builders_fails_fast(): void
    {
        $this->expectException(ViewBuilderNotRegisteredException::class);
        $this->expectExceptionMessage('More than one view builder is registered for "my_assets".');

        new ViewBuilders(builders: $this->builders(types: [...ViewType::cases(), ViewType::MyAssets]));
    }

    /**
     * @param list<ViewType> $types
     * @return list<InMemoryViewBuilder>
     */
    private function builders(array $types): array
    {
        return array_map(callback: fn (ViewType $type): InMemoryViewBuilder => new InMemoryViewBuilder(type: $type, page: ViewPage::empty()), array: $types);
    }
}
