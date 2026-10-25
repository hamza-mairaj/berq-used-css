<?php
defined('ABSPATH') || exit;

class BQUCSS_Storage {

    private string $cache_dir;

    public function __construct() {
        $this->cache_dir = BQUCSS_CACHE;
        if (!is_dir($this->cache_dir)) wp_mkdir_p($this->cache_dir);
    }

    public function store(string $page_url, string $css): bool {
        $path = $this->get_path($page_url);
        return (bool) @file_put_contents($path, gzencode($css, 6), LOCK_EX);
    }

    public function get(string $page_url): string {
        $path = $this->get_path($page_url);
        if (!is_file($path)) return '';
        $raw = @file_get_contents($path);
        return $raw !== false ? (string) gzdecode($raw) : '';
    }

    public function has(string $page_url): bool {
        $path = $this->get_path($page_url);
        return is_file($path) && filesize($path) > 0;
    }

    public function delete(string $page_url): void {
        $path = $this->get_path($page_url);
        if (is_file($path)) wp_delete_file($path);
    }

    public function flush_all(): void {
        $files = glob($this->cache_dir . '*.css.gz');
        if ($files) {
            foreach ($files as $file) {
                wp_delete_file($file);
            }
        }
    }

    public function count(): int {
        return count(glob($this->cache_dir . '*.css.gz') ?: []);
    }

    private function get_path(string $page_url): string {
        return $this->cache_dir . md5($page_url) . '.css.gz';
    }
}
