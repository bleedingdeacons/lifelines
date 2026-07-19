<?php

declare(strict_types=1);

/**
 * Plugin Name: LifeLines
 * Description: A standalone real-time lookup tool for UK place, service and helpline data, with admin-configurable searchable and displayed columns.
 * Version: 1.2.7
 * Build date: 2026/07/07
 * Requires at least: 6.1
 * Requires PHP: 8.1
 * GitHub Plugin URI: https://github.com/thebleedingdeacons/lifelines
 * GitHub Branch: main
 * Author: The Bleeding Deacons
 * Author URI: https://github.com/bleedingdeacons/lifelines
 * Text Domain: lifelines
 * Contact: thebleedingdeacons@gmail.com
 * License: MIT (Modified)
 */

if (!defined('ABSPATH')) {
    exit;
}

// Kill switch — set define('LIFELINES_KILL', true) in wp-config.php to stand the
// plugin down without deactivating it.
if (defined('LIFELINES_KILL') && LIFELINES_KILL) {
    return;
}

// Define plugin constants
if (!function_exists('get_plugin_data')) {
    if (file_exists(ABSPATH . 'wp-admin/includes/plugin.php')) {
        require_once(ABSPATH . 'wp-admin/includes/plugin.php');
    }
}

$lifelines_plugin_data = get_plugin_data(__FILE__, false, false);
define('LIFELINES_VERSION', $lifelines_plugin_data['Version']);
define('LIFELINES_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LIFELINES_PLUGIN_URL', plugin_dir_url(__FILE__));

// Autoloader for LifeLines namespace
spl_autoload_register(function ($class) {
    try {
        $prefix = 'LifeLines\\';
        $base_dir = LIFELINES_PLUGIN_DIR . 'src/';

        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }

        $relative_class = substr($class, $len);
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

        if (file_exists($file)) {
            require $file;
        }
    } catch (\Exception $e) {
        function_exists('wp_log')
            ? wp_log('lifelines')->error('LifeLines Autoloader Error: ' . $e->getMessage(), ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()])
            : error_log('LifeLines Autoloader Error: ' . $e->getMessage());
    } catch (\Throwable $e) {
        function_exists('wp_log')
            ? wp_log('lifelines')->critical('LifeLines Autoloader Fatal Error: ' . $e->getMessage(), ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()])
            : error_log('LifeLines Autoloader Fatal Error: ' . $e->getMessage());
    }
});

// -----------------------------------------------------------------------------
// Smart Lookup subsystem
//
// Self-contained public lookup tool (custom table + shortcode + admin settings).
// LifeLines has no plugin dependencies: it registers on core WordPress hooks.
// -----------------------------------------------------------------------------
add_action('plugins_loaded', function () {
    if (class_exists(\LifeLines\Lookup\LookupBootstrap::class)) {
        (new \LifeLines\Lookup\LookupBootstrap())->register();
    }
});

register_activation_hook(__FILE__, [\LifeLines\Lookup\LookupBootstrap::class, 'activate']);
