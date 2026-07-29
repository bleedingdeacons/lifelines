<?php

declare(strict_types=1);

// PHPUnit bootstrap.
//
// LifeLines is standalone — it has no Unity-ecosystem dependencies — so the
// suite needs nothing but the plugin's own autoloader plus the small amount of
// WordPress surface its classes touch at load time.

require_once dirname(__DIR__) . '/vendor/autoload.php';

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

if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A'); // $wpdb->get_results() output-type flag
}
if (!defined('LIFELINES_PLUGIN_URL')) {
    define('LIFELINES_PLUGIN_URL', 'http://example.test/wp-content/plugins/lifelines/');
}
if (!defined('LIFELINES_VERSION')) {
    define('LIFELINES_VERSION', '9.9.9-test');
}

// ── Minimal WordPress surface ────────────────────────────────────────────
//
// LifeLines is standalone, so these are hand-rolled stubs (no WP_Mock): just
// enough of the handful of core functions its classes call, backed by
// process-global state the tests set up and assert against. Not faithful
// implementations — see each note.

/** Signals a wp_send_json_success() call so a test can inspect the payload. */
class LifeLinesJsonResponse extends \RuntimeException
{
    /** @param array<string,mixed> $data */
    public function __construct(public array $data)
    {
        parent::__construct('wp_send_json_success');
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        public function __construct(public string $code = '', public string $message = '')
        {
        }
    }
}

/**
 * Stand-in for $wpdb. Reads/writes are driven by $GLOBALS the tests set:
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

if (!function_exists('dbDelta')) {
    function dbDelta(string $sql): array
    {
        return [];
    }
}

if (!function_exists('get_option')) {
    function get_option(string $key, mixed $default = false): mixed
    {
        return $GLOBALS['lifelines_test_options'][$key] ?? $default;
    }
}
if (!function_exists('update_option')) {
    function update_option(string $key, mixed $value): bool
    {
        $GLOBALS['lifelines_test_options'][$key] = $value;
        return true;
    }
}
if (!function_exists('wp_parse_args')) {
    /**
     * @param array<string,mixed> $args
     * @param array<string,mixed> $defaults
     * @return array<string,mixed>
     */
    function wp_parse_args(array $args, array $defaults = []): array
    {
        return array_merge($defaults, $args);
    }
}
if (!function_exists('esc_sql')) {
    function esc_sql(string $value): string
    {
        return addslashes($value);
    }
}
if (!function_exists('__')) {
    function __(string $text, string $domain = 'default'): string
    {
        return $text;
    }
}
if (!function_exists('esc_html__')) {
    function esc_html__(string $text, string $domain = 'default'): string
    {
        return $text;
    }
}
if (!function_exists('esc_html')) {
    function esc_html(string $text): string
    {
        return $text;
    }
}
if (!function_exists('esc_attr')) {
    function esc_attr(string $text): string
    {
        return $text;
    }
}
if (!function_exists('esc_url')) {
    function esc_url(string $url): string
    {
        return $url;
    }
}
if (!function_exists('esc_html_e')) {
    function esc_html_e(string $text, string $domain = 'default'): void
    {
        echo $text;
    }
}
if (!function_exists('add_action')) {
    function add_action(string $hook, callable $cb, int $priority = 10, int $args = 1): bool
    {
        return true;
    }
}
if (!function_exists('add_shortcode')) {
    function add_shortcode(string $tag, callable $cb): void
    {
    }
}
if (!function_exists('wp_register_style')) {
    function wp_register_style(string $handle, string $src, array $deps = [], mixed $ver = false): bool
    {
        return true;
    }
}
if (!function_exists('wp_register_script')) {
    function wp_register_script(string $handle, string $src, array $deps = [], mixed $ver = false, bool $footer = false): bool
    {
        return true;
    }
}
if (!function_exists('wp_enqueue_style')) {
    function wp_enqueue_style(string $handle, string $src = '', array $deps = [], mixed $ver = false): void
    {
    }
}
if (!function_exists('wp_enqueue_script')) {
    function wp_enqueue_script(string $handle, string $src = '', array $deps = [], mixed $ver = false, bool $footer = false): void
    {
    }
}
if (!function_exists('wp_localize_script')) {
    function wp_localize_script(string $handle, string $name, array $data): bool
    {
        return true;
    }
}
if (!function_exists('wp_unique_id')) {
    function wp_unique_id(string $prefix = ''): string
    {
        static $n = 0;
        return $prefix . (++$n);
    }
}
if (!function_exists('admin_url')) {
    function admin_url(string $path = ''): string
    {
        return 'http://example.test/wp-admin/' . $path;
    }
}
if (!function_exists('shortcode_atts')) {
    /**
     * @param array<string,mixed> $defaults
     * @param array<string,mixed> $atts
     * @return array<string,mixed>
     */
    function shortcode_atts(array $defaults, array $atts, string $shortcode = ''): array
    {
        return array_merge($defaults, array_intersect_key($atts, $defaults));
    }
}
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $str): string
    {
        return trim(strip_tags($str));
    }
}
if (!function_exists('wp_unslash')) {
    function wp_unslash(string $value): string
    {
        return stripslashes($value);
    }
}
if (!function_exists('wp_send_json_success')) {
    /**
     * @param array<string,mixed> $data
     */
    function wp_send_json_success(array $data = []): void
    {
        // Real WP echoes JSON and exits; throw instead so a test can catch the
        // payload without terminating the process.
        throw new LifeLinesJsonResponse($data);
    }
}
if (!function_exists('is_admin')) {
    function is_admin(): bool
    {
        return $GLOBALS['lifelines_test_is_admin'] ?? false;
    }
}
if (!function_exists('wp_insert_post')) {
    /**
     * @param array<string,mixed> $post
     */
    function wp_insert_post(array $post): mixed
    {
        return $GLOBALS['lifelines_test_insert_id'] ?? 123;
    }
}
if (!function_exists('is_wp_error')) {
    function is_wp_error(mixed $thing): bool
    {
        return $thing instanceof WP_Error;
    }
}
if (!function_exists('get_post_status')) {
    function get_post_status(int $id): string|false
    {
        return $GLOBALS['lifelines_test_post_status'] ?? false;
    }
}
if (!function_exists('get_permalink')) {
    function get_permalink(int $id): string|false
    {
        return $GLOBALS['lifelines_test_permalink'] ?? 'http://example.test/lookup/';
    }
}
if (!function_exists('nocache_headers')) {
    function nocache_headers(): void
    {
    }
}

$GLOBALS['wpdb'] = new FakeWpdb();
