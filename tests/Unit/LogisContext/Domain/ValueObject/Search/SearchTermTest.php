<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\ValueObject\Search;

use Logis\LogisContext\Domain\Exception\Search\InvalidSearchTermException;
use Logis\LogisContext\Domain\ValueObject\Search\SearchTerm;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SearchTermTest extends TestCase
{
    public function test_it_trims_the_term(): void
    {
        $this->assertSame('zebra printer', SearchTerm::fromString(value: '  zebra printer ')->value());
    }

    public function test_it_keeps_wildcard_characters_as_they_are(): void
    {
        $this->assertSame('50%_off', SearchTerm::fromString(value: '50%_off')->value());
    }

    public function test_it_rejects_text_that_is_not_valid_utf8(): void
    {
        $this->expectException(InvalidSearchTermException::class);
        $this->expectExceptionMessage('The search term must be valid UTF-8 text.');

        SearchTerm::fromString(value: "zebra\xFF");
    }

    #[DataProvider('emptyTerms')]
    public function test_it_rejects_an_empty_term(string $value): void
    {
        $this->expectException(InvalidSearchTermException::class);

        SearchTerm::fromString(value: $value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function emptyTerms(): iterable
    {
        yield 'empty' => [''];
        yield 'spaces' => ['   '];
        yield 'tabs and new lines' => ["\t\n "];
    }
}
