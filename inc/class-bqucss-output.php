<?php
defined('ABSPATH') || exit;

class BQUCSS_Output {

    private bool   $buffer_started = false;
    private string $lock_file;
    private int    $lock_timeout = 120;

    public function __construct() {
        $this->lock_file = BQUCSS_CACHE . '.lock';

        add_action('wp_enqueue_scripts',  [$this, 'maybe_enqueue_used_css'], 5);
        add_action('template_redirect',   [$this, 'maybe_generate_used_css'], 1);
        add_action('template_redirect',   [$this, 'maybe_start_buffer'], 2);
        add_action('shutdown',            [$this, 'close_buffer'], 0);
    }

    public function maybe_enqueue_used_css(): void {
        if (!$this->should_process()) return;

        $url     = $this->get_current_url();
        $storage = new BQUCSS_Storage();
        if (!$storage->has($url)) return;

        $used_css = $storage->get($url);
        if (empty($used_css)) return;

        wp_register_style('bqucss-used-css', false, [], BQUCSS_VERSION); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
        wp_enqueue_style('bqucss-used-css');
        wp_add_inline_style('bqucss-used-css', wp_strip_all_tags($used_css));
    }

    public function maybe_start_buffer(): void {
        if (!$this->should_process()) return;
        $this->buffer_started = true;
        ob_start([$this, 'process_buffer']);
    }

    public function close_buffer(): void {
        if ($this->buffer_started && ob_get_level() > 0) {
            ob_end_flush();
            $this->buffer_started = false;
        }
    }

    public function maybe_generate_used_css(): void {
        if (!$this->should_process()) return;
        $url     = $this->get_current_url();
        $storage = new BQUCSS_Storage();
        if (!$storage->has($url)) {
            $this->generate_used_css($url);
        }
    }

    private function generate_used_css(string $url): void {
        if (!$this->acquire_lock()) return;

        try {
            $processor = new BQUCSS_Processor();
            if ($processor->process($url)) {
                do_action('bqucss_used_css_stored', $url);
            }
        } catch (\Throwable $e) {
            // Swallow — next visit will simply retry generation.
        } finally {
            $this->release_lock();
        }
    }

    // -------------------------------------------------------------------------
    // Lock management
    // -------------------------------------------------------------------------

    private function acquire_lock(): bool {
        if (!is_dir(BQUCSS_CACHE)) wp_mkdir_p(BQUCSS_CACHE);

        if (file_exists($this->lock_file)) {
            $mtime = (int) @filemtime($this->lock_file);
            if ($mtime > (time() - $this->lock_timeout)) {
                return false; // Lock is fresh — another request is generating.
            }
            wp_delete_file($this->lock_file); // Stale lock — remove
        }

        return (bool) @file_put_contents($this->lock_file, time(), LOCK_EX);
    }

    private function release_lock(): void {
        if (file_exists($this->lock_file)) {
            wp_delete_file($this->lock_file);
        }
    }

    public function process_buffer(string $buffer): string {
        if (strlen($buffer) < 255) return $buffer;
        if (strpos($buffer, '</html>') === false) return $buffer;

        $url     = $this->get_current_url();
        $storage = new BQUCSS_Storage();

        if (!$storage->has($url)) {
            // Generation was skipped (excluded URL, failed, or lock contention) — serve page unmodified.
            return $buffer;
        }

        // Apply global stylesheet loading mode (used CSS is already in <head> via wp_add_inline_style)
        $buffer = $this->apply_stylesheet_mode($buffer);

        return $buffer;
    }

    // -------------------------------------------------------------------------
    // Stylesheet mode application
    // -------------------------------------------------------------------------

    private function apply_stylesheet_mode(string $buffer): string {
        $mode = get_option('bqucss_stylesheet_mode', 'immediate');
        if ($mode === 'immediate') return $buffer;

        if ($mode === 'remove') {
            $buffer = preg_replace('/<link[^>]+rel=["\']stylesheet["\'][^>]*\/?>/i', '', $buffer);
            $buffer = preg_replace_callback(
                '/<style(\b[^>]*)>(.*?)<\/style>/is',
                function (array $m): string {
                    if (strpos($m[1], 'data-bqucss') !== false || strpos($m[1], 'bqucss-used-css') !== false) return $m[0];
                    return '';
                },
                $buffer
            );
            return $buffer;
        }

        if ($mode === 'defer') {
            $buffer = preg_replace_callback(
                '/<link([^>]+)rel=["\']stylesheet["\']([^>]*)>/i',
                [$this, 'defer_link_tag'],
                $buffer
            );
            $buffer = preg_replace_callback(
                '/<style(\b[^>]*)>(.*?)<\/style>/is',
                [$this, 'defer_style_tag'],
                $buffer
            );
            $buffer = str_replace('</body>', $this->get_defer_css_script() . '</body>', $buffer);
        }

        return $buffer;
    }

