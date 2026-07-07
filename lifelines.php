<?php

declare(strict_types=1);

/**
 * Plugin Name: LifeLines
 * Description: An intergroup management plugin built on Unity. Scaffold ready for feature development.
 * Version: 1.0.0
 * Build date: 2026/07/07
 * Requires at least: 6.1
 * Requires PHP: 8.1
 * Requires Plugins: unity
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

/**
 * Get the LifeLines dependency container (Unity's container).
 *
 * @return \Psr\Container\ContainerInterface
 * @throws \RuntimeException If LifeLines is not initialized
 */
function lifelines(): \Psr\Container\ContainerInterface {
    return \LifeLines\Plugin::getContainer();
}

// Initialize the plugin after Unity is loaded
add_action('unity/loaded', function($unityContainer) {
    try {
        if (!class_exists('LifeLines\Plugin')) {
            throw new \Exception('LifeLines\Plugin class not found. Check that Plugin.php exists in the src/ directory.');
        }

        \LifeLines\Plugin::init($unityContainer);

        do_action('lifelines/loaded', \LifeLines\Plugin::getContainer());

    } catch (\Exception $e) {
        function_exists('wp_log')
            ? wp_log('lifelines')->error('LifeLines Plugin Initialization Error: ' . $e->getMessage(), ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()])
            : error_log('LifeLines Plugin Initialization Error: ' . $e->getMessage());

        if (is_admin()) {
            add_action('admin_notices', function() use ($e) {
                $message = sprintf(
                    '<strong>LifeLines Plugin Error:</strong> %s',
                    esc_html($e->getMessage())
                );
                echo '<div class="notice notice-error is-dismissible"><p>' . $message . '</p></div>';
            });
        }

        return;

    } catch (\Throwable $e) {
        function_exists('wp_log')
            ? wp_log('lifelines')->critical('LifeLines Plugin Fatal Error: ' . $e->getMessage(), ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()])
            : error_log('LifeLines Plugin Fatal Error: ' . $e->getMessage());

        if (is_admin()) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p><strong>LifeLines Plugin Fatal Error:</strong> Plugin failed to load. Check error logs.</p></div>';
            });
        }

        return;
    }
}, 10);

// Show admin notice if the Unity plugin is not active
add_action('admin_notices', function() {
    if (!function_exists('unity') && !did_action('unity/loaded')) {
        echo '<div class="notice notice-warning is-dismissible"><p><strong>LifeLines:</strong> This plugin requires the Unity plugin to be installed and activated.</p></div>';
    }
});
