<?php

declare(strict_types=1);

namespace LifeLines\Tests\Lookup;

use LifeLines\Lookup\LookupSettings;
use PHPUnit\Framework\TestCase;

/**
 * Covers LookupSettings: reading the stored wp_options row (with whitelist
 * fallbacks), the clamped result-limit / min-chars accessors, and the
 * sanitising save() including its empty-configuration guard.
 *
 * @covers \LifeLines\Lookup\LookupSettings
 */
class LookupSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['lifelines_test_options'] = [];
    }

    protected function tearDown(): void
    {
        $GLOBALS['lifelines_test_options'] = [];
        parent::tearDown();
    }

    public function testDefaultsWhenNothingIsStored(): void
    {
        $settings = new LookupSettings();

        $this->assertContains('Place', $settings->searchColumns());
        $this->assertContains('Service_Name', $settings->displayColumns());
        $this->assertSame(50, $settings->resultLimit());
        $this->assertSame(2, $settings->minChars());
    }

    public function testStoredColumnsAreWhitelisted(): void
    {
        $GLOBALS['lifelines_test_options'][LookupSettings::OPTION] = [
            'search_columns'  => ['Place', 'Nonsense', 'Postcode'],
            'display_columns' => ['County', 'DROP TABLE'],
        ];

        $settings = new LookupSettings();
        $this->assertSame(['Place', 'Postcode'], $settings->searchColumns());
        $this->assertSame(['County'], $settings->displayColumns());
    }

    public function testEmptyStoredColumnsFallBackToDefaults(): void
    {
        $GLOBALS['lifelines_test_options'][LookupSettings::OPTION] = [
            'search_columns'  => ['Nonsense'],
            'display_columns' => [],
        ];

        $settings = new LookupSettings();
        $this->assertSame(LookupSettings::defaults()['search_columns'], $settings->searchColumns());
        $this->assertSame(LookupSettings::defaults()['display_columns'], $settings->displayColumns());
    }

    public function testNonArrayStoredOptionIsIgnored(): void
    {
        $GLOBALS['lifelines_test_options'][LookupSettings::OPTION] = 'corrupt';

        $settings = new LookupSettings();
        $this->assertSame(50, $settings->resultLimit());
    }

    public function testResultLimitIsClamped(): void
    {
        $GLOBALS['lifelines_test_options'][LookupSettings::OPTION] = ['result_limit' => 9999];
        $this->assertSame(LookupSettings::MAX_RESULT_LIMIT, (new LookupSettings())->resultLimit());

        $GLOBALS['lifelines_test_options'][LookupSettings::OPTION] = ['result_limit' => 0];
        $this->assertSame(1, (new LookupSettings())->resultLimit());
    }

    public function testMinCharsIsAtLeastOne(): void
    {
        $GLOBALS['lifelines_test_options'][LookupSettings::OPTION] = ['min_chars' => 0];
        $this->assertSame(1, (new LookupSettings())->minChars());
    }

    public function testSavePersistsSanitisedValues(): void
    {
        LookupSettings::save([
            'search_columns'  => ['Place', 'Nonsense'],
            'display_columns' => ['County', 'Number'],
            'result_limit'    => 9999,
            'min_chars'       => 3,
        ]);

        $stored = $GLOBALS['lifelines_test_options'][LookupSettings::OPTION];
        $this->assertSame(['Place'], $stored['search_columns']);
        $this->assertSame(['County', 'Number'], $stored['display_columns']);
        $this->assertSame(LookupSettings::MAX_RESULT_LIMIT, $stored['result_limit']);
        $this->assertSame(3, $stored['min_chars']);
    }

    public function testSaveGuardsAgainstAnEmptyConfiguration(): void
    {
        LookupSettings::save([
            'search_columns'  => ['Nonsense'],
            'display_columns' => [],
            'result_limit'    => 10,
            'min_chars'       => 1,
        ]);

        $stored = $GLOBALS['lifelines_test_options'][LookupSettings::OPTION];
        $this->assertSame(LookupSettings::defaults()['search_columns'], $stored['search_columns']);
        $this->assertSame(LookupSettings::defaults()['display_columns'], $stored['display_columns']);
    }
}
