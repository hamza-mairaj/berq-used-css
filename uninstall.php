<?php
defined('WP_UNINSTALL_PLUGIN') || exit;

// Delete all plugin options
$bqucss_options = [
    'bqucss_enabled',
    'bqucss_exclude_urls',
    'bqucss_exclude_selectors',
    'bqucss_stylesheet_mode',
    'bqucss_remove_font_faces',
    'bqucss_remove_bg_images',
];
foreach ($bqucss_options as $bqucss_option) {
    delete_option($bqucss_option);
}

// Delete the entire cache directory using WP_Filesystem
$bqucss_cache_dir = WP_CONTENT_DIR . '/cache/berq-used-css/';
if (is_dir($bqucss_cache_dir)) {
    global $wp_filesystem;
    if (empty($wp_filesystem)) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        WP_Filesystem();
    }

    $bqucss_iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($bqucss_cache_dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($bqucss_iterator as $bqucss_file) {
        if ($bqucss_file->isDir()) {
            $wp_filesystem->rmdir($bqucss_file->getRealPath());
        } else {
            $wp_filesystem->delete($bqucss_file->getRealPath());
        }
    }
    $wp_filesystem->rmdir($bqucss_cache_dir);
}
