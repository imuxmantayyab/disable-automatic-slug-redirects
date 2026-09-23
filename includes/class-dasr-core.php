<?php
/**
 * Core redirect / metadata suppression logic.
 *
 * @package Disable_Automatic_Slug_Redirects
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DASR_Core
 *
 * Hooks into WordPress to:
 *  - stop `_wp_old_slug` post meta from ever being written for the
 *    configured post types,
 *  - stop `wp_old_slug_redirect()` from issuing a redirect for those post
 *    types (so the request falls through to a normal 404), and
 *  - optionally disable WordPress's broader `redirect_canonical()` handler.
 */
class DASR_Core {

	/**
	 * Settings handler.
	 *
	 * @var DASR_Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param DASR_Settings $settings Settings handler instance.
	 */
	public function __construct( DASR_Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Register all WordPress hooks.
	 */
	public function init() {
		if ( ! $this->settings->is_enabled() ) {
			return;
		}

		// 1. Prevent WordPress from guessing permalinks for 404 requests (e.g. old slugs, partial slugs).
		add_filter( 'do_redirect_guess_404_permalink', array( $this, 'maybe_block_guess_404_permalink' ) );
		add_filter( 'pre_redirect_guess_404_permalink', array( $this, 'maybe_block_pre_guess_404_permalink' ) );

		// 2. Prevent canonical redirect from redirecting any request that resolved to 404.
		add_filter( 'redirect_canonical', array( $this, 'maybe_block_canonical_redirect' ), 10, 2 );

		// 3. Stop WordPress from saving `_wp_old_slug` post meta when posts are updated.
		remove_action( 'post_updated', 'wp_check_for_changed_slugs', 12 );
		remove_action( 'attachment_updated', 'wp_check_for_changed_slugs', 12 );
		add_filter( 'add_post_metadata', array( $this, 'maybe_block_old_slug_meta' ), 10, 5 );
		add_filter( 'update_post_metadata', array( $this, 'maybe_block_old_slug_meta' ), 10, 5 );

		// 4. Stop `wp_old_slug_redirect()` from acting on existing `_wp_old_slug` records.
		add_filter( 'old_slug_redirect_post_id', array( $this, 'maybe_block_old_slug_redirect_post_id' ) );
		add_filter( 'old_slug_redirect_url', array( $this, 'maybe_block_old_slug_redirect_url' ) );

		// 5. Send anti-cache headers on 404 so browsers do not cache old 301 redirects.
		add_action( 'template_redirect', array( $this, 'maybe_send_404_nocache_headers' ), 9 );

		// 6. Redirect child pages if parent or ancestor was redirected.
		if ( $this->settings->is_children_redirection_enabled() ) {
			add_action( 'template_redirect', array( $this, 'maybe_redirect_child_pages' ), 8 );
		}

		// 7. Optional advanced behavior: fully disable core's canonical redirect handler.
		if ( $this->settings->is_canonical_disabled() ) {
			remove_action( 'template_redirect', 'redirect_canonical' );
		}
	}

	/**
	 * Disable WordPress 404 permalink guessing.
	 *
	 * @param bool $do_redirect_guess Whether to attempt to guess a redirect URL for a 404.
	 * @return bool
	 */
	public function maybe_block_guess_404_permalink( $do_redirect_guess ) {
		if ( ! empty( $this->settings->get_target_post_types() ) ) {
			return false;
		}

		return $do_redirect_guess;
	}

	/**
	 * Short-circuit WordPress 404 permalink guessing.
	 *
	 * @param null|string|false $pre Pre-evaluated redirect URL.
	 * @return null|false
	 */
	public function maybe_block_pre_guess_404_permalink( $pre ) {
		if ( ! empty( $this->settings->get_target_post_types() ) ) {
			return false;
		}

		return $pre;
	}

	/**
	 * Cancel canonical redirect if the request is a 404.
	 *
	 * @param string|false $redirect_url  The redirect URL determined by WordPress.
	 * @param string       $requested_url The requested URL.
	 * @return string|false
	 */
	public function maybe_block_canonical_redirect( $redirect_url, $requested_url ) {
		unset( $requested_url );

		if ( is_404() ) {
			return false;
		}

		return $redirect_url;
	}

	/**
	 * Short-circuit add_post_meta() / update_post_meta() for `_wp_old_slug`.
	 *
	 * @param mixed  $check      The value to short-circuit with, or null to proceed normally.
	 * @param int    $object_id  Post ID the metadata is being added/updated for.
	 * @param string $meta_key   Metadata key being added.
	 * @param mixed  $meta_value Metadata value being added. Unused.
	 * @param mixed  $prev_value Previous value. Unused.
	 * @return mixed
	 */
	public function maybe_block_old_slug_meta( $check, $object_id, $meta_key, $meta_value = null, $prev_value = null ) {
		unset( $meta_value, $prev_value );

		if ( '_wp_old_slug' !== $meta_key ) {
			return $check;
		}

		if ( $this->post_type_is_targeted( get_post_type( $object_id ) ) ) {
			return false;
		}

		return $check;
	}

	/**
	 * Short-circuit old slug redirect by post ID before WordPress gets the permalink.
	 *
	 * @param int|false $id Post ID found for old slug.
	 * @return int|false
	 */
	public function maybe_block_old_slug_redirect_post_id( $id ) {
		if ( ! $id ) {
			return $id;
		}

		$post_type = get_post_type( $id );

		if ( $this->post_type_is_targeted( $post_type ) ) {
			return false;
		}

		return $id;
	}

	/**
	 * Filter the URL wp_old_slug_redirect() is about to redirect to. Returning
	 * an empty value stops the redirect, allowing the request to remain a 404.
	 *
	 * @param string $link The redirect URL WordPress determined from `_wp_old_slug`.
	 * @return string
	 */
	public function maybe_block_old_slug_redirect_url( $link ) {
		if ( empty( $link ) ) {
			return $link;
		}

		$post_id = url_to_postid( $link );

		if ( $post_id && $this->post_type_is_targeted( get_post_type( $post_id ) ) ) {
			return '';
		}

		if ( ! empty( $this->settings->get_target_post_types() ) ) {
			return '';
		}

		return $link;
	}

	/**
	 * Send no-cache headers on 404 pages to prevent browsers from caching redirects.
	 */
	public function maybe_send_404_nocache_headers() {
		if ( is_404() && ! headers_sent() ) {
			nocache_headers();
		}
	}

	/**
	 * Attempt to redirect a 404 request if it represents a child page whose parent
	 * or ancestor URL was redirected or renamed.
	 */
	public function maybe_redirect_child_pages() {
		if ( ! is_404() ) {
			return;
		}

		$target_url = $this->resolve_child_page_redirect();
		if ( $target_url ) {
			if ( ! empty( $_GET ) ) {
				$target_url = add_query_arg( $_GET, $target_url );
			}

			wp_safe_redirect( $target_url, 301 );
			exit;
		}
	}

	/**
	 * Determine if a 404 URL matches an existing child page whose parent path has changed.
	 *
	 * @return string|false Target URL to redirect to, or false if not resolvable.
	 */
	public function resolve_child_page_redirect() {
		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return false;
		}

		$raw_path = wp_parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
		if ( empty( $raw_path ) ) {
			return false;
		}

		// Normalize path relative to WordPress site/home root.
		$home_path  = wp_parse_url( home_url(), PHP_URL_PATH );
		$home_path  = ! empty( $home_path ) ? trim( $home_path, '/' ) : '';
		$clean_path = trim( $raw_path, '/' );

		if ( '' !== $home_path && 0 === strpos( $clean_path, $home_path ) ) {
			$clean_path = trim( substr( $clean_path, strlen( $home_path ) ), '/' );
		}

		$segments = array_values( array_filter( explode( '/', $clean_path ) ) );

		// Child page requests must have at least 2 segments (parent/child).
		if ( count( $segments ) < 2 ) {
			return false;
		}

		$leaf_slug = end( $segments );

		// 1. Check if an ancestor prefix matches a rule in Rank Math Redirections table.
		$rm_dest = $this->resolve_via_rank_math( $clean_path, $segments );
		if ( $rm_dest ) {
			return $rm_dest;
		}

		// 2. Check if an ancestor prefix matches a rule in Redirection plugin table.
		$red_dest = $this->resolve_via_redirection_plugin( $clean_path, $segments );
		if ( $red_dest ) {
			return $red_dest;
		}

		// 3. Fallback: Check WordPress page hierarchy directly.
		return $this->resolve_via_page_hierarchy( $clean_path, $segments, $leaf_slug );
	}

