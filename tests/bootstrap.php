<?php

declare(strict_types=1);

// PHPUnit bootstrap.
//
// LifeLines is standalone — it has no Unity-ecosystem dependencies — but the
// WordPress surface its classes touch is the same WordPress every other plugin
// here touches, so it comes from bleedingdeacons/wp-mocks rather than being
// hand-rolled: options, escaping and i18n, the asset and shortcode registry,
// wp_send_json_success(), wp_insert_post() and friends.
//
// wp-mocks' bootstrap loads Patchwork before anything patchable, so anything
// below that defines WordPress functions or classes of its own must stay after
// the Bootstrap::load() call, not before it.
//
// Only the `wordpress` group is loaded. LifeLines has no REST surface, uses no
// ACF, and does not depend on Sentinel's logger.

use BleedingDeacons\WpMocks\Bootstrap;
use BleedingDeacons\WpMocks\WpState;

require_once dirname(__DIR__) . '/vendor/autoload.php';

Bootstrap::load(['wordpress']);

// Makes plugins_url()/plugin_dir_url() answer with LifeLines' own path.
WpState::$pluginSlug = 'lifelines';

// Every LifeLines class begins with `if (!defined('ABSPATH')) { exit; }` to
// block direct web access, so the constant has to exist before any of them is
// loaded or the file simply exits and the class is never declared.
if (!defined('ABSPATH')) {
    define('ABSPATH', sys_get_temp_dir() . '/lifelines-test-wp/');
}

// TownSchema::install() does `require_once ABSPATH . 'wp-admin/includes/upgrade.php'`
// then calls dbDelta(). Provide a real (empty) file at that path so the require
// succeeds, and a dbDelta() no-op below, so import() can be exercised end to end.
$lifelinesUpgradeDir = ABSPATH . 'wp-admin/includes/';
if (!is_dir($lifelinesUpgradeDir)) {
    mkdir($lifelinesUpgradeDir, 0777, true);
}
if (!file_exists($lifelinesUpgradeDir . 'upgrade.php')) {
    file_put_contents($lifelinesUpgradeDir . 'upgrade.php', "<?php\n");
}

if (!defined('LIFELINES_PLUGIN_URL')) {
    define('LIFELINES_PLUGIN_URL', 'http://example.test/wp-content/plugins/lifelines/');
}
if (!defined('LIFELINES_VERSION')) {
    define('LIFELINES_VERSION', '9.9.9-test');
}

// dbDelta() is not part of the shared stubs: it lives in wp-admin/includes and
// is only reachable after the require above, which is a plugin-specific path.
if (!function_exists('dbDelta')) {
    function dbDelta(string $sql): array
    {
        return [];
    }
}

/**
 * Stand-in for $wpdb.
 *
 * Kept local rather than using wp-mocks' Doubles\FakeWpdb, which answers every
 * get_var() with one queued scalar and every get_results() with one queued set
 * of rows. LifeLines needs more than that at the same time:
 *
 *  - get_var() has to answer "SHOW TABLES LIKE" and "COUNT(*)" differently
 *    within a single test, since TownRepository probes for the table and then
 *    counts rows in it;
 *  - get_results() has to be a *queue*, so exportCsv()'s chunked loop can be
 *    handed a full chunk and then a simulated read failure to unwind it before
 *    its terminal exit();
 *  - query() has to report the number of value-tuples in an INSERT, which is
 *    how import() totals the rows it wrote.
 *
 * Reads and writes are driven by $GLOBALS the tests set:
 *  - lifelines_test_rows       -> get_results() payload
 *  - lifelines_test_table_ok   -> whether the table "exists"
 *  - lifelines_test_count      -> COUNT(*) result
 *  - lifelines_test_query_fail -> force query() to report failure (false)
 * Every SQL string passed in is recorded on $queries for assertions.
 */
class FakeWpdb
{
    public string $prefix = 'wp_';
    /** @var list<string> */
    public array $queries = [];

    public function esc_like(string $text): string
    {
        return addcslashes($text, '_%\\');
    }

    /**
     * @param array<int,mixed>|mixed $args
     */
    public function prepare(string $query, $args = [], mixed ...$rest): string
    {
        // The production code only feeds trusted, already-escaped values here;
        // the tests don't assert on the interpolation, so returning the query
        // with placeholders intact is sufficient.
        return $query;
    }

    /**
     * @return list<array<string,string|null>>|null
     */
    public function get_results(string $query, mixed $output = null): ?array
    {
        $this->queries[] = $query;

        // A queue lets a test script successive reads — e.g. exportCsv()'s
        // chunked loop: return a full chunk, then throw on the next read to
        // unwind out of the method before its terminal exit().
        if (isset($GLOBALS['lifelines_test_results_queue']) && $GLOBALS['lifelines_test_results_queue'] !== []) {
            $next = array_shift($GLOBALS['lifelines_test_results_queue']);
            if ($next === '__throw__') {
                throw new \RuntimeException('simulated read failure');
            }
            return $next;
        }

        return $GLOBALS['lifelines_test_rows'] ?? [];
    }

    public function get_var(string $query): ?string
    {
        $this->queries[] = $query;
        if (stripos($query, 'SHOW TABLES') !== false) {
            return ($GLOBALS['lifelines_test_table_ok'] ?? false) ? $this->prefix . 'life_lines' : null;
        }
        if (stripos($query, 'COUNT(*)') !== false) {
            return (string) ($GLOBALS['lifelines_test_count'] ?? 0);
        }
        return null;
    }

    public function get_charset_collate(): string
    {
        return 'DEFAULT CHARSET=utf8mb4';
    }

    /**
     * Returns the number of value-tuples for an INSERT (so import() can total
     * its inserted rows), true for other statements, or false when a test has
     * asked for a failure.
     */
    public function query(string $query): int|bool
    {
        $this->queries[] = $query;
        if (($GLOBALS['lifelines_test_query_fail'] ?? false) && stripos($query, 'INSERT') === 0) {
            return false;
        }
        if (stripos($query, 'INSERT') === 0) {
            return substr_count($query, '),(') + 1;
        }
        return true;
    }
}

$GLOBALS['wpdb'] = new FakeWpdb();
