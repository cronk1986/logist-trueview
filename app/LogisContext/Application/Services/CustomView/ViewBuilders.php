<?php

declare(strict_types=1);

namespace Logis\LogisContext\Application\Services\CustomView;

use Logis\LogisContext\Application\Exception\CustomView\ViewBuilderNotRegisteredException;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewType;

final class ViewBuilders
{
    /** @var array<string, ViewBuilder> */
    private array $builders = [];

    /**
     * @param iterable<ViewBuilder> $builders
     */
    public function __construct(iterable $builders)
    {
        foreach ($builders as $builder) {
            $this->register(builder: $builder);
        }

        $this->ensureEveryTypeHasABuilder();
    }

    public function for(ViewType $type): ViewBuilder
    {
        return $this->builders[$type->value];
    }

    private function register(ViewBuilder $builder): void
    {
        $type = $builder->type();

        if (array_key_exists(key: $type->value, array: $this->builders)) {
            throw ViewBuilderNotRegisteredException::registeredTwice(type: $type);
        }

        $this->builders[$type->value] = $builder;
    }

    private function ensureEveryTypeHasABuilder(): void
    {
        foreach (ViewType::cases() as $type) {
            if (!array_key_exists(key: $type->value, array: $this->builders)) {
                throw ViewBuilderNotRegisteredException::forType(type: $type);
            }
        }
    }
}
