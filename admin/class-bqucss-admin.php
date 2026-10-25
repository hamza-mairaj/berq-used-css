<?php
defined('ABSPATH') || exit;

class BQUCSS_Admin {

    public function __construct() {
        add_action('admin_menu',            [$this, 'register_menu']);
        add_action('admin_init',            [$this, 'save_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_bqucss_clear_cache',      [$this, 'ajax_clear_cache']);
    }

    public function register_menu(): void {
        add_options_page(
            __('Berq Used CSS', 'berq-used-css'),
            __('Berq Used CSS', 'berq-used-css'),
            'manage_options',
            'berq-used-css',
            [$this, 'render_settings_page']
        );
    }

    public function enqueue_assets(string $hook): void {
        if ($hook !== 'settings_page_berq-used-css') return;
        wp_enqueue_style('bqucss-admin', BQUCSS_URL . 'admin/assets/admin.css', [], BQUCSS_VERSION);
        wp_enqueue_script('bqucss-admin', BQUCSS_URL . 'admin/assets/admin.js', [], BQUCSS_VERSION, true);
        wp_localize_script('bqucss-admin', 'bqucssAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('bqucss_admin'),
            'i18n'    => [
                'clearing'  => __('Clearing…', 'berq-used-css'),
                'cleared'   => __('Cache cleared!', 'berq-used-css'),
                'error'     => __('Error — please try again.', 'berq-used-css'),
            ],
        ]);
    }

    public function save_settings(): void {
        if (!isset($_POST['bqucss_save_settings'])) return;
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'] ?? '')), 'bqucss_save_settings')) return;
        if (!current_user_can('manage_options')) return;

        update_option('bqucss_enabled', absint(wp_unslash($_POST['bqucss_enabled'] ?? 0)), false);

        $raw_urls     = sanitize_textarea_field(wp_unslash($_POST['bqucss_exclude_urls'] ?? ''));
        $exclude_urls = array_filter(array_map('sanitize_text_field', explode("\n", $raw_urls)));
        update_option('bqucss_exclude_urls', array_values($exclude_urls), false);

        $raw_sels          = sanitize_textarea_field(wp_unslash($_POST['bqucss_exclude_selectors'] ?? ''));
        $exclude_selectors = array_filter(array_map('sanitize_text_field', explode("\n", $raw_sels)));
        update_option('bqucss_exclude_selectors', array_values($exclude_selectors), false);

        $mode = sanitize_text_field(wp_unslash($_POST['bqucss_stylesheet_mode'] ?? 'immediate'));
        if (!in_array($mode, ['immediate', 'defer', 'remove'], true)) $mode = 'immediate';
        update_option('bqucss_stylesheet_mode', $mode, false);

        update_option('bqucss_remove_font_faces', absint(wp_unslash($_POST['bqucss_remove_font_faces'] ?? 0)), false);
        update_option('bqucss_remove_bg_images',  absint(wp_unslash($_POST['bqucss_remove_bg_images']  ?? 0)), false);

        wp_safe_redirect(add_query_arg('bqucss_saved', '1', admin_url('options-general.php?page=berq-used-css')));
        exit;
    }

