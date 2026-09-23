# Technical Documentation: Disable Automatic Slug Redirects

**GitHub Repository:** [https://github.com/imuxmantayyab/disable-automatic-slug-redirects](https://github.com/imuxmantayyab/disable-automatic-slug-redirects)  
**Author:** [Usman Tayyab](https://www.linkedin.com/in/imuxmantayyab/)  
**Version:** 1.0.1  
**License:** GPL-2.0-or-later  

---

## Table of Contents
1. [User Story & Problem Statement](#1-user-story--problem-statement)
2. [Deep Dive: The Core WordPress Issue](#2-deep-dive-the-core-wordpress-issue)
   - [How WordPress Handles Slug Changes by Default](#how-wordpress-handles-slug-changes-by-default)
   - [The Slug Suffixing Collision (-2, -3, -4)](#the-slug-suffixing-collision--2--3--4)
   - [SEO Impact & Crawl Budget Depletion](#seo-impact--crawl-budget-depletion)
3. [Architecture & System Flow](#3-architecture--system-flow)
4. [Hooks & Filters Intercepted](#4-hooks--filters-intercepted)
5. [Smart Parent & Child Redirection Engine](#5-smart-parent--child-redirection-engine)
6. [Database Operations & Cleanup Utility](#6-database-operations--cleanup-utility)
7. [Testing & Verification Protocol](#7-testing--verification-protocol)
8. [Frequently Asked Technical Questions](#8-frequently-asked-technical-questions)

---

## 1. User Story & Problem Statement

### 🎯 User Story
> **As an** SEO strategist, web architect, or developer managing a high-performing WordPress site,  
> **I want** WordPress to completely stop remembering old slugs and creating automatic 301 redirects whenever I edit, rename, or retire post URLs,  
> **So that** I can reuse clean slugs (avoiding forced `-2` and `-3` suffixes), prevent messy redirect chains, conserve search engine crawl budget, and allow deprecated URLs to serve standard HTTP 404 status codes.

---

## 2. Deep Dive: The Core WordPress Issue

### How WordPress Handles Slug Changes by Default
In WordPress core (`wp-includes/post.php`), whenever a post or page is updated:
1. The hook `post_updated` calls `wp_check_for_changed_slugs( $post_id, $post, $post_before )`.
2. If `post_name` has changed, WordPress executes:
   ```php
   add_post_meta( $post_id, '_wp_old_slug', $post_before->post_name );
   ```
3. Whenever a visitor or bot visits the old URL:
   - WordPress detects a 404.
   - Core triggers `wp_old_slug_redirect()` inside `wp-includes/canonical.php`.
   - Core runs a database query looking for `_wp_old_slug = 'old-slug'`.
   - When found, WordPress issues an HTTP 301 Permanent Redirect to the new post URL.

### The Slug Suffixing Collision (`-2`, `-3`, `-4`)
When an editor or SEO wants to launch a new, fresh page using an old slug name:
1. Suppose an old post was at `/test/` and was renamed to `/test-archive/`.
2. A new marketing campaign is created, and the editor sets the permalink to `/test/`.
3. WordPress runs `wp_unique_post_slug()` in `wp-includes/post.php`.
4. WordPress checks if `/test/` exists in `post_name` OR in historical `_wp_old_slug` postmeta entries.
5. Finding `/test/` in history, **WordPress forcefully suffixes the new post to `/test-2/`**.
6. If the page is updated or duplicated again, WordPress escalates the suffixing to `/test-3/`, `/test-4/`, etc.
7. Furthermore, visiting `/test/` now redirects to `/test-archive/` instead of displaying the new post, causing serious content routing bugs.

### SEO Impact & Crawl Budget Depletion
1. **Crawl Budget Exhaustion:**  
   Googlebot and Bingbot allocate a limited crawl budget per site based on authority and server response time. When crawlers hit endless 301 chains (`/test` ➡️ `/test-2` ➡️ `/test-3`), they consume crawl allocations on useless redirects rather than discovering and indexing fresh pages.
2. **Redirect Loops & Chains:**  
   When slugs are reused or moved back and forth during site migrations, circular redirects (`/test` 🔁 `/test-2`) frequently occur, breaking browser access and dropping rankings.
3. **Loss of HTTP 404 Hygiene:**  
   In healthy technical SEO, removed or obsolete URLs should return `404 Not Found` or `410 Gone` so search engines cleanly remove them from the index. WordPress's automatic redirect behavior keeps defunct pages alive as "ghost redirects" indefinitely.
4. **Aggressive 404 Guessing:**  
   WordPress's `redirect_guess_404_permalink()` tries to match partial strings. If a user visits `/services-xyz` (which should 404), WordPress may arbitrarily redirect them to `/services/`, creating false signals and improper analytics data.

---

## 3. Architecture & System Flow

```
                     Incoming Request
                            │
                            ▼
              Does requested URL exist?
                   │                │
                 [YES]             [NO]
                   │                │
           Serve Standard Post      ▼
                            Is DASR Active?
                             │          │
                           [YES]       [NO]
                             │          │
    ┌────────────────────────┴────┐    WordPress executes:
    │ 1. Block `_wp_old_slug`     │    - wp_old_slug_redirect() (301)
    │ 2. Disable 404 guessing     │    - redirect_guess_404_permalink()
    │ 3. Cancel canonical 404     │    - redirects to guessed/old slug
    │ 4. Send no-cache headers    │
    └─────────────────────────────┘
                   │
                   ▼
       Is Child Page of Redirected Parent?
                   │                │
                 [YES]             [NO]
                   │                │
            Redirect child          ▼
         to new parent path    Return Clean
                                 HTTP 404
```

---

## 4. Hooks & Filters Intercepted

The plugin intercepts WordPress core at 7 strategic layers without modifying any core files:

### 1. `post_updated` & `attachment_updated`
```php
remove_action( 'post_updated', 'wp_check_for_changed_slugs', 12 );
remove_action( 'attachment_updated', 'wp_check_for_changed_slugs', 12 );
```
- **Purpose:** Unhooks WordPress's native function that automatically writes `_wp_old_slug` when a post or attachment is saved.

### 2. `add_post_metadata` & `update_post_metadata`
```php
add_filter( 'add_post_metadata', array( $this, 'maybe_block_old_slug_meta' ), 10, 5 );
add_filter( 'update_post_metadata', array( $this, 'maybe_block_old_slug_meta' ), 10, 5 );
```
- **Purpose:** Short-circuits any attempt to add or update `_wp_old_slug` meta keys for the configured post types by returning `false`.

### 3. `do_redirect_guess_404_permalink` & `pre_redirect_guess_404_permalink`
```php
add_filter( 'do_redirect_guess_404_permalink', array( $this, 'maybe_block_guess_404_permalink' ) );
add_filter( 'pre_redirect_guess_404_permalink', array( $this, 'maybe_block_pre_guess_404_permalink' ) );
```
- **Purpose:** Halts WordPress from guessing permalinks when a 404 occurs, ensuring bad or legacy URLs do not redirect to random posts.

### 4. `old_slug_redirect_post_id` & `old_slug_redirect_url`
```php
add_filter( 'old_slug_redirect_post_id', array( $this, 'maybe_block_old_slug_redirect_post_id' ) );
add_filter( 'old_slug_redirect_url', array( $this, 'maybe_block_old_slug_redirect_url' ) );
```
- **Purpose:** If historical `_wp_old_slug` rows still exist in the database, these filters return `false`, neutralizing any legacy redirect lookups.

### 5. `redirect_canonical`
```php
add_filter( 'redirect_canonical', array( $this, 'maybe_block_canonical_redirect' ), 10, 2 );
```
- **Purpose:** If `is_404()` evaluates to true, canonical redirection is cancelled (`return false;`), preventing WordPress from attempting to rescue the 404 with a redirect.

### 6. `template_redirect` (Anti-Cache Headers)
```php
header( 'Cache-Control: no-cache, no-store, must-revalidate, max-age=0' );
header( 'Pragma: no-cache' );
header( 'Expires: Wed, 11 Jan 1984 05:00:00 GMT' );
```
- **Purpose:** Sends strict anti-caching HTTP headers on 404 pages so that browsers (such as Chrome and Safari) do not permanently cache stale 301 responses.

---

## 5. Smart Parent & Child Redirection Engine

When reorganizing hierarchical content (like WordPress Pages or hierarchical Custom Post Types):
- If `/parent-page/` is redirected (via Rank Math, Redirection plugin, or slug change), nested child pages like `/parent-page/child-page/` might normally return a broken 404.
- **DASR's Smart Child Redirection** checks ancestor paths. If an ancestor has been redirected, child pages automatically resolve to their new parent location:
  `/new-parent/child-page/`
- This ensures clean site restructuring without manual 1-by-1 redirect mapping for deeply nested pages.

---

## 6. Database Operations & Cleanup Utility

### The Cleanup SQL Query
To purge accumulated legacy `_wp_old_slug` entries safely from previous WordPress versions, the plugin executes:

```sql
DELETE pm FROM {$wpdb->postmeta} pm
INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
WHERE pm.meta_key = '_wp_old_slug'
  AND p.post_type IN ('post', 'page', ...);
```

### Safety Features
- Scoped strictly to user-selected post types (or all configured types).
- Verifies admin nonce (`dasr_clean_old_slugs_nonce`) and user permissions (`manage_options`).
- Displays the exact count of deleted records to the administrator.

---

## 7. Testing & Verification Protocol

### Verification via cURL (Recommended)
To verify that WordPress is returning a true `404` and no 301 redirect is issued:

```bash
# Check HTTP response header
curl -I https://yourwebsite.com/old-renamed-slug/
```

**Expected Output:**
```http
HTTP/2 404 
cache-control: no-cache, no-store, must-revalidate, max-age=0
pragma: no-cache
expires: Wed, 11 Jan 1984 05:00:00 GMT
```

### Verification in Browser
1. Open an **Incognito / Private Window** (to bypass local browser 301 disk cache).
2. Open DevTools (`F12`) ➡️ **Network** tab.
3. Check the **Disable cache** checkbox.
4. Request the old slug URL.
5. Verify the HTTP status code is `404 Not Found`.

---

## 8. Frequently Asked Technical Questions

#### Q: Why does my browser still redirect even after activating this plugin?
**A:** Browsers cache HTTP 301 redirects indefinitely on local disk. If you visited the URL when it was returning a 301 redirect, your browser never contacts the server again unless you clear your browsing data or test in an Incognito window.

#### Q: Can I use this with Rank Math or Redirection plugin?
**A:** Yes! When you want deliberate, managed redirects (such as marketing shortlinks or 301 migrations), you should manage them explicitly through Rank Math or Redirection. This plugin only prevents WordPress core from creating invisible, unwanted automatic redirects behind your back.

#### Q: Does this support Custom Post Types?
**A:** Yes, any registered public post type (such as WooCommerce `product`, Portfolio items, Events, etc.) appears automatically in **Settings → Slug Redirects**.
