# Berq Used CSS

Make your WordPress site load faster by removing unused CSS. Berq Used CSS automatically strips bloated stylesheets down to only what each page actually needs.

Built by the team behind [BerqWP](https://berqwp.com/), the all in one WordPress performance plugin.

## Why

Every WordPress theme and plugin loads CSS for every possible feature. Menus, sliders, popups and forms all ship their styles on every page, even when the page uses none of them. That extra CSS is render blocking and drags down your PageSpeed score.

Berq Used CSS scans each page, works out which CSS rules are actually used, and serves a trimmed version to every visitor after that.

## Features

* 100% free. No account, no sign up, and no data sent to external servers.
* Generates used CSS automatically for every page on its first visit.
* Three stylesheet loading modes: keep the originals, load them after user interaction, or remove them entirely.
* Exclude any URL or CSS selector you want to keep untouched.
* Optionally strip `@font-face` rules and background images from the generated CSS.
* Regenerates automatically when you save a post, update a theme or plugin, or make changes in the Customizer or Elementor.
* Works alongside WP Rocket, LiteSpeed Cache, W3 Total Cache, WP Super Cache, SiteGround Optimizer, Nginx Helper, BerqWP and more.
* Works with any theme or page builder, including Elementor, Divi, WPBakery, Gutenberg and WooCommerce.

## How it works

1. A visitor lands on a page for the first time. The plugin analyses the page and generates a lean version of its CSS before the response is sent.
2. That visitor and everyone after them get the smaller CSS, so the page loads faster.

Generated CSS is stored gzipped in `wp-content/cache/berq-used-css/`. Logged in users and URLs with query parameters are skipped.

## Requirements

* WordPress 5.0 or later
* PHP 7.4 or later

## Installation

1. Download or clone this repository into `wp-content/plugins/berq-used-css`.
2. Activate **Berq Used CSS** from the Plugins screen.
3. Visit your site. Used CSS is generated as each page is first visited. No configuration is needed.

Optional settings live under **Settings → Berq Used CSS**, where you can pick a stylesheet loading mode, add exclusions or clear the cache.

## Development

Dependencies are bundled with [PHP-Scoper](https://github.com/humbug/php-scoper) under the `BerqUsedCSS\Deps` namespace to avoid conflicts with other plugins. The scoped output lives in `build/vendor/`.

```bash
composer install
php-scoper add-prefix --output-dir=build/vendor
composer dump-autoload --working-dir=build
```

Run the tests with PHPUnit:

```bash
vendor/bin/phpunit
```

## More from BerqWP

* [BerqWP](https://berqwp.com/) is an all in one WordPress performance plugin.
* [Berq Markdown](https://wordpress.org/plugins/berq-markdown/) generates markdown versions of your pages for AI agents.

## License

GPL 2.0 or later. See [gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html).