    public function ajax_clear_cache(): void {
        check_ajax_referer('bqucss_admin', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Unauthorized']);

        (new BQUCSS_Storage())->flush_all();
        do_action('bqucss_flush_all');

        wp_send_json_success([
            'message' => __('Used CSS cache cleared.', 'berq-used-css'),
            'count'   => 0,
        ]);
    }

    public function render_settings_page(): void {
        $enabled        = (int) get_option('bqucss_enabled', 1);
        $exclude_urls   = implode("\n", (array) get_option('bqucss_exclude_urls', []));
        $exclude_sels   = implode("\n", (array) get_option('bqucss_exclude_selectors', []));
        $mode           = get_option('bqucss_stylesheet_mode', 'immediate');
        $rm_fonts       = (int) get_option('bqucss_remove_font_faces', 0);
        $rm_bg          = (int) get_option('bqucss_remove_bg_images', 0);
        $cache_count    = (new BQUCSS_Storage())->count();
        ?>
        <div class="wrap bqucss-wrap">
            <h1><?php esc_html_e('Berq Used CSS', 'berq-used-css'); ?></h1>

            <?php if (!empty($_GET['bqucss_saved'])): // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only redirect param, not processing form data ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Settings saved.', 'berq-used-css'); ?></p></div>
            <?php endif; ?>

            <form method="post" action="">
                <?php wp_nonce_field('bqucss_save_settings'); ?>
                <input type="hidden" name="bqucss_save_settings" value="1">

                <table class="form-table" role="presentation">

                    <tr>
                        <th scope="row"><?php esc_html_e('Enable Plugin', 'berq-used-css'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="bqucss_enabled" value="1" <?php checked($enabled, 1); ?>>
                                <?php esc_html_e('Enable used CSS generation', 'berq-used-css'); ?>
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="bqucss_exclude_urls"><?php esc_html_e('Exclude URLs', 'berq-used-css'); ?></label>
                        </th>
                        <td>
                            <textarea id="bqucss_exclude_urls" name="bqucss_exclude_urls" rows="6" class="large-text code"><?php echo esc_textarea($exclude_urls); ?></textarea>
                            <p class="description"><?php esc_html_e('One URL per line. Use * as a wildcard suffix, e.g. https://example.com/shop/*', 'berq-used-css'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="bqucss_exclude_selectors"><?php esc_html_e('Exclude CSS Selectors', 'berq-used-css'); ?></label>
                        </th>
                        <td>
                            <textarea id="bqucss_exclude_selectors" name="bqucss_exclude_selectors" rows="6" class="large-text code"><?php echo esc_textarea($exclude_sels); ?></textarea>
                            <p class="description"><?php esc_html_e('One pattern per line. Plain text or /regex/. Matching selectors will be removed from the used CSS output.', 'berq-used-css'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e('Stylesheet Loading Mode', 'berq-used-css'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="radio" name="bqucss_stylesheet_mode" value="immediate" <?php checked($mode, 'immediate'); ?>>
                                    <?php esc_html_e('Load immediately (default — original stylesheets load normally alongside used CSS)', 'berq-used-css'); ?>
                                </label><br>
                                <label>
                                    <input type="radio" name="bqucss_stylesheet_mode" value="defer" <?php checked($mode, 'defer'); ?>>
                                    <?php esc_html_e('Load after user interaction (defer original stylesheets until click / scroll / keypress)', 'berq-used-css'); ?>
                                </label><br>
                                <label>
                                    <input type="radio" name="bqucss_stylesheet_mode" value="remove" <?php checked($mode, 'remove'); ?>>
                                    <?php esc_html_e('Remove completely (strip original stylesheet tags — only used CSS is served)', 'berq-used-css'); ?>
                                </label>
                            </fieldset>
                            <p class="description"><?php esc_html_e('Applied after used CSS has been generated. Original stylesheets are served unchanged on first visit.', 'berq-used-css'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e('Remove Font Faces', 'berq-used-css'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="bqucss_remove_font_faces" value="1" <?php checked($rm_fonts, 1); ?>>
                                <?php esc_html_e('Strip @font-face rules from generated used CSS', 'berq-used-css'); ?>
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e('Remove Background Images', 'berq-used-css'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="bqucss_remove_bg_images" value="1" <?php checked($rm_bg, 1); ?>>
                                <?php esc_html_e('Strip background-image: url(...) declarations from generated used CSS', 'berq-used-css'); ?>
                            </label>
                        </td>
                    </tr>

                </table>

                <?php submit_button(__('Save Settings', 'berq-used-css')); ?>
            </form>

            <hr>

            <h2><?php esc_html_e('Cache Management', 'berq-used-css'); ?></h2>
            <p>
                <?php
                $count_html = '<strong id="bqucss-cache-count">' . esc_html(number_format_i18n($cache_count)) . '</strong>';
                printf(
                    /* translators: %s: number of pages with cached used CSS, wrapped in <strong> and already escaped */
                    _n('%s page has cached used CSS.', '%s pages have cached used CSS.', $cache_count, 'berq-used-css'),
                    $count_html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $count_html is pre-escaped above
                );
                ?>
            </p>
            <p>
                <button id="bqucss-clear-cache" class="button button-secondary">
                    <?php esc_html_e('Clear All Used CSS', 'berq-used-css'); ?>
                </button>
                <span id="bqucss-clear-status" style="margin-left:10px;vertical-align:middle;"></span>
            </p>
        </div>
        <?php
    }
}
