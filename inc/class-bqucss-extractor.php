<?php
defined('ABSPATH') || exit;

use BerqUsedCSS\Deps\voku\helper\HtmlDomParser;

class BQUCSS_Extractor {

    // Instance property — NOT static. Prevents cross-instance HTML contamination.
    private string $html = '';

    public string $combine_css = '';
    public string $domain      = '';
    public string $site_url    = '';
    public array  $js_classes  = [];

    // Static base arrays — configuration, not per-request state.
    private static array $base_force_exclude = [
        '/:focus[^)]/',
        '/:hover[^)]/',
    ];

    private static array $base_force_include = [
        '/\[.*\$.*=.*\]/',
        '/\.elementor-motion-effects-element-type-background/',
        '/\.swiper-wrapper/',
        '/\.woocommerce-js/',
        '/\.et_pb_menu__logo img/',
        '/\.tns-/',
        '/\.dtb-hamburger/',
        '/\.elementor-social-icon/',
        '/\.visible/',
        '/^\.td-js-loaded/',
        '/^\.lte-navwrapper-mobile/',
        '/^\.lte-navbar-items.navbar-mobile/',
        '/^\.navbar-mobile/',
        '/^\.theme-menu-mode-responsive/',
        '/^\.theme-menu-responsive-button/',
        '/^\.stk-video-background/',
        '/^\.is-menu.is-dropdown/',
        '/^\.animated/',
        '/^\.et_pb_animation/',
        '/^\.et_animated/',
        '/^\.et_mobile_menu/',
        '/^\.awb-menu.collapse-enabled/',
        '/^\.collapse-enabled/',
        '/^\.do-animate/',
        '/^\.ast-header-break-point/',
        '/^\.plus-canvas-content-wrap/',
        '/^\.elementor-element:where/',
        '/^\.bounce/',
        '/^\.bounceIn/',
        '/^\.bounceInDown/',
        '/^\.bounceInLeft/',
        '/^\.bounceInRight/',
        '/^\.bounceInUp/',
        '/^\.e-animation-bob/',
        '/^\.e-animation-bounce-in/',
        '/^\.e-animation-bounce-out/',
        '/^\.e-animation-buzz-out/',
        '/^\.e-animation-buzz/',
        '/^\.e-animation-float/',
        '/^\.e-animation-grow-rotate/',
        '/^\.e-animation-grow/',
        '/^\.e-animation-hang/',
        '/^\.e-animation-pop/',
        '/^\.e-animation-pulse-grow/',
        '/^\.e-animation-pulse-shrink/',
        '/^\.e-animation-pulse/',
        '/^\.e-animation-push/',
        '/^\.e-animation-rotate/',
        '/^\.e-animation-shrink/',
        '/^\.e-animation-sink/',
        '/^\.e-animation-skew-backward/',
        '/^\.e-animation-skew-forward/',
        '/^\.e-animation-skew/',
        '/^\.e-animation-wobble-bottom/',
        '/^\.e-animation-wobble-horizontal/',
        '/^\.e-animation-wobble-skew/',
        '/^\.e-animation-wobble-to-bottom-right/',
        '/^\.e-animation-wobble-to-top-right/',
        '/^\.e-animation-wobble-top/',
        '/^\.e-animation-wobble-vertical/',
        '/^\.fadeIn/',
        '/^\.fadeInDown/',
        '/^\.fadeInLeft/',
        '/^\.fadeInRight/',
        '/^\.fadeInUp/',
        '/^\.flash/',
        '/^\.headShake/',
        '/^\.jello/',
        '/^\.lightSpeedIn/',
        '/^\.pulse/',
        '/^\.rollIn/',
        '/^\.rotateIn/',
        '/^\.rotateInDownLeft/',
        '/^\.rotateInDownRight/',
        '/^\.rotateInUpLeft/',
        '/^\.rotateInUpRight/',
        '/^\.rubberBand/',
        '/^\.shake/',
        '/^\.slideInDown/',
        '/^\.slideInLeft/',
        '/^\.slideInRight/',
        '/^\.slideInUp/',
        '/^\.swing/',
        '/^\.tada/',
        '/^\.wobble/',
        '/^\.zoomIn/',
        '/^\.zoomInDown/',
        '/^\.zoomInLeft/',
        '/^\.zoomInRight/',
        '/^\.zoomInUp/',
    ];

