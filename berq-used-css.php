<?php
/**
 * Plugin Name: Berq Used CSS
 * Description: Make your website load faster by removing unused CSS. Berq Used CSS automatically strips bloated stylesheets down to only what each page actually needs.
 * Version:     1.0.19
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Author:      BerqWP
 * Author URI:  https://berqwp.com/
 * Text Domain: berq-used-css
 * License:     GPL-2.0-or-later
 */

defined('ABSPATH') || exit;

define('BQUCSS_VERSION', '1.0.19');
define('BQUCSS_FILE',    __FILE__);
define('BQUCSS_PATH',    plugin_dir_path(__FILE__));
define('BQUCSS_URL',     plugin_dir_url(__FILE__));
define('BQUCSS_CACHE',   WP_CONTENT_DIR . '/cache/berq-used-css/');

// Load scoped vendor autoloader
require_once BQUCSS_PATH . 'build/vendor/autoload.php';

// Load plugin classes
require_once BQUCSS_PATH . 'inc/class-bqucss-storage.php';
require_once BQUCSS_PATH . 'inc/class-bqucss-extractor.php';
require_once BQUCSS_PATH . 'inc/class-bqucss-processor.php';
require_once BQUCSS_PATH . 'inc/class-bqucss-output.php';
require_once BQUCSS_PATH . 'inc/class-bqucss-cache-compat.php';
require_once BQUCSS_PATH . 'admin/class-bqucss-admin.php';

register_activation_hook(__FILE__,   'bqucss_activate');
register_deactivation_hook(__FILE__, 'bqucss_deactivate');

function bqucss_activate(): void {
    wp_mkdir_p(BQUCSS_CACHE);
    wp_mkdir_p(BQUCSS_CACHE . 'external/');

    if (get_option('bqucss_enabled') === false)          add_option('bqucss_enabled', 1, '', false);
    if (get_option('bqucss_exclude_urls') === false)     add_option('bqucss_exclude_urls', [], '', false);
    if (get_option('bqucss_exclude_selectors') === false) add_option('bqucss_exclude_selectors', [], '', false);
    if (get_option('bqucss_stylesheet_mode') === false)  add_option('bqucss_stylesheet_mode', 'immediate', '', false);
    if (get_option('bqucss_remove_font_faces') === false) add_option('bqucss_remove_font_faces', 0, '', false);
    if (get_option('bqucss_remove_bg_images') === false) add_option('bqucss_remove_bg_images', 0, '', false);
}

function bqucss_deactivate(): void {
    // Remove lock file if stuck
    $lock = BQUCSS_CACHE . '.lock';
    if (file_exists($lock)) wp_delete_file($lock);
}

add_action('plugins_loaded', 'bqucss_boot');

function bqucss_boot(): void {
    // Admin is always initialised so the settings page is always accessible.
    if (is_admin()) {
        new BQUCSS_Admin();
    }

    if (!get_option('bqucss_enabled', 1)) return;

    new BQUCSS_Output();
    new BQUCSS_Cache_Compat();
}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), function (array $links): array {
    $settings_link = '<a href="' . esc_url(admin_url('options-general.php?page=berq-used-css')) . '">' . __('Settings', 'berq-used-css') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
});
