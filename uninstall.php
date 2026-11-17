<?php
/**
 * Removes plugin data on uninstall (only when the "delete all data" option was ticked).
 *
 * @package Sidcraft_AutoSEO
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/** Clean up. Wrapped in a function so no global variables are created. */
function sidcraft_autoseo_uninstall() {
	global $wpdb;
	$settings = get_option( 'sidcraft_autoseo_settings' );
	if ( is_array( $settings ) && ! empty( $settings['remove_data'] ) ) {
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'sidcraft_autoseo_redirects' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- removing our own tables.
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'sidcraft_autoseo_404' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- removing our own tables.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $wpdb->esc_like( '_sidcraft_autoseo_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- removing our own post meta.
		foreach ( array( 'sidcraft_autoseo_settings', 'sidcraft_autoseo_db_version', 'sidcraft_autoseo_indexnow_key', 'sidcraft_autoseo_indexnow_last', 'sidcraft_autoseo_last_run', 'sidcraft_autoseo_links_items', 'sidcraft_autoseo_links_state', 'sidcraft_autoseo_links_ignored' ) as $option_name ) {
			delete_option( $option_name );
		}
	}
	wp_clear_scheduled_hook( 'sidcraft_autoseo_daily_audit' );
	wp_clear_scheduled_hook( 'sidcraft_autoseo_audit_more' );
	wp_clear_scheduled_hook( 'sidcraft_autoseo_links_more' );
}

sidcraft_autoseo_uninstall();
