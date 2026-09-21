<?php

declare(strict_types=1);

use LifeLines\Lookup\Columns;

/*
 * Columns is the security linchpin of the lookup feature: column identifiers
 * are back-ticked straight into SQL, and the only thing making that safe is
 * that they must appear in this whitelist first. These tests pin that
 * guarantee.
 */

covers(Columns::class);

describe('keys', function () {
    it('match the declared column map', function () {
        expect(Columns::keys())
            ->toBe(array_keys(Columns::ALL))
            ->toContain('ID', 'Place');
    });
});

describe('isValid', function () {
    it('accepts every declared column', function () {
        expect(array_map(Columns::isValid(...), Columns::keys()))->each->toBeTrue();
    });

    // Validation is by exact key. Anything else — including case variants and
    // the labels — must be rejected, because a near-miss that slipped through
    // would be interpolated into SQL.
    it('rejects anything not in the whitelist', function (string $column) {
        expect(Columns::isValid($column))->toBeFalse();
    })->with([
        'unknown column'    => ['Nonsense'],
        'empty string'      => [''],
        'lowercase variant' => ['id'],
        'label not key'     => ['Phone Number'],
        'sql injection'     => ['ID`; DROP TABLE wp_life_lines; --'],
        'backtick'          => ['`ID`'],
        'wildcard'          => ['*'],
        'whitespace padded' => [' ID'],
    ]);
});

describe('label', function () {
    it('falls back to the key when unknown', function () {
        expect(Columns::label('Number'))->toBe('Phone Number')
            ->and(Columns::label('AA_Region'))->toBe('AA Region')
            ->and(Columns::label('Nonsense'))->toBe('Nonsense');
    });
});

describe('whitelist', function () {
    it('keeps only valid columns and preserves order', function () {
        expect(Columns::whitelist(['Place', 'Nonsense', 'ID', 'DROP TABLE']))->toBe(['Place', 'ID']);
    });

    it('removes duplicates', function () {
        expect(Columns::whitelist(['ID', 'Place', 'ID', 'Place']))->toBe(['ID', 'Place']);
    });

    it('ignores non-string entries', function () {
        expect(Columns::whitelist(['ID', 42, null, ['Place'], true]))->toBe(['ID']);
    });

    it('returns empty for a non-array', function (mixed $input) {
        expect(Columns::whitelist($input))->toBe([]);
    })->with([
        'null'   => [null],
        'string' => ['ID'],
        'int'    => [1],
        'false'  => [false],
    ]);

    it('of everything returns every column', function () {
        expect(Columns::whitelist(Columns::keys()))->toBe(Columns::keys());
    });
});
