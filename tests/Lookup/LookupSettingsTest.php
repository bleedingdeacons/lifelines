<?php

declare(strict_types=1);

namespace LifeLines\Tests\Lookup;

use LifeLines\Lookup\LookupSettings;
use BleedingDeacons\WpMocks\WpState;

/*
 * Covers LookupSettings: reading the stored wp_options row (with whitelist
 * fallbacks), the clamped result-limit / min-chars accessors, and the
 * sanitising save() including its empty-configuration guard.
 */

covers(LookupSettings::class);

beforeEach(function () {
    WpState::$options = [];
});

afterEach(function () {
    WpState::$options = [];
});

describe('reading', function () {
    it('uses the defaults when nothing is stored', function () {
        $settings = new LookupSettings();

        expect($settings->searchColumns())->toContain('Place')
            ->and($settings->displayColumns())->toContain('Service_Name')
            ->and($settings->resultLimit())->toBe(50)
            ->and($settings->minChars())->toBe(2);
    });

    it('whitelists the stored columns', function () {
        WpState::$options[LookupSettings::OPTION] = [
            'search_columns'  => ['Place', 'Nonsense', 'Postcode'],
            'display_columns' => ['County', 'DROP TABLE'],
        ];

        $settings = new LookupSettings();
        expect($settings->searchColumns())->toBe(['Place', 'Postcode'])
            ->and($settings->displayColumns())->toBe(['County']);
    });

    it('falls back to the defaults when the stored columns are empty', function () {
        WpState::$options[LookupSettings::OPTION] = [
            'search_columns'  => ['Nonsense'],
            'display_columns' => [],
        ];

        $settings = new LookupSettings();
        expect($settings->searchColumns())->toBe(LookupSettings::defaults()['search_columns'])
            ->and($settings->displayColumns())->toBe(LookupSettings::defaults()['display_columns']);
    });

    it('ignores a non-array stored option', function () {
        WpState::$options[LookupSettings::OPTION] = 'corrupt';

        expect((new LookupSettings())->resultLimit())->toBe(50);
    });

    it('clamps the result limit', function () {
        WpState::$options[LookupSettings::OPTION] = ['result_limit' => 9999];
        expect((new LookupSettings())->resultLimit())->toBe(LookupSettings::MAX_RESULT_LIMIT);

        WpState::$options[LookupSettings::OPTION] = ['result_limit' => 0];
        expect((new LookupSettings())->resultLimit())->toBe(1);
    });

    it('keeps min chars at least one', function () {
        WpState::$options[LookupSettings::OPTION] = ['min_chars' => 0];
        expect((new LookupSettings())->minChars())->toBe(1);
    });
});

describe('save', function () {
    it('persists sanitised values', function () {
        LookupSettings::save([
            'search_columns'  => ['Place', 'Nonsense'],
            'display_columns' => ['County', 'Number'],
            'result_limit'    => 9999,
            'min_chars'       => 3,
        ]);

        $stored = WpState::$options[LookupSettings::OPTION];
        expect($stored['search_columns'])->toBe(['Place'])
            ->and($stored['display_columns'])->toBe(['County', 'Number'])
            ->and($stored['result_limit'])->toBe(LookupSettings::MAX_RESULT_LIMIT)
            ->and($stored['min_chars'])->toBe(3);
    });

    it('guards against an empty configuration', function () {
        LookupSettings::save([
            'search_columns'  => ['Nonsense'],
            'display_columns' => [],
            'result_limit'    => 10,
            'min_chars'       => 1,
        ]);

        $stored = WpState::$options[LookupSettings::OPTION];
        expect($stored['search_columns'])->toBe(LookupSettings::defaults()['search_columns'])
            ->and($stored['display_columns'])->toBe(LookupSettings::defaults()['display_columns']);
    });
});
