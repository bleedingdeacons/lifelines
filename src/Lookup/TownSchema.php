<?php

declare(strict_types=1);

namespace LifeLines\Lookup;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Owns the custom lookup table: its name, creation, row count, and importing /
 * exporting the data as CSV.
 *
 * The table name is derived solely from $wpdb->prefix plus a literal suffix, so
 * it is always a trusted identifier. Column identifiers only ever come from the
 * Columns whitelist.
 */
final class TownSchema
{
    private const TABLE_SUFFIX = 'life_lines';

    /** Columns stored as numbers rather than quoted strings. */
    private const NUMERIC = ['ID', 'Latitude', 'Longitude', 'Easting', 'Northing'];

    public static function tableName(): string
    {
        global $wpdb;

        return $wpdb->prefix . self::TABLE_SUFFIX;
    }

    /**
     * Create (or migrate) the lookup table. This is the single source of truth
     * for the schema — imported CSVs carry data only. Uses InnoDB/utf8mb4, and
     * every column except the ID primary key is nullable so a CSV that omits a
     * column (or leaves cells blank) imports cleanly.
     */
    public static function install(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table           = self::tableName();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table (
  ID int NOT NULL,
  Place varchar(255) DEFAULT NULL,
  AA_Region varchar(50) DEFAULT NULL,
  Service_Name varchar(100) DEFAULT NULL,
  Number varchar(50) DEFAULT NULL,
  Open_Times varchar(25) DEFAULT NULL,
  County varchar(255) DEFAULT NULL,
  Country varchar(255) DEFAULT NULL,
  Postcode varchar(10) DEFAULT NULL,
  AreaCode varchar(10) DEFAULT NULL,
  GridRef varchar(10) DEFAULT NULL,
  Latitude decimal(10,5) DEFAULT NULL,
  Longitude decimal(10,5) DEFAULT NULL,
  Easting int DEFAULT NULL,
  Northing int DEFAULT NULL,
  Region varchar(255) DEFAULT NULL,
  Category varchar(255) DEFAULT NULL,
  PRIMARY KEY  (ID),
  KEY Place (Place),
  KEY Postcode (Postcode)
) $charset_collate;";

        dbDelta($sql);
    }

    public static function exists(): bool
    {
        global $wpdb;

        $table = self::tableName();
        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));

        return $found === $table;
    }

    public static function count(): int
    {
        global $wpdb;

        if (!self::exists()) {
            return 0;
        }

        // Table name is a trusted identifier (prefix + literal).
        return (int) $wpdb->get_var('SELECT COUNT(*) FROM `' . self::tableName() . '`');
    }

    /**
     * Import a CSV file into the prefixed table, replacing any existing rows.
     *
     * The first row must be the column names (matched against the Columns
     * whitelist — unknown headers are ignored). Fields are parsed with fgetcsv,
     * so embedded commas / quotes inside quoted values are handled correctly.
     * If there is no ID column (or a blank ID cell) a sequential ID is assigned.
     * Rows are inserted in batches for speed; values are escaped via esc_sql.
     *
     * @return array{ok:bool,inserted:int,errors:int,message:string}
     */
    public static function import(string $file): array
    {
        global $wpdb;

        if (!is_readable($file)) {
            return ['ok' => false, 'inserted' => 0, 'errors' => 0, 'message' => 'Data file not found: ' . $file];
        }

        $handle = fopen($file, 'r');
        if ($handle === false) {
            return ['ok' => false, 'inserted' => 0, 'errors' => 0, 'message' => 'Could not open the file for reading.'];
        }

        $header = fgetcsv($handle);
        if (!is_array($header)) {
            fclose($handle);
            return ['ok' => false, 'inserted' => 0, 'errors' => 0, 'message' => 'The CSV file is empty.'];
        }

        // Map recognised column name -> its position in the CSV.
        $index = [];
        foreach ($header as $i => $name) {
            $name = ltrim(trim((string) $name), "\xEF\xBB\xBF"); // trim + strip UTF-8 BOM
            if (Columns::isValid($name)) {
                $index[$name] = $i;
            }
        }
        if ($index === []) {
            fclose($handle);
            return ['ok' => false, 'inserted' => 0, 'errors' => 0, 'message' => 'No recognised column headers were found. The first row must be the column names.'];
        }

        self::install();
        $table = self::tableName();

        $hasId       = isset($index['ID']);
        $insertCols  = array_keys($index);
        if (!$hasId) {
            array_unshift($insertCols, 'ID');
        }
        $colList = implode(', ', array_map(static fn (string $c): string => "`$c`", $insertCols));

        $wpdb->query('TRUNCATE TABLE `' . $table . '`');

        $inserted = 0;
        $errors   = 0;
        $autoId   = 0;
        $batch    = [];

        $flush = static function () use (&$batch, &$inserted, &$errors, $wpdb, $table, $colList): void {
            if ($batch === []) {
                return;
            }
            $sql = "INSERT INTO `$table` ($colList) VALUES " . implode(',', $batch);
            $res = $wpdb->query($sql);
            if ($res === false) {
                $errors++;
            } else {
                $inserted += (int) $res;
            }
            $batch = [];
        };

        while (($row = fgetcsv($handle)) !== false) {
            if (!is_array($row) || $row === [null]) {
                continue; // blank line
            }

            $id = $hasId ? trim((string) ($row[$index['ID']] ?? '')) : '';
            if ($id === '' || !ctype_digit($id)) {
                $id = (string) (++$autoId);
            } else {
                $autoId = max($autoId, (int) $id);
            }

            $vals = [];
            foreach ($insertCols as $c) {
                if ($c === 'ID') {
                    $vals[] = (int) $id;
                    continue;
                }
                $raw = isset($index[$c]) ? trim((string) ($row[$index[$c]] ?? '')) : '';
                if ($raw === '') {
                    $vals[] = 'NULL';
                } elseif (in_array($c, self::NUMERIC, true)) {
                    $vals[] = is_numeric($raw) ? $raw : 'NULL';
                } else {
                    $vals[] = "'" . esc_sql($raw) . "'";
                }
            }

            $batch[] = '(' . implode(',', $vals) . ')';
            if (count($batch) >= 500) {
                $flush();
            }
        }
        $flush();
        fclose($handle);

        return [
            'ok'       => $errors === 0 && $inserted > 0,
            'inserted' => $inserted,
            'errors'   => $errors,
            'message'  => sprintf('Imported %d rows%s.', $inserted, $errors > 0 ? " ({$errors} batch error(s))" : ''),
        ];
    }

    /**
     * Stream the whole table to the browser as a CSV download and exit.
     *
     * The header row is the column names; values are written with fputcsv, which
     * quotes any field containing a comma, quote or newline. Rows are streamed in
     * chunks so a large table doesn't have to be held in memory at once.
     */
    public static function exportCsv(): void
    {
        global $wpdb;

        $table   = self::tableName();
        $columns = Columns::keys();
        $colList = implode(', ', array_map(static fn (string $c): string => "`$c`", $columns));

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="life_lines-' . gmdate('Ymd') . '.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, $columns);

        $offset = 0;
        $chunk  = 2000;
        do {
            $rows = $wpdb->get_results(
                "SELECT $colList FROM `$table` ORDER BY `ID` LIMIT $chunk OFFSET $offset",
                ARRAY_A
            );
            foreach ((array) $rows as $row) {
                $line = [];
                foreach ($columns as $c) {
                    $line[] = $row[$c] ?? '';
                }
                fputcsv($out, $line);
            }
            $offset += $chunk;
        } while (is_array($rows) && count($rows) === $chunk);

        fclose($out);
        exit;
    }
}
