<?php

declare(strict_types=1);

namespace LifeLines\Tests\Lookup;

use LifeLines\Lookup\Columns;
use PHPUnit\Framework\TestCase;

/**
 * Columns is the security linchpin of the lookup feature: column identifiers
 * are back-ticked straight into SQL, and the only thing making that safe is
 * that they must appear in this whitelist first. These tests pin that
 * guarantee.
 *
 * @covers \LifeLines\Lookup\Columns
 */
class ColumnsTest extends TestCase
{
    /**
     * @test
     */
    public function keys_match_the_declared_column_map(): void
    {
        $this->assertSame(array_keys(Columns::ALL), Columns::keys());
        $this->assertContains('ID', Columns::keys());
        $this->assertContains('Place', Columns::keys());
    }

    /**
     * @test
     */
    public function every_declared_column_validates(): void
    {
        foreach (Columns::keys() as $column) {
            $this->assertTrue(Columns::isValid($column), "{$column} should be a valid column");
        }
    }

    /**
     * Validation is by exact key. Anything else — including case variants and
     * the labels — must be rejected, because a near-miss that slipped through
     * would be interpolated into SQL.
     *
     * @test
     * @dataProvider invalidColumnProvider
     */
    public function it_rejects_anything_not_in_the_whitelist(string $column): void
    {
        $this->assertFalse(Columns::isValid($column));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidColumnProvider(): array
    {
        return [
            'unknown column'      => ['Nonsense'],
            'empty string'        => [''],
            'lowercase variant'   => ['id'],
            'label not key'       => ['Phone Number'],
            'sql injection'       => ['ID`; DROP TABLE wp_life_lines; --'],
            'backtick'            => ['`ID`'],
            'wildcard'            => ['*'],
            'whitespace padded'   => [' ID'],
        ];
    }

    /**
     * @test
     */
    public function label_falls_back_to_the_key_when_unknown(): void
    {
        $this->assertSame('Phone Number', Columns::label('Number'));
        $this->assertSame('AA Region', Columns::label('AA_Region'));
        $this->assertSame('Nonsense', Columns::label('Nonsense'));
    }

    /**
     * @test
     */
    public function whitelist_keeps_only_valid_columns_and_preserves_order(): void
    {
        $result = Columns::whitelist(['Place', 'Nonsense', 'ID', 'DROP TABLE']);

        $this->assertSame(['Place', 'ID'], $result);
    }

    /**
     * @test
     */
    public function whitelist_removes_duplicates(): void
    {
        $this->assertSame(['ID', 'Place'], Columns::whitelist(['ID', 'Place', 'ID', 'Place']));
    }

    /**
     * @test
     */
    public function whitelist_ignores_non_string_entries(): void
    {
        $this->assertSame(['ID'], Columns::whitelist(['ID', 42, null, ['Place'], true]));
    }

    /**
     * @test
     * @dataProvider nonArrayProvider
     */
    public function whitelist_returns_empty_for_a_non_array(mixed $input): void
    {
        $this->assertSame([], Columns::whitelist($input));
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function nonArrayProvider(): array
    {
        return [
            'null'   => [null],
            'string' => ['ID'],
            'int'    => [1],
            'false'  => [false],
        ];
    }

    /**
     * @test
     */
    public function whitelist_of_everything_returns_every_column(): void
    {
        $this->assertSame(Columns::keys(), Columns::whitelist(Columns::keys()));
    }
}
