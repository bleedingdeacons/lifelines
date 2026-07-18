<?php

declare(strict_types=1);

namespace LifeLines\Tests\Lookup;

use LifeLines\Lookup\TownSchema;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
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
 *
 * @covers \LifeLines\Lookup\TownSchema
 */
class TownSchemaCsvTest extends TestCase
{
    /** @var string[] */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        $this->tempFiles = [];
        parent::tearDown();
    }

    /**
     * The regression. Written as raw bytes, exactly as a spreadsheet
     * application would emit them, so the parser is what is under test.
     *
     * @test
     */
    public function a_field_ending_in_a_backslash_does_not_swallow_the_next_row(): void
    {
        $path = $this->writeRaw(
            "ID,Place,Notes\n"
            . "1,Ambleside,\"ends with backslash\\\"\n"
            . "2,Buxton,North\n"
        );

        $rows = $this->readAll($path);

        $this->assertCount(3, $rows, 'Header plus both town rows must survive parsing.');
        $this->assertSame(['ID', 'Place', 'Notes'], $rows[0]);
        $this->assertSame('Ambleside', $rows[1][1]);
        $this->assertSame('ends with backslash\\', $rows[1][2]);
        $this->assertSame('Buxton', $rows[2][1], 'The second town must not be swallowed by the first.');
    }

    /**
     * @test
     */
    public function a_backslash_inside_a_field_is_data_not_an_escape(): void
    {
        $path = $this->writeRaw("ID,Place\n1,\"North\\South\"\n");

        $rows = $this->readAll($path);

        $this->assertSame('North\\South', $rows[1][1]);
    }

    /**
     * The standard RFC 4180 escape — a doubled quote inside a quoted field —
     * must still be honoured.
     *
     * @test
     */
    public function a_doubled_quote_inside_a_quoted_field_is_unescaped(): void
    {
        $path = $this->writeRaw("ID,Place\n1,\"Stoke-on-\"\"Trent\"\"\"\n");

        $rows = $this->readAll($path);

        $this->assertSame('Stoke-on-"Trent"', $rows[1][1]);
    }

    /**
     * The export is meant to round-trip back through the importer, so the
     * writer and reader must agree on escaping. Anything the exporter emits
     * must come back byte-identical.
     *
     * @test
     * @dataProvider awkwardValueProvider
     */
    public function values_survive_an_export_import_round_trip(string $value): void
    {
        $original = ['1', 'Ambleside', $value];

        $path = $this->tempPath();
        $out  = fopen($path, 'w');
        $this->writeCsvRow($out, $original);
        fclose($out);

        $rows = $this->readAll($path);

        $this->assertSame($original, $rows[0]);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function awkwardValueProvider(): array
    {
        return [
            'plain'                  => ['North'],
            'trailing backslash'     => ['ends with backslash\\'],
            'backslash mid-field'    => ['North\\South'],
            'backslash before quote' => ['says \\"hi\\"'],
            'embedded comma'         => ['Ambleside, Cumbria'],
            'embedded quote'         => ['Stoke-on-"Trent"'],
            'embedded newline'       => ["line one\nline two"],
            'empty'                  => [''],
            'only a backslash'       => ['\\'],
        ];
    }

    /**
     * A multi-row export must re-import with every row intact — the failure
     * mode of the old escape was losing a row, not mangling a value.
     *
     * @test
     */
    public function every_row_of_a_multi_row_export_re_imports(): void
    {
        $original = [
            ['1', 'Ambleside', 'ends with backslash\\'],
            ['2', 'Buxton', 'North'],
            ['3', 'Crewe', 'says \\"hi\\"'],
        ];

        $path = $this->tempPath();
        $out  = fopen($path, 'w');
        foreach ($original as $row) {
            $this->writeCsvRow($out, $row);
        }
        fclose($out);

        $this->assertSame($original, $this->readAll($path));
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    /**
     * Read a whole file through TownSchema's real reader.
     *
     * @return array<int, array<int, string|null>>
     */
    private function readAll(string $path): array
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
    private function writeCsvRow($handle, array $fields): void
    {
        (new ReflectionMethod(TownSchema::class, 'writeCsvRow'))->invoke(null, $handle, $fields);
    }

    private function writeRaw(string $contents): string
    {
        $path = $this->tempPath();
        file_put_contents($path, $contents);

        return $path;
    }

    private function tempPath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'lifelines_csv_') . '.csv';
        $this->tempFiles[] = $path;

        return $path;
    }
}
