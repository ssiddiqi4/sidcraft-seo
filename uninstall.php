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
	if ( is_array( $settings ) && ! empty( $settings['remove_data'] ) ) {
		$up = wp_get_upload_dir();
		if ( empty( $up['error'] ) ) {
			foreach ( array( 'pages', 'min' ) as $sub ) {
				$dir = trailingslashit( $up['basedir'] ) . 'sidcraft-autoseo/' . $sub;
				if ( is_dir( $dir ) ) { foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST ) as $f ) { $f->isDir() ? rmdir( $f->getPathname() ) : wp_delete_file( $f->getPathname() ); } rmdir( $dir ); } // phpcs:ignore WordPress.WP.AlternativeFunctions.rmdir_rmdir -- removing our own generated folders.
			}
		}
		foreach ( array( 'sidcraft_autoseo_speed', 'sidcraft_autoseo_slow', 'sidcraft_autoseo_min_bad' ) as $option_name ) { delete_option( $option_name ); }
	}
	wp_clear_scheduled_hook( 'sidcraft_autoseo_db_auto' );
	wp_clear_scheduled_hook( 'sidcraft_autoseo_preload', array( 0 ) );
	wp_clear_scheduled_hook( 'sidcraft_autoseo_daily_audit' );
	wp_clear_scheduled_hook( 'sidcraft_autoseo_audit_more' );
	wp_clear_scheduled_hook( 'sidcraft_autoseo_links_more' );
}

sidcraft_autoseo_uninstall();