    // Per-instance arrays — can be extended via add_force_include / add_force_exclude
    private array $force_exclude = [];
    private array $force_include = [];

    public function __construct(string $site_url) {
        $this->site_url     = $site_url;
        $this->force_exclude = self::$base_force_exclude;
        $this->force_include = self::$base_force_include;
    }

    public function add_force_include(array $patterns): void {
        $this->force_include = array_merge($this->force_include, $patterns);
    }

    public function add_force_exclude(array $patterns): void {
        $this->force_exclude = array_merge($this->force_exclude, $patterns);
    }

    public function process_css(string $html): string {
        $this->html = $html;
        $selectors  = [];

        $dom = HtmlDomParser::str_get_html($html);
        $used_fonts        = [];
        $used_font_weights = ['normal' => 1, '400' => 1];
        $used_css          = '';

        foreach ($dom->find('style') as $style) {
            $style->setAttribute('data-bqucss-critical', '1');
        }

        foreach ($dom->find('link') as $link) {
            $rel = strtolower($link->rel ?? '');
            $as  = strtolower($link->as ?? '');
            if ($rel === 'stylesheet' || $as === 'style') {
                $link->setAttribute('data-bqucss-critical', '1');
            }
        }

        $nodes = $dom->find('[data-bqucss-critical="1"]');
        $href  = '';

        foreach ($nodes as $css_node) {
            $local_css = '';

            if ($css_node->tag === 'style') {
                $local_css = '@media all { ' . $css_node->innertext . ' } ';
            }

            if ($css_node->tag === 'link') {
                $href   = $css_node->href ?? '';
                $media  = $css_node->media ?? '';
                $onload = $css_node->onload ?? '';

                if ($onload === "this.media='all'" || $onload === "this.media=&#039;all&#039;;this.onload=null;") {
                    $media = 'all';
                }

                if ($media === 'print') continue;
                if (!$this->is_valid_asset($href)) continue;

                $base_url = $this->getBaseUrl($this->site_url);
                $href     = $this->rel2abs($href, $base_url);

                $response = $this->fetch($href);
                if ($response === false) continue;

                $local_css .= $response;
            }

            if (!empty($local_css)) {
                $matches = $this->extractUrlsFromCss($local_css);
                foreach ($matches as $url) {
                    $url = trim($url);
                    if (!empty($url) && $this->is_valid_asset($url)) {
                        $local_css = $this->replace_url($url, $this->rel2abs($url, $href), $local_css);
                    }
                }

                if (!empty($media) && $media !== 'screen') {
                    $local_css = "@media $media {{$local_css}}";
                }

                $this->combine_css .= $local_css;

                $css = $this->extract_used_css($local_css, $selectors);
                $css = $this->minify_css($css);
                $css = preg_replace('/@media[^{]+\{\s*\}/i', '', $css);
                $css = preg_replace('/@@+/', '', $css);
                $used_css .= $css;

                preg_match_all('/font-family\s*:\s*([^!;}]+)/', $css, $mf);
                preg_match_all('/font-weight\s*:\s*([^!;}]+)/', $css, $mw);

                if (!empty($mf[1])) {
                    foreach ($mf[1] as $ff) {
                        if (strpos($ff, ',') !== false) {
                            foreach (explode(',', $ff) as $fm) {
                                $used_fonts[trim($fm, " \t\n\r\0\x0B'\"")] = 1;
                            }
                        } else {
                            $used_fonts[trim($ff, " \t\n\r\0\x0B'\"")] = 1;
                        }
                    }
                    foreach ($mw[1] as $fw) {
                        $used_font_weights[trim($fw, " \t\n\r\0\x0B'\"")] = 1;
                    }
                }
            }
        }


        for ($i = 0; $i < 3; $i++) {
            $used_css = preg_replace('/@media[^{]+\{\s*\}/i', '', $used_css);
        }

        $font_faces = $this->used_font_faces($used_fonts, $used_font_weights);
        $font_faces = str_replace('font-display:block;', 'font-display:swap;', $font_faces);
        $font_faces = str_replace('font-style:', 'font-display:swap;font-style:', $font_faces);

        return $used_css . $font_faces;
    }

