<?php

declare(strict_types=1);

namespace LifeLines\Tests\Lookup;

use LifeLines\Lookup\TownSchema;
use BleedingDeacons\WpMocks\TestCase;
use BleedingDeacons\WpMocks\WpState;

/**
 * Covers TownSchema's database-facing surface against the FakeWpdb stub:
 * tableName/exists/count/install and the import() parser with its header,
 * auto-id, numeric-null, blank-line, batch-error and guard branches.
 *
 * @covers \LifeLines\Lookup\TownSchema
 */
class TownSchemaImportTest extends TestCase
{
    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        unset($GLOBALS['lifelines_test_table_ok'], $GLOBALS['lifelines_test_count'], $GLOBALS['lifelines_test_query_fail']);
        $GLOBALS['wpdb']->queries = [];
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $f) {
            if (file_exists($f)) {
                unlink($f);
            }
        }
        $this->tempFiles = [];
        unset($GLOBALS['lifelines_test_query_fail']);
        parent::tearDown();
    }

    private function csv(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'lifelines_imp_') . '.csv';
        file_put_contents($path, $contents);
        $this->tempFiles[] = $path;
        return $path;
    }

    // ── name / exists / count / install ──────────────────────────────────

    public function testTableNameUsesThePrefix(): void
    {
        $this->assertSame('wp_life_lines', TownSchema::tableName());
    }

    public function testExistsReflectsTheDatabase(): void
    {
        $GLOBALS['lifelines_test_table_ok'] = true;
        $this->assertTrue(TownSchema::exists());

        $GLOBALS['lifelines_test_table_ok'] = false;
        $this->assertFalse(TownSchema::exists());
    }

    public function testCountIsZeroWhenTheTableIsAbsent(): void
    {
        $GLOBALS['lifelines_test_table_ok'] = false;
        $this->assertSame(0, TownSchema::count());
    }

    public function testCountReadsTheRowCountWhenPresent(): void
    {
        $GLOBALS['lifelines_test_table_ok'] = true;
        $GLOBALS['lifelines_test_count'] = 4200;
        $this->assertSame(4200, TownSchema::count());
    }

    public function testInstallRunsWithoutError(): void
    {
        TownSchema::install();
        $this->assertTrue(true);
    }

    // ── import guards ────────────────────────────────────────────────────

    public function testImportRejectsAMissingFile(): void
    {
        $result = TownSchema::import(sys_get_temp_dir() . '/does-not-exist-' . uniqid() . '.csv');
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('not found', $result['message']);
    }

    public function testImportRejectsAnEmptyFile(): void
    {
        $result = TownSchema::import($this->csv(''));
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('empty', $result['message']);
    }

    public function testImportRejectsUnrecognisedHeaders(): void
    {
        $result = TownSchema::import($this->csv("Foo,Bar\n1,2\n"));
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('recognised', $result['message']);
    }

    // ── import happy paths ───────────────────────────────────────────────

    public function testImportInsertsRowsWithIdsAndNumericHandling(): void
    {
        $result = TownSchema::import($this->csv(
            "ID,Place,County,Latitude\n"
            . "1,Bath,Somerset,51.38\n"
            . "\n"                       // blank line, skipped
            . "2,Bristol,Avon,notnum\n"  // non-numeric Latitude -> NULL
        ));

        $this->assertTrue($result['ok']);
        $this->assertSame(2, $result['inserted']);
        $this->assertSame(0, $result['errors']);

        $insert = $this->lastInsert();
        $this->assertStringContainsString("'Bath'", $insert);
        $this->assertStringContainsString('NULL', $insert); // the bad Latitude
    }

    public function testImportAssignsSequentialIdsWhenNoIdColumn(): void
    {
        $result = TownSchema::import($this->csv(
            "Place,County\nBath,Somerset\nBristol,Avon\n"
        ));

        $this->assertTrue($result['ok']);
        $this->assertSame(2, $result['inserted']);

        // ID is prepended to the insert column list and auto-numbered from 1.
        $insert = $this->lastInsert();
        $this->assertStringContainsString('`ID`', $insert);
        $this->assertStringContainsString('(1,', $insert);
    }

    public function testImportReportsBatchErrors(): void
    {
        $GLOBALS['lifelines_test_query_fail'] = true;

        $result = TownSchema::import($this->csv("ID,Place\n1,Bath\n"));

        $this->assertFalse($result['ok']);
        $this->assertSame(0, $result['inserted']);
        $this->assertSame(1, $result['errors']);
        $this->assertStringContainsString('batch error', $result['message']);
    }

    public function testImportStoresBlankCellsAsNull(): void
    {
        // County left blank → NULL for a string column (distinct from the
        // non-numeric-number NULL path).
        $result = TownSchema::import($this->csv("ID,Place,County\n1,Bath,\n"));

        $this->assertTrue($result['ok']);
        $this->assertStringContainsString('NULL', $this->lastInsert());
    }

    public function testImportFlushesInBatchesOfFiveHundred(): void
    {
        // 500 rows trips the mid-loop flush; the trailing flush then sees an
        // empty batch and returns early.
        $csv = "ID,Place\n";
        for ($i = 1; $i <= 500; $i++) {
            $csv .= "{$i},Place{$i}\n";
        }

        $result = TownSchema::import($this->csv($csv));

        $this->assertTrue($result['ok']);
        $this->assertSame(500, $result['inserted']);
    }

    public function testExportStreamsTheHeaderAndRows(): void
    {
        // exportCsv() ends in exit(); drive its chunked loop by returning one
        // full chunk then throwing, so it unwinds before the exit. The CSV it
        // has already streamed to php://output is captured here.
        $fullChunk = array_fill(0, 2000, array_fill_keys(\LifeLines\Lookup\Columns::keys(), 'x'));
        $GLOBALS['lifelines_test_results_queue'] = [$fullChunk, '__throw__'];

        $baseLevel = ob_get_level();
        ob_start();
        $threw = false;
        try {
            TownSchema::exportCsv();
        } catch (\RuntimeException $e) {
            $threw = true;
        }

        // Reclaim only the buffer(s) this test opened — never PHPUnit's own.
        $csv = '';
        while (ob_get_level() > $baseLevel) {
            $csv = ob_get_clean() . $csv;
        }
        unset($GLOBALS['lifelines_test_results_queue']);

        $this->assertTrue($threw, 'Expected the simulated read failure to unwind exportCsv().');
        $this->assertStringContainsString('ID,Place', $csv); // header row
        $this->assertStringContainsString('x,x', $csv);       // a data row
    }

    private function lastInsert(): string
    {
        foreach (array_reverse($GLOBALS['wpdb']->queries) as $q) {
            if (stripos($q, 'INSERT') === 0) {
                return $q;
            }
        }
        $this->fail('No INSERT statement was issued.');
    }
}
