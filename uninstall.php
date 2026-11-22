<?php
/**
 * Removes plugin data on uninstall (only when the "delete all data" option was ticked).
 *
 * @package Sidcraft_RankBoost
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/** Clean up. Wrapped in a function so no global variables are created. */
function sidcraft_rankboost_uninstall() {
	global $wpdb;
	$settings = get_option( 'sidcraft_rankboost_settings' );
	if ( is_array( $settings ) && ! empty( $settings['remove_data'] ) ) {
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'sidcraft_rankboost_redirects' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- removing our own tables.
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'sidcraft_rankboost_404' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- removing our own tables.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $wpdb->esc_like( '_sidcraft_rankboost_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- removing our own post meta.
		foreach ( array( 'sidcraft_rankboost_settings', 'sidcraft_rankboost_db_version', 'sidcraft_rankboost_indexnow_key', 'sidcraft_rankboost_indexnow_last', 'sidcraft_rankboost_last_run', 'sidcraft_rankboost_links_items', 'sidcraft_rankboost_links_state', 'sidcraft_rankboost_links_ignored' ) as $option_name ) {
			delete_option( $option_name );
		}
	}
	if ( is_array( $settings ) && ! empty( $settings['remove_data'] ) ) {
		$up = wp_get_upload_dir();
		if ( empty( $up['error'] ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
			global $wp_filesystem;
			foreach ( array( 'pages', 'min' ) as $sub ) {
				$dir = trailingslashit( $up['basedir'] ) . 'sidcraft-rankboost/' . $sub;
				if ( $wp_filesystem && is_dir( $dir ) ) { $wp_filesystem->delete( $dir, true ); }
			}
		}
		foreach ( array( 'sidcraft_rankboost_speed', 'sidcraft_rankboost_slow', 'sidcraft_rankboost_min_bad' ) as $option_name ) { delete_option( $option_name ); }
	}
	$vs = get_option( 'sidcraft_vs_settings' );
	if ( is_array( $vs ) && ! empty( $vs['delete_on_uninstall'] ) ) {
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'svs_hits' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- removing our own table.
		foreach ( array( 'sidcraft_vs_settings', 'sidcraft_vs_db', 'sidcraft_vs_salt' ) as $option_name ) { delete_option( $option_name ); }
	}
	wp_clear_scheduled_hook( 'sidcraft_vs_purge' );
	wp_clear_scheduled_hook( 'sidcraft_rankboost_db_auto' );
	wp_clear_scheduled_hook( 'sidcraft_rankboost_cache_clear' );
	wp_clear_scheduled_hook( 'sidcraft_rankboost_preload', array( 0 ) );
	wp_clear_scheduled_hook( 'sidcraft_rankboost_daily_audit' );
	wp_clear_scheduled_hook( 'sidcraft_rankboost_audit_more' );
	wp_clear_scheduled_hook( 'sidcraft_rankboost_links_more' );
}

sidcraft_rankboost_uninstall();
