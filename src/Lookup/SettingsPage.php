<?php

declare(strict_types=1);

namespace LifeLines\Lookup;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin settings screen: configure searchable/display columns and result limits,
 * and (re-)import the dataset.
 */
final class SettingsPage
{
    public const MENU_SLUG   = 'lifelines-lookup';
    public const CAPABILITY  = 'manage_options';
    private const SAVE_ACTION = 'lifelines_lookup_save';
    private const IMPORT_ACTION = 'lifelines_lookup_import';

    /** @var string|null Notice to show after a POST action. */
    private ?string $notice = null;
    private string $noticeType = 'success';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerMenu']);
    }

    public function registerMenu(): void
    {
        add_menu_page(
            __('LifeLines Lookup', 'lifelines'),
            __('LifeLines', 'lifelines'),
            self::CAPABILITY,
            self::MENU_SLUG,
            [$this, 'render'],
            'dashicons-search',
            58
        );
    }

    public function render(): void
    {
        if (!current_user_can(self::CAPABILITY)) {
            return;
        }

        $this->maybeHandlePost();

        $settings       = new LookupSettings();
        $searchColumns  = $settings->searchColumns();
        $displayColumns = $settings->displayColumns();
        $rowCount       = TownSchema::count();
        $lookupPageUrl  = $this->lookupPageUrl();
        $maxUpload      = size_format(wp_max_upload_size());

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('LifeLines Smart Lookup', 'lifelines'); ?></h1>

            <?php if ($this->notice !== null) : ?>
                <div class="notice notice-<?php echo esc_attr($this->noticeType); ?> is-dismissible">
                    <p><?php echo esc_html($this->notice); ?></p>
                </div>
            <?php endif; ?>

            <h2><?php esc_html_e('Dataset', 'lifelines'); ?></h2>
            <p>
                <?php
                printf(
                    /* translators: %s: number of rows */
                    esc_html__('The lookup table currently holds %s rows.', 'lifelines'),
                    '<strong>' . esc_html(number_format_i18n($rowCount)) . '</strong>'
                );
                ?>
            </p>
            <?php if ($lookupPageUrl !== null) : ?>
                <p>
                    <?php esc_html_e('Public lookup page:', 'lifelines'); ?>
                    <a href="<?php echo esc_url($lookupPageUrl); ?>" target="_blank" rel="noopener">
                        <?php echo esc_html($lookupPageUrl); ?>
                    </a>
                </p>
            <?php endif; ?>
            <p>
                <?php esc_html_e('Or place this shortcode on any page:', 'lifelines'); ?>
                <code>[<?php echo esc_html(LookupController::SHORTCODE); ?>]</code>
            </p>

            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field(self::IMPORT_ACTION); ?>
                <input type="hidden" name="lifelines_action" value="<?php echo esc_attr(self::IMPORT_ACTION); ?>">
                <p>
                    <label for="lifelines-sql-file">
                        <strong><?php esc_html_e('Import data from a .sql file', 'lifelines'); ?></strong>
                    </label>
                </p>
                <p>
                    <input type="file" id="lifelines-sql-file" name="sql_file" accept=".sql" required>
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e('Upload &amp; import', 'lifelines'); ?>
                    </button>
                </p>
                <p class="description">
                    <?php
                    printf(
                        /* translators: %s: maximum upload size, e.g. 64 MB */
                        esc_html__('The data replaces the current rows, then the uploaded file is deleted. Maximum upload size: %s.', 'lifelines'),
                        esc_html($maxUpload)
                    );
                    ?>
                </p>
            </form>

            <hr>

            <form method="post">
                <?php wp_nonce_field(self::SAVE_ACTION); ?>
                <input type="hidden" name="lifelines_action" value="<?php echo esc_attr(self::SAVE_ACTION); ?>">

                <h2><?php esc_html_e('Searchable columns', 'lifelines'); ?></h2>
                <p class="description">
                    <?php esc_html_e('Typed text is partial-matched against every column you tick here.', 'lifelines'); ?>
                </p>
                <?php $this->renderColumnChecklist('search_columns', $searchColumns); ?>

                <h2><?php esc_html_e('Displayed columns', 'lifelines'); ?></h2>
                <p class="description">
                    <?php esc_html_e('Columns shown (in this order) in the results table.', 'lifelines'); ?>
                </p>
                <?php $this->renderColumnChecklist('display_columns', $displayColumns); ?>

                <h2><?php esc_html_e('Behaviour', 'lifelines'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="lifelines-result-limit"><?php esc_html_e('Maximum results', 'lifelines'); ?></label>
                        </th>
                        <td>
                            <input
                                type="number" id="lifelines-result-limit" name="result_limit"
                                min="1" max="<?php echo esc_attr((string) LookupSettings::MAX_RESULT_LIMIT); ?>"
                                value="<?php echo esc_attr((string) $settings->resultLimit()); ?>" class="small-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="lifelines-min-chars"><?php esc_html_e('Minimum characters', 'lifelines'); ?></label>
                        </th>
                        <td>
                            <input
                                type="number" id="lifelines-min-chars" name="min_chars"
                                min="1" max="10"
                                value="<?php echo esc_attr((string) $settings->minChars()); ?>" class="small-text">
                            <p class="description">
                                <?php esc_html_e('Search begins once this many characters are typed.', 'lifelines'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Save settings', 'lifelines')); ?>
            </form>
        </div>
        <?php
    }

    /**
     * @param list<string> $selected
     */
    private function renderColumnChecklist(string $name, array $selected): void
    {
        echo '<fieldset><ul class="lifelines-column-list" style="column-width:220px;">';
        foreach (Columns::ALL as $key => $label) {
            $checked = in_array($key, $selected, true);
            printf(
                '<li><label><input type="checkbox" name="%1$s[]" value="%2$s" %3$s> %4$s <code>%2$s</code></label></li>',
                esc_attr($name),
                esc_attr($key),
                checked($checked, true, false),
                esc_html($label)
            );
        }
        echo '</ul></fieldset>';
    }

    private function maybeHandlePost(): void
    {
        $action = isset($_POST['lifelines_action']) ? sanitize_key(wp_unslash((string) $_POST['lifelines_action'])) : '';

        if ($action === self::SAVE_ACTION) {
            check_admin_referer(self::SAVE_ACTION);

            LookupSettings::save([
                'search_columns'  => array_map('sanitize_text_field', (array) ($_POST['search_columns'] ?? [])),
                'display_columns' => array_map('sanitize_text_field', (array) ($_POST['display_columns'] ?? [])),
                'result_limit'    => (int) ($_POST['result_limit'] ?? 50),
                'min_chars'       => (int) ($_POST['min_chars'] ?? 2),
            ]);

            $this->notice = __('Settings saved.', 'lifelines');
            $this->noticeType = 'success';

            return;
        }

        if ($action === self::IMPORT_ACTION) {
            check_admin_referer(self::IMPORT_ACTION);

            $result = $this->handleUploadAndImport();

            $this->notice = $result['message'];
            $this->noticeType = $result['ok'] ? 'success' : 'error';
        }
    }

    /**
     * Validate the uploaded .sql file, import it, and delete it.
     *
     * @return array{ok:bool,message:string}
     */
    private function handleUploadAndImport(): array
    {
        if (empty($_FILES['sql_file']) || !is_array($_FILES['sql_file'])) {
            return ['ok' => false, 'message' => __('No file was uploaded.', 'lifelines')];
        }

        $file = $_FILES['sql_file'];
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'message' => $this->uploadErrorMessage($error)];
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            return ['ok' => false, 'message' => __('Upload failed: the file could not be read.', 'lifelines')];
        }

        $originalName = sanitize_file_name((string) ($file['name'] ?? ''));
        if (strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION)) !== 'sql') {
            return ['ok' => false, 'message' => __('Please upload a file with a .sql extension.', 'lifelines')];
        }

        // Move to a private, randomly named path inside the uploads directory so
        // we control the file and can delete it after importing.
        $upload = wp_upload_dir();
        if (!empty($upload['error']) || empty($upload['basedir'])) {
            return ['ok' => false, 'message' => __('The uploads directory is not writable.', 'lifelines')];
        }

        $destination = trailingslashit($upload['basedir'])
            . 'lifelines-import-' . wp_generate_password(12, false) . '.sql';

        if (!move_uploaded_file($tmpName, $destination)) {
            return ['ok' => false, 'message' => __('Could not store the uploaded file for import.', 'lifelines')];
        }

        try {
            $result = TownSchema::import($destination);
        } finally {
            // Always remove the uploaded file, whether or not the import succeeded.
            if (file_exists($destination)) {
                @unlink($destination); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
            }
        }

        return ['ok' => $result['ok'], 'message' => $result['message']];
    }

    private function uploadErrorMessage(int $error): string
    {
        switch ($error) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return __('The uploaded file is larger than the server allows. Increase upload_max_filesize / post_max_size, or split the dump.', 'lifelines');
            case UPLOAD_ERR_PARTIAL:
                return __('The file was only partially uploaded. Please try again.', 'lifelines');
            case UPLOAD_ERR_NO_FILE:
                return __('Please choose a .sql file to upload.', 'lifelines');
            default:
                return __('The file upload failed. Please try again.', 'lifelines');
        }
    }

    private function lookupPageUrl(): ?string
    {
        $pageId = (int) get_option(LookupBootstrap::PAGE_OPTION, 0);
        if ($pageId <= 0 || get_post_status($pageId) !== 'publish') {
            return null;
        }

        $url = get_permalink($pageId);

        return $url !== false ? $url : null;
    }
}