    // -------------------------------------------------------------------------
    // CSS asset caching (uses plugin's own external/ directory)
    // -------------------------------------------------------------------------

    private function get_external_cache_dir(): string {
        $path = BQUCSS_CACHE . 'external/';
        if (!is_dir($path)) wp_mkdir_p($path);
        return $path;
    }

    private function has_cache(string $url): bool {
        $file = $this->get_external_cache_dir() . md5($url) . '.gz';
        return is_file($file) && (int) @filemtime($file) > (time() - DAY_IN_SECONDS);
    }

    private function cache_asset(string $url, string $content): bool {
        $file = $this->get_external_cache_dir() . md5($url) . '.gz';
        return (bool) @file_put_contents($file, gzencode($content, 9), LOCK_EX);
    }

    private function cache_miss(string $url): bool {
        $file = $this->get_external_cache_dir() . md5($url) . '.gz';
        return (bool) @file_put_contents($file, gzencode('bqucss-404', 9), LOCK_EX);
    }

    private function is_cached_miss(string $url): bool {
        if (!$this->has_cache($url)) return false;
        return $this->get_cache($url) === 'bqucss-404';
    }

    private function get_cache(string $url): string {
        $file = $this->get_external_cache_dir() . md5($url) . '.gz';
        return (string) gzdecode((string) @file_get_contents($file));
    }

    // -------------------------------------------------------------------------
    // Asset fetching
    // -------------------------------------------------------------------------

    private function fetch(string $url) {
        if ($this->is_self_hosted_url($url)) {
            if ($this->is_cached_miss($url)) return false;
            $local_path = $this->url_to_path($url);
            if ($local_path && is_file($local_path)) {
                return (string) file_get_contents($local_path);
            }
            $this->cache_miss($url);
            return false;
        }

        if ($this->is_cached_miss($url)) return false;

        if ($this->has_cache($url)) {
            return $this->get_cache($url);
        }

        $response         = wp_remote_get($url, ['timeout' => 30, 'sslverify' => false]);
        $response_code    = wp_remote_retrieve_response_code($response);
        $is_valid         = $response_code >= 200 && $response_code < 400;

        if (is_wp_error($response) || !$is_valid) {
            $this->cache_miss($url);
            return false;
        }

        $local_css = wp_remote_retrieve_body($response);

        preg_match_all('/@import\s+(?:url\s*\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)|[\'"]([^\'";]+)[\'"])/', $local_css, $matches);

        if (!empty($matches[1])) {
            foreach ($matches[1] as $import_url) {
                $import_url = trim($import_url, " \t\n\r\0\x0B'\"");
                if (empty($import_url)) continue;

                $base       = $this->getBaseUrl($url);
                $import_url = $this->rel2abs($import_url, $base);

                if (!$this->is_valid_asset($import_url)) continue;

                $sub = $this->fetch($import_url);
                if ($sub === false) continue;

                foreach ($this->extractUrlsFromCss($sub) as $url_func) {
                    $url_func = trim($url_func);
                    if (!empty($url_func)) {
                        $sub = $this->replace_url($url_func, $this->rel2abs($url_func, $import_url), $sub);
                    }
                }

                $local_css .= $sub;
            }
        }

        $this->cache_asset($url, $local_css);
        return $local_css;
    }

    // -------------------------------------------------------------------------
    // URL helpers
    // -------------------------------------------------------------------------

    private function replace_url(string $search, string $replace, string $content): string {
        if (strpos($content, "'$search'") !== false) {
            $content = str_replace("'$search'", "'$replace'", $content);
        }
        if (strpos($content, "\"$search\"") !== false) {
            $content = str_replace("\"$search\"", "\"$replace\"", $content);
        }
        if (strpos($content, "($search)") !== false) {
            $content = str_replace("($search)", "($replace)", $content);
        }
        if (strpos($content, " $search ") !== false) {
            $content = str_replace(" $search ", " $replace ", $content);
        }
        return $content;
    }

    private function is_valid_asset(string $url): bool {
        return strpos($url, 'data:') === false;
    }

