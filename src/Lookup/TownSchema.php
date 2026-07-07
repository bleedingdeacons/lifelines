<?php

declare(strict_types=1);

namespace LifeLines\Lookup;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Owns the custom lookup table: its name, creation, row count, and importing the
 * bundled uk.sql dump into it.
 *
 * The table name is derived solely from $wpdb->prefix plus a literal suffix, so
 * it is always a trusted identifier.
 */
final class TownSchema
{
    private const TABLE_SUFFIX = 'life_lines';

    public static function tableName(): string
    {
        global $wpdb;

        return $wpdb->prefix . self::TABLE_SUFFIX;
    }

    /**
     * Create (or migrate) the lookup table. This is the single source of truth
     * for the schema — uploaded dumps carry data only, no CREATE TABLE. Uses
     * InnoDB/utf8mb4.
     */
    public static function install(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table           = self::tableName();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table (
  ID int NOT NULL,
  Place varchar(255) NOT NULL DEFAULT '',
  AA_Region varchar(50) DEFAULT NULL,
  Service_Name varchar(100) DEFAULT NULL,
  Number varchar(50) DEFAULT NULL,
  Open_Times varchar(25) DEFAULT NULL,
  County varchar(255) NOT NULL DEFAULT '',
  Country varchar(255) NOT NULL DEFAULT '',
  Postcode varchar(10) DEFAULT NULL,
  AreaCode varchar(10) NOT NULL DEFAULT '',
  GridRef varchar(10) NOT NULL DEFAULT '',
  Latitude decimal(10,5) DEFAULT NULL,
  Longitude decimal(10,5) DEFAULT NULL,
  Easting int NOT NULL DEFAULT 0,
  Northing int NOT NULL DEFAULT 0,
  Region varchar(255) NOT NULL DEFAULT '',
  Category varchar(255) NOT NULL DEFAULT '',
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
     * Import a life_lines mysqldump into the prefixed table, replacing any
     * existing rows.
     *
     * Only lines that are `INSERT INTO `life_lines` VALUES ...` statements are
     * executed (with the source table name rewritten to the prefixed table);
     * every other statement in the file is ignored, so an uploaded dump cannot
     * run arbitrary SQL such as DROP/DELETE against other tables.
     *
     * @return array{ok:bool,inserted:int,errors:int,message:string}
     */
    public static function import(string $file): array
    {
        global $wpdb;

        if (!is_readable($file)) {
            return ['ok' => false, 'inserted' => 0, 'errors' => 0, 'message' => 'Data file not found: ' . $file];
        }

        self::install();

        $table   = self::tableName();
        $source  = 'INSERT INTO `life_lines` VALUES';
        $target  = "INSERT INTO `{$table}` VALUES";
        $prefix  = 'INSERT INTO `life_lines`';

        // Start from an empty table so re-imports don't collide on the PRIMARY KEY.
        $wpdb->query('TRUNCATE TABLE `' . $table . '`');

        $handle = fopen($file, 'r');
        if ($handle === false) {
            return ['ok' => false, 'inserted' => 0, 'errors' => 0, 'message' => 'Could not open data file for reading.'];
        }

        $inserted = 0;
        $errors   = 0;

        // Statements can be multi-megabyte single lines; fgets handles that.
        while (($line = fgets($handle)) !== false) {
            if (strncmp($line, $prefix, strlen($prefix)) !== 0) {
                continue;
            }

            $statement = str_replace($source, $target, rtrim(rtrim($line), ';'));

            $result = $wpdb->query($statement);
            if ($result === false) {
                $errors++;
            } else {
                $inserted += (int) $result;
            }
        }

        fclose($handle);

        return [
            'ok'       => $errors === 0 && $inserted > 0,
            'inserted' => $inserted,
            'errors'   => $errors,
            'message'  => sprintf('Imported %d rows%s.', $inserted, $errors > 0 ? " ({$errors} statement error(s))" : ''),
        ];
    }
}
