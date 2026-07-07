<?php

declare(strict_types=1);

namespace LifeLines\Lookup;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Front-end surface for the smart lookup: the [lifelines_lookup] shortcode, its
 * assets, and the public AJAX search endpoint.
 *
 * The search endpoint is intentionally nonce-free: it exposes only read-only,
 * non-sensitive public data, and requiring a nonce would break on full-page
 * caching (a cached page would serve a stale nonce to anonymous visitors).
 */
final class LookupController
{
    public const SHORTCODE = 'lifelines_lookup';
    public const ACTION    = 'lifelines_lookup';

    private TownRepository $repository;

    public function __construct(TownRepository $repository)
    {
        $this->repository = $repository;
    }

    public function register(): void
    {
        add_shortcode(self::SHORTCODE, [$this, 'renderShortcode']);
        add_action('wp_enqueue_scripts', [$this, 'registerAssets']);
        add_action('wp_ajax_' . self::ACTION, [$this, 'handleAjax']);
        add_action('wp_ajax_nopriv_' . self::ACTION, [$this, 'handleAjax']);
    }

    public function registerAssets(): void
    {
        wp_register_style(
            'lifelines-lookup',
            LIFELINES_PLUGIN_URL . 'assets/css/lookup.css',
            [],
            LIFELINES_VERSION
        );

        wp_register_script(
            'lifelines-lookup',
            LIFELINES_PLUGIN_URL . 'assets/js/lookup.js',
            [],
            LIFELINES_VERSION,
            true
        );
    }

    /**
     * @param array<string,mixed>|string $atts
     */
    public function renderShortcode($atts = []): string
    {
        $atts = shortcode_atts(
            [
                'placeholder' => __('Start typing a place, postcode or county…', 'lifelines'),
            ],
            is_array($atts) ? $atts : [],
            self::SHORTCODE
        );

        $settings = new LookupSettings();

        $columns = [];
        foreach ($settings->displayColumns() as $column) {
            $columns[] = ['key' => $column, 'label' => Columns::label($column)];
        }

        wp_enqueue_style('lifelines-lookup');
        wp_enqueue_script('lifelines-lookup');
        wp_localize_script('lifelines-lookup', 'LifeLinesLookup', [
            'ajaxUrl'  => admin_url('admin-ajax.php'),
            'action'   => self::ACTION,
            'minChars' => $settings->minChars(),
            'columns'  => $columns,
            'i18n'     => [
                'searching' => __('Searching…', 'lifelines'),
                'noResults' => __('No matches found.', 'lifelines'),
                'error'     => __('Something went wrong. Please try again.', 'lifelines'),
                'typeMore'  => sprintf(
                    /* translators: %d: minimum number of characters */
                    __('Type at least %d characters to search.', 'lifelines'),
                    $settings->minChars()
                ),
            ],
        ]);

        $uid = 'lifelines-lookup-' . wp_unique_id();

        ob_start();
        ?>
        <div class="lifelines-lookup" id="<?php echo esc_attr($uid); ?>">
            <div class="lifelines-lookup__field">
                <label class="screen-reader-text" for="<?php echo esc_attr($uid); ?>-input">
                    <?php esc_html_e('Search', 'lifelines'); ?>
                </label>
                <input
                    type="search"
                    id="<?php echo esc_attr($uid); ?>-input"
                    class="lifelines-lookup__input"
                    placeholder="<?php echo esc_attr($atts['placeholder']); ?>"
                    autocomplete="off"
                    aria-controls="<?php echo esc_attr($uid); ?>-results"
                >
            </div>
            <div
                class="lifelines-lookup__status"
                aria-live="polite"
                data-role="status"
            ></div>
            <div
                class="lifelines-lookup__results"
                id="<?php echo esc_attr($uid); ?>-results"
                data-role="results"
            ></div>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    public function handleAjax(): void
    {
        $term = isset($_GET['q']) ? sanitize_text_field(wp_unslash((string) $_GET['q'])) : '';

        $settings = new LookupSettings();

        if (mb_strlen($term) < $settings->minChars()) {
            wp_send_json_success(['rows' => [], 'columns' => $this->columnMeta($settings)]);
        }

        $rows = $this->repository->search(
            $term,
            $settings->searchColumns(),
            $settings->displayColumns(),
            $settings->resultLimit()
        );

        wp_send_json_success([
            'rows'    => $rows,
            'columns' => $this->columnMeta($settings),
        ]);
    }

    /**
     * @return list<array{key:string,label:string}>
     */
    private function columnMeta(LookupSettings $settings): array
    {
        $meta = [];
        foreach ($settings->displayColumns() as $column) {
            $meta[] = ['key' => $column, 'label' => Columns::label($column)];
        }

        return $meta;
    }
}
