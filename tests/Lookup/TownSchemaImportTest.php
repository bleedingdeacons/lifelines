<?php

declare(strict_types=1);

namespace LifeLines\Tests\Lookup;

use LifeLines\Lookup\Columns;
use LifeLines\Lookup\TownSchema;
use PHPUnit\Framework\Assert;

/*
 * Covers TownSchema's database-facing surface against the FakeWpdb stub:
 * tableName/exists/count/install and the import() parser with its header,
 * auto-id, numeric-null, blank-line, batch-error and guard branches.
 */

covers(TownSchema::class);

/**
 * The most recent INSERT the FakeWpdb recorded.
 */
function lastImportInsert(): string
{
    foreach (array_reverse($GLOBALS['wpdb']->queries) as $q) {
        if (stripos($q, 'INSERT') === 0) {
            return $q;
        }
    }
    Assert::fail('No INSERT statement was issued.');
}

beforeEach(function () {
    unset($GLOBALS['lifelines_test_table_ok'], $GLOBALS['lifelines_test_count'], $GLOBALS['lifelines_test_query_fail']);
    $GLOBALS['wpdb']->queries = [];
    $this->tempFiles = [];

    $this->csv = function (string $contents): string {
        $path = tempnam(sys_get_temp_dir(), 'lifelines_imp_') . '.csv';
        file_put_contents($path, $contents);
        $this->tempFiles[] = $path;
        return $path;
    };
});

afterEach(function () {
    foreach ($this->tempFiles as $f) {
        if (file_exists($f)) {
            unlink($f);
        }
    }
    $this->tempFiles = [];
    unset($GLOBALS['lifelines_test_query_fail']);
});

// ── name / exists / count / install ──────────────────────────────────

it('uses the prefix in the table name', function () {
    expect(TownSchema::tableName())->toBe('wp_life_lines');
});

it('reflects the database in exists', function () {
    $GLOBALS['lifelines_test_table_ok'] = true;
    expect(TownSchema::exists())->toBeTrue();

    $GLOBALS['lifelines_test_table_ok'] = false;
    expect(TownSchema::exists())->toBeFalse();
});

it('counts zero when the table is absent', function () {
    $GLOBALS['lifelines_test_table_ok'] = false;
    expect(TownSchema::count())->toBe(0);
});

it('reads the row count when present', function () {
    $GLOBALS['lifelines_test_table_ok'] = true;
    $GLOBALS['lifelines_test_count'] = 4200;
    expect(TownSchema::count())->toBe(4200);
});

it('runs install without error', function () {
    TownSchema::install();
})->throwsNoExceptions();

// ── import guards ────────────────────────────────────────────────────

describe('import guards', function () {
    it('rejects a missing file', function () {
        $result = TownSchema::import(sys_get_temp_dir() . '/does-not-exist-' . uniqid() . '.csv');
        expect($result['ok'])->toBeFalse()
            ->and($result['message'])->toContain('not found');
    });

    it('rejects an empty file', function () {
        $result = TownSchema::import(($this->csv)(''));
        expect($result['ok'])->toBeFalse()
            ->and($result['message'])->toContain('empty');
    });

    it('rejects unrecognised headers', function () {
        $result = TownSchema::import(($this->csv)("Foo,Bar\n1,2\n"));
        expect($result['ok'])->toBeFalse()
            ->and($result['message'])->toContain('recognised');
    });
});

// ── import happy paths ───────────────────────────────────────────────

describe('import', function () {
    it('inserts rows with ids and numeric handling', function () {
        $result = TownSchema::import(($this->csv)(
            "ID,Place,County,Latitude\n"
            . "1,Bath,Somerset,51.38\n"
            . "\n"                       // blank line, skipped
            . "2,Bristol,Avon,notnum\n"  // non-numeric Latitude -> NULL
        ));

        expect($result['ok'])->toBeTrue()
            ->and($result['inserted'])->toBe(2)
            ->and($result['errors'])->toBe(0);

        expect(lastImportInsert())
            ->toContain("'Bath'")
            ->toContain('NULL'); // the bad Latitude
    });

    it('assigns sequential ids when there is no id column', function () {
        $result = TownSchema::import(($this->csv)(
            "Place,County\nBath,Somerset\nBristol,Avon\n"
        ));

        expect($result['ok'])->toBeTrue()
            ->and($result['inserted'])->toBe(2);

        // ID is prepended to the insert column list and auto-numbered from 1.
        expect(lastImportInsert())->toContain('`ID`', '(1,');
    });

    it('reports batch errors', function () {
        $GLOBALS['lifelines_test_query_fail'] = true;

        $result = TownSchema::import(($this->csv)("ID,Place\n1,Bath\n"));

        expect($result['ok'])->toBeFalse()
            ->and($result['inserted'])->toBe(0)
            ->and($result['errors'])->toBe(1)
            ->and($result['message'])->toContain('batch error');
    });

    it('stores blank cells as null', function () {
        // County left blank → NULL for a string column (distinct from the
        // non-numeric-number NULL path).
        $result = TownSchema::import(($this->csv)("ID,Place,County\n1,Bath,\n"));

        expect($result['ok'])->toBeTrue()
            ->and(lastImportInsert())->toContain('NULL');
    });

    it('flushes in batches of five hundred', function () {
        // 500 rows trips the mid-loop flush; the trailing flush then sees an
        // empty batch and returns early.
        $csv = "ID,Place\n";
        for ($i = 1; $i <= 500; $i++) {
            $csv .= "{$i},Place{$i}\n";
        }

        $result = TownSchema::import(($this->csv)($csv));

        expect($result['ok'])->toBeTrue()
            ->and($result['inserted'])->toBe(500);
    });
});

it('streams the header and rows on export', function () {
    // exportCsv() ends in exit(); drive its chunked loop by returning one
    // full chunk then throwing, so it unwinds before the exit. The CSV it
    // has already streamed to php://output is captured here.
    $fullChunk = array_fill(0, 2000, array_fill_keys(Columns::keys(), 'x'));
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

    expect($threw)->toBeTrue('Expected the simulated read failure to unwind exportCsv().')
        ->and($csv)->toContain('ID,Place')  // header row
        ->and($csv)->toContain('x,x');      // a data row
});