    public static function rel2abs(string $rel, string $base): string {
        if (wp_parse_url($rel, PHP_URL_SCHEME) !== null && wp_parse_url($rel, PHP_URL_SCHEME) !== '') {
            return $rel;
        }
        if (strpos($rel, '//') === 0) {
            $scheme = wp_parse_url($base, PHP_URL_SCHEME) ?: 'https';
            return $scheme . ':' . $rel;
        }
        if (!empty($rel) && ($rel[0] === '#' || $rel[0] === '?')) {
            return $base . $rel;
        }

        extract(wp_parse_url($base)); // provides $scheme, $host, $path
        $path = preg_replace('#/[^/]*$#', '', $path ?? '');

        if (!empty($rel) && $rel[0] === '/') {
            $path = '';
        }

        $abs = ($host ?? '') . $path . '/' . $rel;
        $re  = ['#(/\.?/)#', '#/(?!\.\.)[^/]+/\.\./#'];
        for ($n = 1; $n > 0; $abs = preg_replace($re, '/', $abs, -1, $n));

        return ($scheme ?? 'https') . '://' . $abs;
    }

    public static function getBaseUrl(string $fileUrl): string {
        $parsed  = wp_parse_url($fileUrl);
        $scheme  = isset($parsed['scheme']) ? $parsed['scheme'] . '://' : '';
        $host    = $parsed['host'] ?? '';
        $path    = $parsed['path'] ?? '';
        $base    = preg_replace('/\/[^\/]+$/', '/', $path);
        return $scheme . $host . $base;
    }

    public static function extractUrlsFromCss(string $css): array {
        $urls = [];
        preg_match_all('/url\((.*?)\)/i', $css, $matches);
        foreach ($matches[1] as $match) {
            $urls[] = trim($match, "'\"\t ");
        }
        return $urls;
    }

    private function is_self_hosted_url(string $url): bool {
        if (empty($url)) return false;
        if (strpos($url, '//') === false) return true;
        $site_host = wp_parse_url(site_url(), PHP_URL_HOST);
        $url_host  = wp_parse_url($url, PHP_URL_HOST);
        return $site_host && $url_host && strtolower($site_host) === strtolower($url_host);
    }

    private function url_to_path(string $url) {
        if (strpos($url, '?') !== false) {
            $url = explode('?', $url)[0];
        }
        if (strpos($url, '//') === 0) {
            $path = wp_parse_url('https:' . $url, PHP_URL_PATH);
            return realpath(ABSPATH . ltrim($path ?? '', '/'));
        }
        $content_url = content_url();
        if (strpos($url, $content_url) === 0) {
            return str_replace($content_url, WP_CONTENT_DIR, $url);
        }
        $site_url = get_site_url();
        if (strpos($url, $site_url) === 0) {
            return realpath(str_replace($site_url, ABSPATH, $url));
        }
        return false;
    }

    // -------------------------------------------------------------------------
    // Font faces
    // -------------------------------------------------------------------------

    private function used_font_faces(array $used_fonts, array $used_font_weights): string {
        preg_match_all('/@font-face\s*{[^}]*}/', $this->combine_css, $matches);
        $used = '';
        foreach ($matches[0] as $font) {
            preg_match('/font-family\s*:\s*([^;}]+)/', $font, $mf);
            preg_match('/font-weight\s*:\s*([^;}]+)/', $font, $mw);
            $family = isset($mf[1]) ? trim($mf[1], " \t\n\r\0\x0B'\"") : '';
            $weight = isset($mw[1]) ? trim($mw[1], " \t\n\r\0\x0B'\"") : '';
            if (!empty($used_fonts[$family]) && !empty($used_font_weights[$weight])) {
                $used .= $font;
            }
        }
        return $used;
    }

    // -------------------------------------------------------------------------
    // Selector matching
    // -------------------------------------------------------------------------

    static function next_token(string $sel, int $current = 0) {
        $tokens     = [' ', '.', '#'];
        $characters = str_split($sel);

        foreach ($characters as $index => $char) {
            if ($index >= $current && in_array($char, $tokens, true)) {

                if (!isset($characters[$index - 1])) {
                    return ['char' => $char, 'pos' => $index];
                }

                $prev_sibling = $characters[$index - 1];

                if ($char === '.' && $prev_sibling === '\\') continue;
                if ($char === '#' && $prev_sibling === '\\') continue;
                return ['char' => $char, 'pos' => $index];
            }
        }

        return false;
    }

