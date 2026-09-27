<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\ValueObject\CustomView;

use Logis\LogisContext\Domain\ValueObject\CustomView\UserFilterMode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UserFilterModeTest extends TestCase
{
    #[DataProvider('modes')]
    public function test_each_mode_answers_what_the_user_can_do(UserFilterMode $mode, bool $allowsUserFilter, bool $replacesSavedFilters, bool $requiresDiscreteValues): void
    {
        $this->assertSame($allowsUserFilter, $mode->allowsUserFilter());
        $this->assertSame($replacesSavedFilters, $mode->replacesSavedFilters());
        $this->assertSame($requiresDiscreteValues, $mode->requiresDiscreteValues());
    }

    /**
     * @return array<string, array{UserFilterMode, bool, bool, bool}>
     */
    public static function modes(): array
    {
        return [
            'restricted' => [UserFilterMode::Restricted, true, false, true],
            'open' => [UserFilterMode::Open, true, true, false],
            'locked' => [UserFilterMode::Locked, false, false, false],
        ];
    }
}
