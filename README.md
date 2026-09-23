# Disable Automatic Slug Redirects

[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-blue.svg)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-7.2%2B-indigo.svg)](https://www.php.net)
[![GitHub Repository](https://img.shields.io/badge/GitHub-imuxmantayyab%2Fdisable--automatic--slug--redirects-black.svg?logo=github)](https://github.com/imuxmantayyab/disable-automatic-slug-redirects)

A lightweight, powerful WordPress plugin that prevents WordPress from automatically creating 301 redirects when you change post, page, or custom post type slugs. Stops unwanted `-2`, `-3` slug suffixing, eliminates SEO redirect chains, and protects your search engine crawl budget.

---

## 📖 User Story & Why We Built This Plugin

> ### 👤 **The User Story**
> **As an** SEO specialist, developer, and WordPress site owner,  
> **I want** full control over permalinks when editing or deleting content, without WordPress automatically redirecting old slugs or saving them in the database,  
> **So that** I can prevent nasty `-2`, `-3` slug suffixing, stop wasteful redirect chains, save valuable search engine crawl budget, and allow deprecated URLs to return a clean HTTP 404 status.

---

### 🚨 The Real-World Problem We Faced

In WordPress core, whenever you edit or change the slug of a post, page, or custom post type:

1. **WordPress silently records the old slug** in the database (`wp_postmeta` table under the meta key `_wp_old_slug`).
2. **WordPress automatically issues a 301 permanent redirect** via `wp_old_slug_redirect()` from the old URL to the new URL.
3. **The Slug Conflict Nightmare (`/test` ➡️ `/test-2` ➡️ `/test-3`):**
   - Suppose you had an old post at `example.com/test` and you change its slug to `example.com/test-updated`.
   - Now, you try to publish a brand-new post and assign it the clean URL `example.com/test`.
   - **WordPress refuses!** Because `test` is still lingering in `_wp_old_slug`, WordPress automatically alters your new post's slug to `example.com/test-2`.
   - If that happens again, it becomes `example.com/test-3`, `example.com/test-4`, etc.
4. **Disastrous Redirect Chains:**
   - As slugs get renamed and reassigned, WordPress creates multi-hop redirect chains:  
     `/test` ➡️ `/test-2` ➡️ `/test-3`
5. **Aggressive 404 Guessing:**
   - WordPress's built-in `redirect_guess_404_permalink()` actively guesses URLs when someone hits a 404, redirecting users and bots to completely unrelated posts that happen to share partial characters.

---

### 📉 Why This Severely Hurts Your SEO

* **Wasted Crawl Budget:** Search engine bots (Googlebot, Bingbot) have a finite crawl budget for your domain. Crawlers waste their time navigating chained 301 redirects instead of indexing your fresh, revenue-generating content.
* **Loss of Link Equity & Page Speed:** Redirect chains dilute link equity and introduce latency hops, negatively impacting Core Web Vitals and user experience.
* **Zombie URLs:** Deprecated URLs never return a clean `404 Not Found` or `410 Gone`. Search engines keep old URLs in their index indefinitely instead of de-indexing them.
* **Cluttered Database:** The `wp_postmeta` table fills up with thousands of stale `_wp_old_slug` records that cause performance bottlenecks on high-traffic sites.

---

## 💡 The Solution: How This Plugin Fixes It

**Disable Automatic Slug Redirects** completely halts this behavior at the core level:

| Feature | Default WordPress Behavior | With This Plugin Active |
|---|---|---|
| **Old Slug Recording** | Silently saves old slug to `wp_postmeta` (`_wp_old_slug`) | **Completely blocked** via `add_post_metadata` & hook suppression |
| **Old Slug Redirect** | Forces 301 redirect to new URL | **Disabled**; old URL serves a clean **HTTP 404** |
| **Reusing Clean Slugs** | Forces `-2`, `-3` suffixes (`/test-2`, `/test-3`) | **Fully clean permalinks** (`/test` can be cleanly reused) |
| **404 Permalink Guessing** | Guesses and redirects to similar partial URLs | **Disabled** via `do_redirect_guess_404_permalink` |
| **Canonical 404 Protection** | May attempt canonical redirection on 404s | **Cancelled** so 404 pages stay 404 |
| **Database Clutter** | Thousands of old slug rows remain forever | **One-Click Clean Tool** to purge all historical `_wp_old_slug` records |
| **Parent/Child Pages** | Broken child paths or orphan chains | **Smart Child Page Redirection** (child pages follow moved parents) |

---

## ✨ Key Features

- 🛑 **Stops `_wp_old_slug` Creation:** Prevents WordPress from saving historical slug metadata when posts/pages are edited.
- 🚫 **Disables Automatic Redirects:** Blocks `wp_old_slug_redirect()`, allowing deprecated URLs to return a normal HTTP 404.
- 🎯 **Eliminates `-2`, `-3` Suffixing:** Never get stuck with unwanted numbers appended to your URLs.
- 🛡️ **Preserves Crawl Budget:** Prevents redirect chains and redirects loops that drain Googlebot and Bingbot crawl capacity.
- 🧹 **One-Click Database Purge:** Easily clean up historical `_wp_old_slug` rows left behind by previous WordPress versions.
- ⚙️ **Granular Post Type Control:** Choose specifically which content types are affected (Posts, Pages, Products, Portfolio, or any Custom Post Type).
- 🌲 **Smart Parent-Child Support:** When a parent page is redirected, child pages automatically route to their new relative parent destination.
- ⚡ **Zero Bloat & Blazing Fast:** Built with native WordPress hooks and zero external dependencies or heavy assets.

---

## 🚀 Installation

### Option 1: Via WordPress Admin
1. Download the latest `.zip` release from the [GitHub Releases](https://github.com/imuxmantayyab/disable-automatic-slug-redirects/releases) page.
2. In your WordPress admin, navigate to **Plugins → Add New → Upload Plugin**.
3. Choose the downloaded `.zip` file and click **Install Now**.
4. Click **Activate Plugin**.

### Option 2: Manual Installation via FTP / Git
1. Clone or copy the plugin into your WordPress plugins directory:
   ```bash
   cd wp-content/plugins/
   git clone https://github.com/imuxmantayyab/disable-automatic-slug-redirects.git
   ```
2. In your WordPress dashboard, navigate to **Plugins** and activate **Disable Automatic Slug Redirects**.

---

## ⚙️ Configuration & Usage

Once activated, go to **Settings → Slug Redirects** in your WordPress dashboard:

1. **Enable Plugin:** Toggle the master switch to activate or deactivate the suppression logic.
2. **Target Post Types:** Select which post types you want to protect (e.g., Posts, Pages, WooCommerce Products, or custom post types).
3. **Smart Child Redirection:** Toggle whether nested child pages should automatically follow when parent pages are redirected.
4. **Clean Historical Slugs:** Click the **Clean Old Slugs** button to wipe out all legacy `_wp_old_slug` entries from your `wp_postmeta` database table.
5. **Advanced Options:** Optionally disable WordPress's core `redirect_canonical()` entirely (recommended only for advanced users with specific architectural needs).

> [!TIP]
> **Browser Caching Advisory:**  
> Web browsers aggressively cache HTTP 301 redirects locally. When testing slug changes, always use an **Incognito / Private Window** or inspect network headers using Developer Tools (`Ctrl+Shift+I` or `Cmd+Option+I`) with "Disable cache" checked.

---

## 🧩 Compatibility

The plugin is fully compatible with:
- **SEO Plugins:** Rank Math, Yoast SEO, All in One SEO, SEOPress.
- **Redirection Plugins:** Redirection, Safe Redirect Manager.
- **Page Builders & Editors:** Gutenberg (Block Editor), Classic Editor, Elementor, Divi, Bricks, Beaver Builder.
- **E-Commerce:** WooCommerce products, custom taxonomies, and custom post types.

---

## 📚 Technical Documentation

For in-depth technical documentation, hooks intercepted, architectural flowcharts, and database query details, please refer to [DOCUMENTATION.md](DOCUMENTATION.md).

---

## 👨‍💻 Author & Repository

- **Author:** [Usman Tayyab](https://www.linkedin.com/in/imuxmantayyab/)
- **GitHub Repository:** [https://github.com/imuxmantayyab/disable-automatic-slug-redirects](https://github.com/imuxmantayyab/disable-automatic-slug-redirects)

## 📄 License

This project is open-source software licensed under the [GNU General Public License v2.0 or later](https://www.gnu.org/licenses/gpl-2.0.html).