    private static function matches_js_class(string $class, array $js_classes): bool {
        foreach ($js_classes as $pattern) {
            if (substr($pattern, -1) === '*') {
                if (strpos($class, substr($pattern, 0, -1)) === 0) return true;
            } else {
                if ($class === $pattern) return true;
            }
        }
        return false;
    }

    private function is_selector_used(string $selector, array $html_selectors, array $js_classes = []): bool {
        if (empty($selector)) return false;

        foreach ($this->force_exclude as $keyword) {
            if (preg_match($keyword, $selector)) return false;
        }

        if ($selector === ':root') return true;

        foreach ($this->force_include as $keyword) {
            if (preg_match($keyword, $selector)) return true;
        }

        $selector = preg_replace('/:{1,2}[a-zA-Z-]+(\((?:[^()]*|\([^()]*\))*\))?/', '', $selector);
        $selector = trim($selector);
        if (empty($selector)) return true;

        $selector = str_replace([' > ', '>', ' + ', '+', ' ~ ', '~', ' *'], [' ', ' ', ' ', ' ', ' ', ' ', ''], $selector);

        if (strpos($selector, '*') !== false) return true;

        if (strpos($selector, '.') !== false && !empty($js_classes)) {
            preg_match_all('/\.([a-zA-Z0-9_\\\:\\/\\-]+)/', $selector, $matches);
            if (!empty($matches[1])) {
                $has_js = false;
                foreach ($matches[1] as $class) {
                    if (self::matches_js_class($class, $js_classes)) {
                        $selector = preg_replace('/\.' . preg_quote($class, '/') . '(?=[\s.#>+~\[\]:,)]|$)/', '', $selector);
                        $has_js   = true;
                    }
                }
                if ($has_js) {
                    $selector = trim(preg_replace('/\s+/', ' ', $selector));
                    if (empty($selector)) return true;
                }
            }
        }

        $starts_with_tag = false;
        $tag_name        = '';

        if (!empty($selector) && !in_array($selector[0], ['.', '#', ' '], true)) {
            if (preg_match('/^([a-zA-Z][a-zA-Z0-9-]*)/i', $selector, $m)) {
                $tag_name    = strtolower($m[1]);
                $tag_regex   = '<' . preg_quote($tag_name, '/') . '\\b[^>]*>';
                if (!preg_match("/$tag_regex/", $this->html)) return false;
                $starts_with_tag = true;
                $selector        = ltrim(substr($selector, strlen($m[1])));
                if (empty($selector)) return true;
            } else {
                return true;
            }
        }

        $token          = self::next_token($selector);
        $reg            = '';
        $prev           = null;
        $last_pos       = -1;
        $iteration_count = 0;
        $max_iterations = strlen($selector);
        $herahicle_match = [];

        while ($token !== false) {
            $char = $token['char'];
            $pos  = $token['pos'];

            if ($pos === $last_pos) break;
            $iteration_count++;
            if ($iteration_count > $max_iterations) break;
            $last_pos = $pos;

            if ($char === '.') {
                $next_token = self::next_token($selector, $pos + 1);
                $next       = $next_token['char'] ?? false;
                $length     = ($next_token['pos'] ?? strlen($selector)) - $pos;
                $class      = substr($selector, $pos + 1, $length - 1);
                $token      = $next_token;

                $class = preg_replace('/\\\\(.)/u', '$1', $class);

                if (strpos($class, '\[') === false && strpos($class, '[') !== false) {
                    preg_match_all('/\[((?:[^]]+)?)/', $class, $attr_matches);
                    $class = preg_replace("/\[[^>\]]+]/", '', $class);
                    foreach ($attr_matches[1] as $match) {
                        $match     = explode('=', $match)[0];
                        $match     = preg_quote($match, '/');
                        $match_att = '<(?:[^>]+)?' . $match . '(?:[^>]+)?>';
                        if (!preg_match("/$match_att/", $this->html)) return false;
                    }
                }

                $esc = preg_quote($class, '/');

                if ($prev === null && $next === ' ') {
                    $reg .= 'class\s*=\s*[\'"](?=[^"\']*(?:\s' . $esc . '|' . $esc . '\s|\b' . $esc . '\b))[^\'"]*[\'"][^>]*>';
                } elseif ($prev === null && $next === '.') {
                    $reg .= 'class\s*=\s*[\'"](?=[^"\']*(?:\s' . $esc . '|' . $esc . '\s|\b' . $esc . '\b))';
                } elseif ($prev === '.' && $next === '.') {
                    $reg .= '(?=[^"\']*(?:\s' . $esc . '|' . $esc . '\s))';
                } elseif ($prev === '.' && $next === ' ') {
                    $reg .= '(?=[^"\']*(?:\s' . $esc . '|' . $esc . '\s|\b' . $esc . '\b))(?:[^\'"]+)?[\'"][^>]*>';
                } elseif ($prev === ' ' && $next === false) {
                    $reg .= 'class\s*=\s*[\'"](?=[^"\']*(?:\s' . $esc . '|' . $esc . '\s|\b' . $esc . '\b))(?:[^\'"]+)?[\'"][^>]*>';
                } elseif ($prev === ' ' && $next === '.') {
                    $reg .= 'class\s*=\s*[\'"](?=[^"\']*(?:\s' . $esc . '|' . $esc . '\s|\b' . $esc . '\b))';
                } elseif ($prev === '.' && $next === false) {
                    $reg .= '(?=[^"\']*(?:\s' . $esc . '|' . $esc . '\s|\b' . $esc . '\b))(?:[^\'"]+)?[\'"]';
                } elseif ($prev === null && $next === false) {
                    $reg .= 'class\s*=\s*[\'"][^\'"]*' . $esc . '(?:[^\'"]+)?[\'"][^>]*>';
                } elseif ($prev === '#' && $next === ' ') {
                    $reg .= '.*?class\s*=\s*[\'"](?=[^"\']*(?:\s' . $esc . '|' . $esc . '\s|\b' . $esc . '\b))(?:[^\'"]+)?[\'"][^>]*>';
                }

            } elseif ($char === ' ') {
                $next_token = self::next_token($selector, $pos + 1);
                $token      = $next_token;
                $next       = $next_token['char'] ?? false;

                if (!empty($reg)) {
                    if (preg_match("/$reg/", $this->html)) {
                        $prev = $char;
                        $reg  = '';
                    } else {
                        return false;
                    }
                }
            } elseif ($char === '#') {
                $next_token = self::next_token($selector, $pos + 1);
                $next       = $next_token['char'] ?? false;
                $length     = ($next_token['pos'] ?? strlen($selector)) - $pos;
                $id         = substr($selector, $pos + 1, $length - 1);
                $token      = $next_token;

                $id = preg_replace('/\\\\(.)/u', '$1', $id);

                if (strpos($id, '\[') === false && strpos($id, '[') !== false) {
                    preg_match_all('/\[((?:[^]]+)?)/', $id, $attr_matches);
                    $id = preg_replace("/\[[^>\]]+]/", '', $id);
                    foreach ($attr_matches[1] as $match) {
                        $match     = explode('=', $match)[0];
                        $match     = preg_quote($match, '/');
                        $match_att = '<(?:[^>]+)?' . $match . '(?:[^>]+)?>';
                        if (!preg_match("/$match_att/", $this->html)) return false;
                    }
                }

                $esc = preg_quote($id, '/');

                if ($prev === null && $next === false) {
                    $reg .= 'id\s*=\s*[\'"][^\'"]*' . $esc . '(?:[^\'"]+)?[\'"][^>]*>';
                } elseif ($prev === null && $next === '.') {
                    $reg .= 'id\s*=\s*[\'"][^\'"]*' . $esc . '[\'"]';
                } elseif ($prev === null && $next === ' ') {
                    $reg .= 'id\s*=\s*[\'"][^\'"]*' . $esc . '(?:[^\'"]+)?[\'"][^>]*>';
                } elseif ($prev === ' ' && $next === false) {
                    $reg .= 'id\s*=\s*[\'"][^\'"]*' . $esc . '(?:[^\'"]+)?[\'"][^>]*>';
                } elseif ($prev === ' ' && $next === '.') {
                    $reg .= 'id\s*=\s*[\'"][^\'"]*' . $esc . '(?:[^\'"]+)?[\'"]';
                }

            } elseif (in_array($char, ['>', '+', '~'], true)) {
                return true;
            } else {
                break;
            }

            $prev = $char;
        }

        if ($starts_with_tag) {
            $reg = '<' . preg_quote($tag_name, '/') . '\\b[^>]*>';
        }

        if (!empty($herahicle_match)) {
            if (!empty($reg)) $herahicle_match[] = $reg;
            $last_pos_h = 0;
            foreach ($herahicle_match as $i => $r) {
                preg_match("/$r/", $this->html, $m);
                if (!empty($m[0])) {
                    $strpos = strpos($this->html, $m[0]);
                    if ($strpos === false) return false;
                    if ($last_pos_h > 0 && $strpos < $last_pos_h) return false;
                    $last_pos_h = $strpos;
                    if ($last_pos_h > 0 && $strpos > $last_pos_h && ($i + 1) === count($herahicle_match)) {
                        return true;
                    }
                } else {
                    return false;
                }
            }
        }

        return (bool) preg_match("/$reg/", $this->html);
    }

