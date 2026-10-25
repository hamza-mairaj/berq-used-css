<?php
defined('ABSPATH') || exit;

class BQUCSS_Cache_Compat {

    public function __construct() {
        add_action('bqucss_used_css_stored', [$this, 'purge_page_cache']);
        add_action('bqucss_flush_all',       [$this, 'purge_all_caches']);

        // Invalidate stored used CSS when content or design changes
        add_action('save_post',   [$this, 'invalidate_post'], 10, 1);
        add_action('post_updated', [$this, 'invalidate_post'], 10, 1);

        add_action('customize_save_after',               [$this, 'invalidate_all']);
        add_action('elementor/core/files/clear_cache',   [$this, 'invalidate_all']);
        add_action('elementor/editor/after_save',        [$this, 'invalidate_post_by_id'], 10, 2);
        add_action('switch_theme',                       [$this, 'invalidate_all']);
        add_action('activated_plugin',                   [$this, 'invalidate_all']);
        add_action('deactivated_plugin',                 [$this, 'invalidate_all']);
        add_action('upgrader_process_complete',          [$this, 'invalidate_on_upgrade'], 10, 2);

        // BerqWP integration
        add_action('berqwp_flush_all_cache',   [$this, 'invalidate_all']);
        add_action('berqwp_flush_page_cache',  [$this, 'invalidate_by_slug'], 10, 1);
        add_action('berqwp_stored_page_cache', [$this, 'invalidate_by_url'],  10, 1);
    }

    public function purge_page_cache(string $url): void {
        $post_id = url_to_postid($url);

        // WP Super Cache
        if (function_exists('wp_cache_post_change') && $post_id) {
            wp_cache_post_change($post_id);
        }
        if (function_exists('prune_super_cache')) {
            prune_super_cache($url, true);
        }

        // W3 Total Cache
        if (function_exists('w3tc_flush_url')) {
            w3tc_flush_url($url);
        }

        // Third-party cache plugin hooks below use their own hook names intentionally.
        // phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

        // LiteSpeed Cache
        do_action('litespeed_purge_url', $url);

        // WP Rocket
        if (function_exists('rocket_clean_url')) {
            rocket_clean_url($url);
        }

        // Autoptimize
        if (class_exists('autoptimizeCache')) {
            \autoptimizeCache::clearall();
        }

        // SiteGround SG Optimizer
        if (function_exists('sg_cachepress_purge_cache')) {
            sg_cachepress_purge_cache($url);
        }

        // Nginx Helper (RunCloud / Nginx reverse proxy)
        if (class_exists('Nginx_Helper')) {
            do_action('rt_nginx_helper_purge_url', $url);
        }

        // Breeze (Cloudways)
        if (class_exists('Breeze_PurgeCache')) {
            do_action('breeze_clear_varnish_cache_url', $url);
        }

        // Hummingbird
        if (class_exists('\\Hummingbird\\Core\\Utils')) {
            do_action('wphb_clear_page_cache', $post_id);
        }

        // Comet Cache / ZenCache
        if (class_exists('\\WebSharks\\CometCache\\Classes\\Plugin')) {
            do_action('comet_cache_wipe_cache');
        }

        // Cache Enabler
        if (class_exists('Cache_Enabler')) {
            do_action('cache_enabler_clear_page_cache_by_url', $url);
        }

        // Swift Performance
        if (class_exists('Swift_Performance_Cache')) {
            do_action('swift_performance_before_clean_cache');
        }

        // phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

        // Allow other plugins to hook in
        do_action('bqucss_purge_page_cache', $url);
    }

    public function purge_all_caches(): void {
        // WP Super Cache
        if (function_exists('wp_cache_clear_cache')) {
            wp_cache_clear_cache();
        }

        // W3 Total Cache
        if (function_exists('w3tc_flush_all')) {
            w3tc_flush_all();
        }

        // Third-party cache plugin hooks below use their own hook names intentionally.
        // phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

        // LiteSpeed Cache
        do_action('litespeed_purge_all');

        // WP Rocket
        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
        }

        // SiteGround SG Optimizer
        if (function_exists('sg_cachepress_purge_cache')) {
            sg_cachepress_purge_cache();
        }

        // Nginx Helper
        do_action('rt_nginx_helper_purge_all');

        // Cache Enabler
        if (class_exists('Cache_Enabler')) {
            do_action('cache_enabler_clear_complete_cache');
        }

        // phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

        // Allow other plugins to hook in
        do_action('bqucss_purge_all_caches');
    }

    // -------------------------------------------------------------------------
    // Used CSS invalidation
    // -------------------------------------------------------------------------

    public function invalidate_post(int $post_id): void {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id)) return;

        $url = get_permalink($post_id);
        if (empty($url)) return;

        $storage = new BQUCSS_Storage();
        $storage->delete($url);
    }

    public function invalidate_post_by_id(int $post_id): void {
        $this->invalidate_post($post_id);
    }

    public function invalidate_all(): void {
        (new BQUCSS_Storage())->flush_all();
        do_action('bqucss_flush_all');
    }

    public function invalidate_on_upgrade($upgrader, array $hook_extra): void {
        $type = $hook_extra['type'] ?? '';
        if (in_array($type, ['plugin', 'theme'], true)) {
            $this->invalidate_all();
        }
    }

    public function invalidate_by_slug(string $slug): void {
        $url = home_url(ltrim($slug, '/'));
        $storage = new BQUCSS_Storage();
        $storage->delete($url);
    }

    public function invalidate_by_url(string $url): void {
        $url = esc_url_raw($url);
        if (empty($url)) return;
        $storage = new BQUCSS_Storage();
        $storage->delete($url);
    }
}