	/**
	 * Check if any ancestor path prefix is redirected in Rank Math's redirections table.
	 *
	 * @param string   $clean_path Path relative to WordPress root.
	 * @param string[] $segments   URL segments.
	 * @return string|false
	 */
	private function resolve_via_rank_math( $clean_path, $segments ) {
		global $wpdb;

		$rm_table = $wpdb->prefix . 'rank_math_redirections';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $rm_table ) ) !== $rm_table ) {
			return false;
		}

		$prefix_segments = $segments;
		array_pop( $prefix_segments ); // exclude leaf slug

		while ( ! empty( $prefix_segments ) ) {
			$prefix_path  = implode( '/', $prefix_segments );
			$like_pattern = '%' . $wpdb->esc_like( $prefix_path ) . '%';

			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT url_to, sources FROM {$rm_table} WHERE status = 'active' AND sources LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$like_pattern
				)
			);

			if ( ! empty( $rows ) ) {
				foreach ( $rows as $row ) {
					$sources = maybe_unserialize( $row->sources );
					if ( is_array( $sources ) ) {
						foreach ( $sources as $src ) {
							$pattern = trim( $src['pattern'] ?? '', '/' );
							if ( strcasecmp( $pattern, $prefix_path ) === 0 ) {
								$sub_path = ltrim( substr( $clean_path, strlen( $prefix_path ) ), '/' );
								$dest     = $row->url_to;

								if ( ! preg_match( '#^https?://#i', $dest ) ) {
									$dest = home_url( '/' . ltrim( $dest, '/' ) );
								}

								return trailingslashit( $dest ) . user_trailingslashit( $sub_path );
							}
						}
					}
				}
			}

			array_pop( $prefix_segments );
		}

		return false;
	}

	/**
	 * Check if any ancestor path prefix is redirected in the Redirection plugin table.
	 *
	 * @param string   $clean_path Path relative to WordPress root.
	 * @param string[] $segments   URL segments.
	 * @return string|false
	 */
	private function resolve_via_redirection_plugin( $clean_path, $segments ) {
		global $wpdb;

		$red_table = $wpdb->prefix . 'redirection_items';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $red_table ) ) !== $red_table ) {
			return false;
		}

		$prefix_segments = $segments;
		array_pop( $prefix_segments );

		while ( ! empty( $prefix_segments ) ) {
			$prefix_path = '/' . implode( '/', $prefix_segments );

			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT action_data FROM {$red_table} WHERE status = 'enabled' AND (url = %s OR url = %s) LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$prefix_path,
					trailingslashit( $prefix_path )
				)
			);

			if ( $row && ! empty( $row->action_data ) ) {
				$dest     = $row->action_data;
				$sub_path = ltrim( substr( $clean_path, strlen( trim( $prefix_path, '/' ) ) ), '/' );

				if ( ! preg_match( '#^https?://#i', $dest ) ) {
					$dest = home_url( '/' . ltrim( $dest, '/' ) );
				}

				return trailingslashit( $dest ) . user_trailingslashit( $sub_path );
			}

			array_pop( $prefix_segments );
		}

		return false;
	}

	/**
	 * Check if the leaf slug matches an existing published page whose ancestors
	 * align with the requested path segments (e.g. parent slug changed).
	 *
	 * @param string   $clean_path Path relative to WordPress root.
	 * @param string[] $segments   URL segments.
	 * @param string   $leaf_slug  Leaf post slug.
	 * @return string|false
	 */
	private function resolve_via_page_hierarchy( $clean_path, $segments, $leaf_slug ) {
		unset( $clean_path );

		$candidates = get_posts(
			array(
				'name'             => $leaf_slug,
				'post_type'        => array( 'page' ),
				'post_status'      => 'publish',
				'posts_per_page'   => 10,
				'suppress_filters' => true,
			)
		);

		if ( empty( $candidates ) ) {
			return false;
		}

		$root_requested = reset( $segments );

		foreach ( $candidates as $candidate ) {
			$ancestor_ids = array_reverse( get_post_ancestors( $candidate->ID ) );
			if ( empty( $ancestor_ids ) ) {
				continue;
			}

			$ancestor_slugs = array();
			foreach ( $ancestor_ids as $a_id ) {
				$a_post = get_post( $a_id );
				if ( $a_post ) {
					$ancestor_slugs[] = $a_post->post_name;
				}
			}

			$root_candidate = reset( $ancestor_slugs );

			// Top-level ancestor must match the requested top-level segment.
			if ( strcasecmp( $root_requested, $root_candidate ) === 0 ) {
				$permalink = get_permalink( $candidate->ID );
				if ( $permalink ) {
					return $permalink;
				}
			}
		}

		return false;
	}

	/**
	 * Whether a given post type is one the plugin should suppress redirects for.
	 *
	 * @param string|false $post_type Post type name.
	 * @return bool
	 */
	private function post_type_is_targeted( $post_type ) {
		if ( empty( $post_type ) ) {
			return false;
		}

		return in_array( $post_type, $this->settings->get_target_post_types(), true );
	}
}