    // -------------------------------------------------------------------------
    // CSS block extraction
    // -------------------------------------------------------------------------

    private function extract_used_css(string $css, array $selectors): string {
        $css = preg_replace('/\/\*[\s\S]*?\*\//', '', $css);
        $css = preg_replace('/@font-face\s*\{(?:[^{}]+|\{[^}]*\})*\}/is', '', $css);
        $css = preg_replace('/@charset\s+["\'][^"\']+["\']\s*;/i', '', $css);
        $css = preg_replace('/@import\s+(?:url\s*\(\s*["\']?[^)]+["\']?\s*\)|["\'][^"\']+["\'])\s*[^;]*;?/i', '', $css);

        $css = preg_replace('/[\'"]\s*}\s*[\'"]/', 'bwpclosingcurlybracket', $css);
        $css = preg_replace('/[\'"]\s*{\s*[\'"]/', 'bwpopeningcurlybracket', $css);

        preg_match_all('/([^{}]+)\{([^{}]+)\}/', $css, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        $removals = [];

        foreach ($matches as $match) {
            $block = trim($match[0][0]);
            if (empty($block) || preg_match('/^@/', $block)) continue;

            $block_selectors      = trim(explode('{', $block)[0]);
            $individual_selectors = $this->split_selector_by_comma($block_selectors);
            $keep_block           = false;
            $used_selectors       = [];

            foreach ($individual_selectors as $sel) {
                $sel = trim($sel);
                if ($this->is_selector_used($sel, $selectors, $this->js_classes)) {
                    $used_selectors[] = $sel;
                    $keep_block       = true;
                }
            }

            if ($keep_block) {
                $new_selector_str = implode(', ', $used_selectors);
                $new_block        = str_replace($block_selectors, $new_selector_str, $block);
                $removals[]       = ['offset' => $match[0][1], 'length' => strlen($match[0][0]), 'replace' => $new_block];
            } else {
                $removals[]       = ['offset' => $match[0][1], 'length' => strlen($match[0][0])];
            }
        }

        for ($i = count($removals) - 1; $i >= 0; $i--) {
            $r   = $removals[$i];
            $css = substr_replace($css, $r['replace'] ?? '', $r['offset'], $r['length']);
        }

        $css = preg_replace('/@+(?=[.#\[])/i', '', $css);
        $css = str_replace('bwpclosingcurlybracket', '"}"', $css);
        $css = str_replace('bwpopeningcurlybracket', '"{"', $css);

        return $css;
    }

    private function minify_css(string $css): string {
        $css = str_replace(["\r\n", "\r", "\n", "\t"], '', $css);
        $css = preg_replace('/\s+/', ' ', $css);
        return trim($css);
    }

    private function split_selector_by_comma(string $selector): array {
        $parts   = [];
        $current = '';
        $depth   = 0;

        for ($i = 0; $i < strlen($selector); $i++) {
            $char = $selector[$i];
            if ($char === '(' || $char === '[') {
                $depth++;
                $current .= $char;
            } elseif ($char === ')' || $char === ']') {
                $depth--;
                $current .= $char;
            } elseif ($char === ',' && $depth === 0) {
                if (trim($current) !== '') $parts[] = trim($current);
                $current = '';
            } else {
                $current .= $char;
            }
        }

        if (trim($current) !== '') $parts[] = trim($current);
        return $parts;
    }
}
