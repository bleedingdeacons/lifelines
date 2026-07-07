<?php

declare(strict_types=1);

namespace LifeLines\Lookup;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Wires the smart-lookup subsystem into WordPress.
 *
 * Deliberately independent of Unity: the lookup is a self-contained public tool,
 * so it registers on core WordPress hooks and works whether or not the Unity
 * plugin is active.
 */
final class LookupBootstrap
{
    /** wp_options key holding the auto-created public page ID. */
    public const PAGE_OPTION = 'lifelines_lookup_page_id';

    public function register(): void
    {
        $controller = new LookupController(new TownRepository());
        $controller->register();

        if (is_admin()) {
            (new SettingsPage())->register();
        }
    }

    /**
     * Activation: create the table, import the bundled data if the table is
     * empty, and ensure a public lookup page exists.
     */
    public static function activate(): void
    {
        TownSchema::install();

        if (TownSchema::count() === 0 && is_readable(TownSchema::dumpPath())) {
            TownSchema::import();
        }

        self::ensureLookupPage();
    }

    /**
     * Create a published "Lookup" page containing the shortcode, once.
     */
    private static function ensureLookupPage(): void
    {
        $existing = (int) get_option(self::PAGE_OPTION, 0);
        if ($existing > 0 && get_post_status($existing) === 'publish') {
            return;
        }

        $pageId = wp_insert_post([
            'post_title'   => __('Lookup', 'lifelines'),
            'post_name'    => 'lookup',
            'post_content' => '[' . LookupController::SHORTCODE . ']',
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ]);

        if (!is_wp_error($pageId) && $pageId > 0) {
            update_option(self::PAGE_OPTION, (int) $pageId);
        }
    }
}
