<?php
/**
 * Fired when the plugin is uninstalled (deleted) via the WordPress admin.
 *
 * @package Disable_Automatic_Slug_Redirects
 */

// If uninstall is not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'dasr_settings' );

// Clean up the option on multisite installs too.
if ( is_multisite() ) {
	global $wpdb;

	$blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

	foreach ( $blog_ids as $blog_id ) {
		switch_to_blog( $blog_id );
		delete_option( 'dasr_settings' );
		restore_current_blog();
	}
}
