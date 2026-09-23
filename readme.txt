=== Disable Automatic Slug Redirects ===
Contributors: muxmantayyab
Tags: slug, redirect, permalinks, 404, seo
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.2
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Prevent WordPress from automatically redirecting old URLs after changing page, post, or custom post type slugs. Stops unwanted -2, -3 slug suffixing, redirect chains, and wasted crawl budget.

== Description ==

= The Problem: Why We Built This Plugin (User Story) =

As SEO professionals, developers, and website owners, we repeatedly faced a frustrating WordPress problem:
When you edit or rename a post or page slug (for instance, changing `/test/`), WordPress automatically stores the old slug in `_wp_old_slug` and forces a 301 redirect. 

Then, whenever you want to publish a fresh new post or restore the original clean URL (`example.com/test`), WordPress refuses to let you use that slug cleanly! Because the old slug is cached in history, WordPress forces the new slug to become `example.com/test-2`, then `example.com/test-3`, and so on. 

Even worse, WordPress creates chained automatic redirects (`/test` -> `/test-2` -> `/test-3`) and aggressively guesses 404 URLs (`redirect_guess_404_permalink`), redirecting visitors and search engine bots to unintended pages.

**This is disastrous for SEO:**
* **Wastes Search Engine Crawl Budget**: Googlebot and Bingbot waste their crawl limits traversing endless redirect chains and auto-guessed URLs rather than indexing your important pages.
* **Forces Ugly Slugs**: Your URLs get polluted with `-2`, `-3`, `-4` suffixes instead of clean, professional, SEO-friendly permalinks.
* **Unwanted Redirect Chains**: Instead of a clean HTTP 404 letting search engines drop deprecated URLs from the index, WordPress keeps zombie redirects alive indefinitely.

**The Solution:**
**Disable Automatic Slug Redirects** completely solves this problem:
1. It stops WordPress from ever writing `_wp_old_slug` to your database.
2. It disables automatic 301 redirects on edited slugs so old URLs return a clean HTTP 404.
3. It disables aggressive 404 guessing, preventing WordPress from redirecting users to random similar pages.
4. It frees up your original URLs (`/test`) so you can reuse them without WordPress forcing `-2` or `-3`.
5. It provides a one-click database cleanup tool to delete all legacy `_wp_old_slug` entries.

= Key Features =

* Stops WordPress from writing `_wp_old_slug` post meta for the post types you choose.
* Stops `wp_old_slug_redirect()` from redirecting old URLs for those post types, so they 404 as expected.
* Stops WordPress from guessing 404 URLs (`redirect_guess_404_permalink`), preventing automatic redirects on pages or similar slugs.
* Blocks canonical redirects on any request that resolves to a 404.
* Smart Parent & Child Redirection: When a parent page URL is redirected (e.g. via Rank Math, Redirection plugin, or WordPress page hierarchy), all nested child pages automatically redirect to their new parent location.
* Lets you scope the behavior to Posts, Pages, and/or any public custom post type individually.
* Built-in one-click tool to clean up historical `_wp_old_slug` entries from your database.
* Optional advanced setting to disable WordPress's core `redirect_canonical()` handler entirely (use with care —
  this also affects unrelated canonical redirects such as trailing slashes and pagination).
* No core files are modified. Everything is done through standard WordPress hooks and filters.
* Compatible with Rank Math, Redirection, Classic Editor, Gutenberg, Elementor, and WooCommerce.

= Settings =

After activation, go to **Settings → Slug Redirects** to:

1. Enable or disable the plugin.
2. Choose whether it applies to Posts, Pages, and/or specific custom post types.
3. Toggle automatic child page redirection when parent pages are redirected.
4. Clean up historical old slug records from the database with one click.
5. Optionally disable core canonical redirects (Advanced section).

= Example =

Before activating:

* Old URL `/about-us/` was renamed to `/about-company/`.
* Visiting `/about-us/` redirects (301) to `/about-company/`.

After activating (with Pages enabled):

* Visiting `/about-us/` now returns a 404.
* Visiting `/about-company/` continues to work normally.

== Installation ==

1. Upload the `disable-automatic-slug-redirects` folder to `/wp-content/plugins/`.
2. Activate the plugin through the "Plugins" screen in WordPress.
3. Visit **Settings → Slug Redirects** to configure which content types are affected.

== Frequently Asked Questions ==

= Does this delete existing `_wp_old_slug` meta from past slug changes? =

The plugin provides a dedicated button in **Settings → Slug Redirects** to delete all historical `_wp_old_slug` entries from your database whenever you wish. Even without purging, the plugin completely blocks WordPress from redirecting on those existing records.

= Why does my browser still redirect after changing a slug? =

Web browsers cache HTTP 301 (Permanent) redirects very aggressively in local disk cache. If you visited the old URL before activating the plugin or changing the slug, test using an Incognito / Private browsing window or clear your browser cache.

= Will this break my SEO redirects? =

Only for the content types you explicitly enable it for. If you rely on old-slug redirects for SEO on any post
type, simply leave that post type unchecked in the settings screen.

= Does the "disable canonical redirects" option only affect old slugs? =

No — that Advanced option removes WordPress's entire `redirect_canonical()` handler, which also manages
trailing-slash normalization, pagination redirects, and similar canonical URL behavior. It is off by default and
should only be enabled if you understand and accept that trade-off.

== Changelog ==

= 1.0.1 =
* Fixed: Completely prevent 404 permalink guessing (`redirect_guess_404_permalink`) so pages and posts never redirect old URLs to new ones.
* Fixed: Cancel canonical redirects whenever a request resolves to 404.
* Fixed: Prevent `_wp_old_slug` from being written on post updates.
* Fixed: Send anti-cache headers on 404 pages to prevent browser redirect caching.
* Added: One-click historical old slug database cleanup tool in admin settings.
* Added: Browser 301 caching advisory notice in settings screen.

= 1.0.0 =
* Initial release.
