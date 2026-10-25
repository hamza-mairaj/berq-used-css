=== Berq Used CSS - Remove Unused CSS Automatically ===
Contributors: berqwp, thevisionofhamza
Tags: performance, speed, used css, optimization, page speed
Requires at least: 5.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.19
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Make your website load faster by removing unused CSS. Automatically strips bloated stylesheets down to only what each page actually needs.

== Description ==

Every WordPress theme and plugin loads CSS for every possible feature - menus, sliders, pop-ups, forms, even on pages that don't use any of them. All that extra CSS slows your site down and hurts your Google PageSpeed score.

**Berq Used CSS fixes this automatically.**

It scans each page of your website, figures out exactly which CSS rules are actually used, and saves a trimmed-down version. From that point on, visitors get only the CSS they need - nothing more. Pages load faster, your PageSpeed score goes up, and you didn't have to touch a single line of code. It works with every WordPress theme, plugin, and page builder right out of the box.

**How it works:**

1. A visitor lands on a page for the first time - the plugin analyses the page and generates a lean version of its CSS before the page finishes loading.
2. That same visitor, and everyone after, gets the smaller CSS file, and the page loads noticeably faster.

**Features:**

* 100% free - no account, no sign-up, and no data sent to external servers
* Automatically generates used CSS for every page on first visit.
* Three stylesheet loading modes: keep originals, load them after interaction, or remove them entirely
* Exclude any URL or CSS selector you want to keep untouched
* Strip web font declarations and background images from the generated CSS to save even more weight
* Updates automatically when you save a post, update a plugin or theme, or change your design in the Customizer.
* Works alongside cache plugins like WP Rocket, LiteSpeed Cache, W3 Total Cache, WP Super Cache, SiteGround Optimizer, Nginx Helper and more
* Works with every WordPress theme and plugin - no compatibility issues

== Other Products ==

Also check out our other WordPress products.

- [BerqWP](https://berqwp.com/) – All-in-One WordPress performance plugin.
- [WP Replai](https://wpreplai.com/) – The skills-powered AI customer support agent that lives inside your site, answering questions, walking visitors through fixes, qualifying leads, and only escalating to your team when it truly should.
- [ElizaWP](https://elizawp.com/) – SEO MCP for AI agents that researches, writes and publishes for WordPress.
- [Berq Markdown](https://wordpress.org/plugins/berq-markdown/) – Automatically generate markdown version of web pages for AI agents.
- [CanvoWP](https://canvowp.com/) – Completely free Agent-first website builder plugin. No blocks, no bloat.
- [Camouflage Security](https://wordpress.org/plugins/camouflage/) – Static WordPress Firewall and PHP Isolation.
- [Barlapse](https://wordpress.org/plugins/barlapse/) – Fix admin bar overflow issue in WordPress admin panel.
- [Seenli Blocks](https://wordpress.org/plugins/seenli-blocks/) – Conditional display blocks for logged in users & user roles.

== Installation ==

1. Upload the plugin to your site via **Plugins → Add New → Upload Plugin**, or search for "Berq Used CSS" in the plugin directory.
2. Activate the plugin.
3. Visit your website - used CSS generates automatically as each page is first visited. No configuration needed to get started.

Optional: go to **Settings → Berq Used CSS** to choose a stylesheet loading mode, exclude specific pages, or clear the cache.

== Frequently Asked Questions ==

= Do I need to create an account or sign up for anything? =

No. Berq Used CSS works entirely on your own website. There is no account to create, no sign-up required, and no data is sent to any external server.

= Will this break my website's design? =

No. The very first visit to any page generates the used CSS and serves it on that same visit - your layout and styling stay exactly as intended, just with the unused CSS stripped out.

= How long does it take to generate used CSS for my pages? =

Generation happens during the first visit to a page, before the page finishes loading. That first visit may take a little longer while the plugin analyses the page, and every visit after that - including that same one - gets the trimmed CSS.

= What happens when I update my theme or a plugin? =

The plugin automatically clears the saved CSS and re-generates it. This happens whenever you save a post, update a theme or plugin, make changes in the Customizer, or clear cache in Elementor.

= Which stylesheet loading mode should I choose? =

Start with **Load immediately** (the default) - this already gives you a faster page by serving lean CSS. If you want the maximum speed boost, try **Load after interaction**, which delays the original stylesheets until the visitor scrolls or clicks. Use **Remove completely** only if you are confident the generated used CSS covers everything your pages need.

= Does it work with my theme and plugins? =

Yes - Berq Used CSS works with every WordPress theme and plugin without any configuration. It reads the CSS that your pages actually output, so it doesn't matter whether you use Elementor, Divi, WPBakery, Gutenberg, WooCommerce, or any other tool. If your page loads in a browser, the plugin can generate used CSS for it. When you save changes in Elementor or switch themes, the saved CSS is automatically cleared and re-generated.

= Will it affect my Google PageSpeed / Core Web Vitals score? =

Yes - removing unused CSS directly reduces the amount of render-blocking CSS the browser has to process, which improves First Contentful Paint (FCP) and other Core Web Vitals metrics.

== Screenshots ==

1. Settings page

== Changelog ==

= 1.0.19 =
* Fixed is_used_selector always returning true for first element of the selector.
* Fixed nested empty @media blocks being added in used CSS.

= 1.0.18 =
* Tested with WordPress 7.1
* Disabled used css generation for logged in users

= 1.0.17 =
* Show the number of pages with cached used CSS in Cache Management

= 1.0.16 =
* Generate used CSS synchronously during the triggering page load (blocking) instead of after the response, so the same visitor that triggers generation receives the trimmed CSS on that visit

= 1.0.15 =
* Generate used CSS immediately after the triggering page load instead of via background queue and AJAX heartbeat polling
* Remove the queue/heartbeat system and related admin "Queue Status" UI
* Harden query-parameter exclusion so pages with query strings are never processed or cached, independent of caller

= 1.0.14 =
* Update readme

= 1.0.13 =
* Use wp_enqueue APIs for JS and CSS output instead of raw echo
* Escape inline CSS output via wp_strip_all_tags
* Fix unclosed output buffer - explicitly closed on shutdown
* Fix protocol-relative URL path resolution in CSS extractor
* Skip pages with query parameters from CSS generation
* Invalidate used CSS on post save, theme/plugin update, Customizer, and Elementor changes
* Add BerqWP cache compatibility hooks
* Speed up heartbeat - first beat fires in 1s, base interval reduced to 3s

= 1.0.12 =
* Update voku/simple_html_dom library version

= 1.0.11 =
* WP Repo technical improvements

= 1.0.0 =
* Initial release.
* Automatic used CSS generation in the background for every page.
* Three stylesheet loading modes: immediate, defer until interaction, remove.
* Exclude specific URLs and CSS selectors.
* Option to strip @font-face rules and background images.
* Compatible with major caching plugins and BerqWP.
* Automatic cache invalidation on post save, theme/plugin update, Customizer save, and Elementor changes.
