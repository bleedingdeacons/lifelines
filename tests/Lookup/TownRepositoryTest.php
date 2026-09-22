<?php

declare(strict_types=1);

namespace LifeLines\Tests\Lookup;

use LifeLines\Lookup\TownRepository;

/*
 * Covers TownRepository::search — the empty-term and empty-whitelist guards,
 * and the happy path that builds the prepared query and maps the rows back.
 */

covers(TownRepository::class);

beforeEach(function () {
    unset($GLOBALS['lifelines_test_rows']);
    $GLOBALS['wpdb']->queries = [];
});

describe('search', function () {
    it('returns no rows for a blank term', function () {
        expect((new TownRepository())->search('   ', ['Place'], ['Place'], 50))->toBe([]);
    });

    it('returns no rows for unknown columns', function () {
        $repo = new TownRepository();
        expect($repo->search('bath', ['Nonsense'], ['Place'], 50))->toBe([])
            ->and($repo->search('bath', ['Place'], ['Nonsense'], 50))->toBe([]);
    });

    it('builds a query and returns rows', function () {
        $GLOBALS['lifelines_test_rows'] = [
            ['Place' => 'Bath', 'County' => 'Somerset'],
            ['Place' => 'Bathgate', 'County' => 'West Lothian'],
        ];

        $rows = (new TownRepository())->search('bath', ['Place', 'County'], ['Place', 'County'], 25);

        expect($rows)->toHaveCount(2)
            ->and($rows[0]['Place'])->toBe('Bath');

        // The generated SQL selects and searches the whitelisted columns.
        expect($GLOBALS['wpdb']->queries[0])->toContain('`Place`', 'LIKE %s', 'LIMIT 25');
    });

    it('clamps the limit to the allowed maximum', function () {
        $GLOBALS['lifelines_test_rows'] = [];
        (new TownRepository())->search('bath', ['Place'], ['Place'], 100000);

        expect($GLOBALS['wpdb']->queries[0])->toContain('LIMIT 200');
    });

    it('turns a non-array result into an empty list', function () {
        $GLOBALS['lifelines_test_rows'] = null;
        expect((new TownRepository())->search('bath', ['Place'], ['Place'], 50))->toBe([]);
    });
});
