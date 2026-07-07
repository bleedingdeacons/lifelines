<?php

declare(strict_types=1);

namespace LifeLines\Lookup;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * [lifelines_find_button] — a call-to-action button (for the home page, etc.)
 * that links to the lookup/find page.
 *
 * Attributes:
 *   label — button text (default "Find").
 *   page  — target page: a page ID, a page path/slug, or a full URL. Defaults to
 *           the auto-created Lookup page tracked by LookupBootstrap.
 */
final class FindButtonShortcode
{
    public const SHORTCODE = 'lifelines_find_button';

    public function register(): void
    {
        add_shortcode(self::SHORTCODE, [$this, 'render']);
    }

    /**
     * @param array<string,mixed>|string $atts
     */
    public function render($atts = []): string
    {
        $atts = shortcode_atts(
            [
                'label' => __('Find', 'lifelines'),
                'page'  => '',
            ],
            is_array($atts) ? $atts : [],
            self::SHORTCODE
        );

        $url = $this->resolveUrl((string) $atts['page']);
        if ($url === '') {
            return '';
        }

        // Reuse the lookup stylesheet (registered by LookupController on
        // wp_enqueue_scripts) for the button styling.
        wp_enqueue_style('lifelines-lookup');

        return sprintf(
            '<a class="lifelines-find-button" href="%s">%s</a>',
            esc_url($url),
            esc_html((string) $atts['label'])
        );
    }

    private function resolveUrl(string $page): string
    {
        if ($page !== '') {
            if (ctype_digit($page)) {
                $url = get_permalink((int) $page);

                return $url !== false ? $url : '';
            }

            if (filter_var($page, FILTER_VALIDATE_URL) !== false) {
                return $page;
            }

            $post = get_page_by_path($page);
            if ($post instanceof \WP_Post) {
                $url = get_permalink($post);

                return $url !== false ? $url : '';
            }

            return '';
        }

        $pageId = (int) get_option(LookupBootstrap::PAGE_OPTION, 0);
        if ($pageId > 0) {
            $url = get_permalink($pageId);

            return $url !== false ? $url : '';
        }

        return '';
    }
}
