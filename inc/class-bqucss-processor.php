<?php
defined('ABSPATH') || exit;

class BQUCSS_Processor {

    public function process(string $page_url): bool {
        if ($this->is_excluded($page_url)) {
            return false;
        }

        $html = $this->fetch_html($page_url);
        if (empty($html)) {
            return false;
        }

        $extractor = new BQUCSS_Extractor(get_option('home'));

        // Merge in any programmatically registered force-include/exclude patterns
        $extra_include = apply_filters('bqucss_force_include_patterns', []);
        $extra_exclude = apply_filters('bqucss_force_exclude_patterns', []);
        if (!empty($extra_include)) $extractor->add_force_include($extra_include);
        if (!empty($extra_exclude)) $extractor->add_force_exclude($extra_exclude);

        try {
            $used_css = $extractor->process_css($html);
        } catch (\Throwable $e) {
            return false;
        }

        $used_css = $this->apply_options($used_css);
        $used_css = $this->apply_exclude_selectors($used_css);
        $used_css = (string) apply_filters('bqucss_used_css', $used_css, $page_url);

        if (empty(trim($used_css))) {
            return false;
        }

        $storage = new BQUCSS_Storage();

        return $storage->store($page_url, $used_css);
    }

    private function fetch_html(string $url): string {
        $bypass_url = add_query_arg('bqucss_bypass', time(), $url);
        $response   = wp_remote_get($bypass_url, [
            'timeout'   => 30,
            'sslverify' => false,
            'headers'   => ['Cache-Control' => 'no-cache'],
        ]);

        if (is_wp_error($response)) return '';

        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 400) return '';

        $body = wp_remote_retrieve_body($response);

        // Strip the bypass param from URLs in the response body
        $body = preg_replace('/[?&]bqucss_bypass=\d+/', '', $body);

        return $body;
    }

    private function apply_options(string $css): string {
        if (get_option('bqucss_remove_font_faces', 0)) {
            $css = preg_replace('/@font-face\s*\{(?:[^{}]+|\{[^}]*\})*\}/is', '', $css);
        }

        if (get_option('bqucss_remove_bg_images', 0)) {
            // Strip background-image or background shorthand that contain url(...) references
            $css = preg_replace('/background(?:-image)?\s*:[^;}]*url\([^)]+\)[^;}]*/i', '', $css);
        }

        return $css;
    }

    private function apply_exclude_selectors(string $css): string {
        $patterns = get_option('bqucss_exclude_selectors', []);
        if (empty($patterns)) return $css;

        foreach ($patterns as $pattern) {
            $pattern = trim($pattern);
            if (empty($pattern)) continue;

            // Wrap plain text in a case-insensitive regex if not already a regex
            if ($pattern[0] !== '/') {
                $pattern = '/' . preg_quote($pattern, '/') . '/i';
            }

            $css = preg_replace_callback(
                '/([^{}]+)\{([^{}]+)\}/',
                function (array $match) use ($pattern): string {
                    if (@preg_match($pattern, $match[1])) return '';
                    return $match[0];
                },
                $css
            );
        }

        return $css;
    }

    public function is_excluded(string $url): bool {
        if (wp_parse_url($url, PHP_URL_QUERY)) {
            return true;
        }

        if ((bool) apply_filters('bqucss_should_process', true, $url) === false) {
            return true;
        }

        $excludes = get_option('bqucss_exclude_urls', []);
        foreach ($excludes as $excl) {
            $excl = trim($excl);
            if (empty($excl)) continue;

            if (substr($excl, -1) === '*') {
                if (strpos($url, rtrim($excl, '*')) !== false) return true;
            } else {
                if (rtrim($url, '/') === rtrim($excl, '/')) return true;
            }
        }

        return false;
    }
}