    private function defer_style_tag(array $match): string {
        if (strpos($match[1], 'data-bqucss') !== false || strpos($match[1], 'bqucss-used-css') !== false) return $match[0];
        return '<script type="text/css-deferred"' . $match[1] . '>' . $match[2] . '</script>';
    }

    private function defer_link_tag(array $match): string {
        $tag = $match[0];

        // Extract href value
        if (!preg_match('/href=["\']([^"\']+)["\']/', $tag, $href_match)) {
            return $tag;
        }

        $href = $href_match[1];

        // Replace href with data-bqucss-href so the browser won't load it
        $tag = str_replace(
            ['href="' . $href . '"', "href='" . $href . "'"],
            ['data-bqucss-href="' . $href . '"', "data-bqucss-href='" . $href . "'"],
            $tag
        );

        // Also remove media="all" or similar so it doesn't interfere
        return $tag;
    }

    private function get_defer_css_script(): string {
        return '<script id="bqucss-defer-css">' .
            '(function(){' .
            'var l=false;' .
            'function b(){' .
                'if(l)return;l=true;' .
                'document.querySelectorAll("link[data-bqucss-href]").forEach(function(el){' .
                    'el.setAttribute("href",el.getAttribute("data-bqucss-href"));' .
                    'el.removeAttribute("data-bqucss-href");' .
                '});' .
                'document.querySelectorAll("script[type=\'text/css-deferred\']").forEach(function(el){' .
                    'var s=document.createElement("style");' .
                    'Array.prototype.slice.call(el.attributes).forEach(function(a){' .
                        'if(a.name!=="type")s.setAttribute(a.name,a.value);' .
                    '});' .
                    's.textContent=el.textContent;' .
                    'document.head.appendChild(s);' .
                    'el.parentNode.removeChild(el);' .
                '});' .
                '["click","scroll","keydown","mousemove","touchstart"].forEach(function(e){' .
                    'window.removeEventListener(e,b);' .
                '});' .
            '}' .
            '["click","scroll","keydown","mousemove","touchstart"].forEach(function(e){' .
                'window.addEventListener(e,b,{once:true,passive:true});' .
            '});' .
            '})();' .
            '</script>';
    }

    // -------------------------------------------------------------------------
    // Buffer helpers
    // -------------------------------------------------------------------------
    // Guard / URL helpers
    // -------------------------------------------------------------------------

    public function should_process(): bool {
        if (is_admin()) return false;
        if (is_user_logged_in()) return false;
        if (defined('DOING_AJAX') && DOING_AJAX) return false;
        if (defined('REST_REQUEST') && REST_REQUEST) return false;
        if (defined('DOING_CRON') && DOING_CRON) return false;
        if (defined('WP_CLI') && WP_CLI) return false;

        // is_feed / is_404 require the query to be set up — check safely
        if (function_exists('is_feed') && is_feed()) return false;
        if (function_exists('is_404') && is_404()) return false;

        if (!empty($_SERVER['QUERY_STRING'])) return false; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- boolean check only, value not used

        $url = $this->get_current_url();
        if (!$this->is_pretty_url($url)) return false;

        $excludes = get_option('bqucss_exclude_urls', []);
        foreach ($excludes as $excl) {
            $excl = trim($excl);
            if (empty($excl)) continue;
            if (substr($excl, -1) === '*') {
                if (strpos($url, rtrim($excl, '*')) !== false) return false;
            } elseif (rtrim($url, '/') === rtrim($excl, '/')) {
                return false;
            }
        }

        return (bool) apply_filters('bqucss_should_process', true, $url);
    }

    private function is_pretty_url(string $url): bool {
        $path = wp_parse_url($url, PHP_URL_PATH) ?? '/';
        if (preg_match('/\.[a-zA-Z0-9]{1,10}$/', $path)) {
            return (bool) preg_match('/\.html$/i', $path);
        }
        return true;
    }

    public function get_current_url(): string {
        $forwarded = isset($_SERVER['HTTP_X_FORWARDED_PROTO']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_PROTO'])) : '';
        $proto     = (is_ssl() || $forwarded === 'https') ? 'https://' : 'http://';
        $host      = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
        $path      = strtok(isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '/', '?');
        return $proto . $host . $path;
    }
}
