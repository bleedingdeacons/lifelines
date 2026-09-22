<?php

declare(strict_types=1);

namespace LifeLines\Tests\Lookup;

use LifeLines\Lookup\TownSchema;
use ReflectionMethod;

/*
 * Covers TownSchema's CSV escaping.
 *
 * import() and exportCsv() cannot be driven directly from a unit test —
 * import() calls install(), which requires wp-admin/includes/upgrade.php and a
 * live $wpdb, and exportCsv() sends headers and exits. Both funnel all of
 * their CSV I/O through readCsvRow()/writeCsvRow(), so these exercise the real
 * production helpers (and therefore the real CSV_ESCAPE) rather than a copy of
 * the logic.
 *
 * The behaviour under test is a fixed bug: with PHP's legacy backslash escape
 * a quoted field ending in a backslash escaped its own closing quote, the
 * parser ran past the end of the record, and two rows silently became one —
 * losing a town on import with no error.
 */

covers(TownSchema::class);

// ── Helpers ─────────────────────────────────────────────────────────

/**
 * Read a whole file through TownSchema's real reader.
 *
 * @return array<int, array<int, string|null>>
 */
function readCsvFile(string $path): array
{
    $read = new ReflectionMethod(TownSchema::class, 'readCsvRow');
    $handle = fopen($path, 'r');

    $rows = [];
    while (($row = $read->invoke(null, $handle)) !== false) {
        $rows[] = $row;
    }
    fclose($handle);

    return $rows;
}

/**
 * Write one row through TownSchema's real writer.
 *
 * @param resource          $handle
 * @param array<int, mixed> $fields
 */
function writeCsvRowThroughSchema($handle, array $fields): void
{
    (new ReflectionMethod(TownSchema::class, 'writeCsvRow'))->invoke(null, $handle, $fields);
}

beforeEach(function () {
    $this->tempFiles = [];

    // A temp .csv path, remembered so afterEach can delete it.
    $this->tempPath = function (): string {
        $path = tempnam(sys_get_temp_dir(), 'lifelines_csv_') . '.csv';
        $this->tempFiles[] = $path;

        return $path;
    };

    $this->writeRaw = function (string $contents): string {
        $path = ($this->tempPath)();
        file_put_contents($path, $contents);

        return $path;
    };
});

afterEach(function () {
    foreach ($this->tempFiles as $file) {
        if (file_exists($file)) {
            unlink($file);
        }
    }
    $this->tempFiles = [];
});

// The regression. Written as raw bytes, exactly as a spreadsheet
// application would emit them, so the parser is what is under test.
it('does not let a field ending in a backslash swallow the next row', function () {
    $path = ($this->writeRaw)(
        "ID,Place,Notes\n"
        . "1,Ambleside,\"ends with backslash\\\"\n"
        . "2,Buxton,North\n"
    );

    $rows = readCsvFile($path);

    expect($rows)->toHaveCount(3, 'Header plus both town rows must survive parsing.')
        ->and($rows[0])->toBe(['ID', 'Place', 'Notes'])
        ->and($rows[1][1])->toBe('Ambleside')
        ->and($rows[1][2])->toBe('ends with backslash\\')
        ->and($rows[2][1])->toBe('Buxton', 'The second town must not be swallowed by the first.');
});

it('treats a backslash inside a field as data, not an escape', function () {
    $path = ($this->writeRaw)("ID,Place\n1,\"North\\South\"\n");

    expect(readCsvFile($path)[1][1])->toBe('North\\South');
});

// The standard RFC 4180 escape — a doubled quote inside a quoted field —
// must still be honoured.
it('unescapes a doubled quote inside a quoted field', function () {
    $path = ($this->writeRaw)("ID,Place\n1,\"Stoke-on-\"\"Trent\"\"\"\n");

    expect(readCsvFile($path)[1][1])->toBe('Stoke-on-"Trent"');
});

// The export is meant to round-trip back through the importer, so the
// writer and reader must agree on escaping. Anything the exporter emits
// must come back byte-identical.
it('keeps values intact through an export/import round trip', function (string $value) {
    $original = ['1', 'Ambleside', $value];

    $path = ($this->tempPath)();
    $out  = fopen($path, 'w');
    writeCsvRowThroughSchema($out, $original);
    fclose($out);

    expect(readCsvFile($path)[0])->toBe($original);
})->with([
    'plain'                  => ['North'],
    'trailing backslash'     => ['ends with backslash\\'],
    'backslash mid-field'    => ['North\\South'],
    'backslash before quote' => ['says \\"hi\\"'],
    'embedded comma'         => ['Ambleside, Cumbria'],
    'embedded quote'         => ['Stoke-on-"Trent"'],
    'embedded newline'       => ["line one\nline two"],
    'empty'                  => [''],
    'only a backslash'       => ['\\'],
]);

// A multi-row export must re-import with every row intact — the failure
// mode of the old escape was losing a row, not mangling a value.
it('re-imports every row of a multi-row export', function () {
    $original = [
        ['1', 'Ambleside', 'ends with backslash\\'],
        ['2', 'Buxton', 'North'],
        ['3', 'Crewe', 'says \\"hi\\"'],
    ];

    $path = ($this->tempPath)();
    $out  = fopen($path, 'w');
    foreach ($original as $row) {
        writeCsvRowThroughSchema($out, $row);
    }
    fclose($out);

    expect(readCsvFile($path))->toBe($original);
});
