<?php

declare(strict_types=1);

namespace LifeLines\Tests\Lookup;

use LifeLines\Lookup\TownRepository;
use BleedingDeacons\WpMocks\TestCase;
use BleedingDeacons\WpMocks\WpState;

/**
 * Covers TownRepository::search — the empty-term and empty-whitelist guards,
 * and the happy path that builds the prepared query and maps the rows back.
 *
 * @covers \LifeLines\Lookup\TownRepository
 */
class TownRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        unset($GLOBALS['lifelines_test_rows']);
        $GLOBALS['wpdb']->queries = [];
    }

    public function testBlankTermReturnsNoRows(): void
    {
        $this->assertSame([], (new TownRepository())->search('   ', ['Place'], ['Place'], 50));
    }

    public function testUnknownColumnsReturnNoRows(): void
    {
        $repo = new TownRepository();
        $this->assertSame([], $repo->search('bath', ['Nonsense'], ['Place'], 50));
        $this->assertSame([], $repo->search('bath', ['Place'], ['Nonsense'], 50));
    }

    public function testSearchBuildsAQueryAndReturnsRows(): void
    {
        $GLOBALS['lifelines_test_rows'] = [
            ['Place' => 'Bath', 'County' => 'Somerset'],
            ['Place' => 'Bathgate', 'County' => 'West Lothian'],
        ];

        $rows = (new TownRepository())->search('bath', ['Place', 'County'], ['Place', 'County'], 25);

        $this->assertCount(2, $rows);
        $this->assertSame('Bath', $rows[0]['Place']);

        // The generated SQL selects and searches the whitelisted columns.
        $sql = $GLOBALS['wpdb']->queries[0];
        $this->assertStringContainsString('`Place`', $sql);
        $this->assertStringContainsString('LIKE %s', $sql);
        $this->assertStringContainsString('LIMIT 25', $sql);
    }

    public function testLimitIsClampedToTheAllowedMaximum(): void
    {
        $GLOBALS['lifelines_test_rows'] = [];
        (new TownRepository())->search('bath', ['Place'], ['Place'], 100000);

        $this->assertStringContainsString('LIMIT 200', $GLOBALS['wpdb']->queries[0]);
    }

    public function testANonArrayResultBecomesAnEmptyList(): void
    {
        $GLOBALS['lifelines_test_rows'] = null;
        $this->assertSame([], (new TownRepository())->search('bath', ['Place'], ['Place'], 50));
    }
}
