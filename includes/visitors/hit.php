<?php
/**
 * Lightweight Visitor Stats endpoint.
 *
 * The tracking script posts here first. It loads only the WordPress core (database, options, formatting),
 * not the theme, the other plugins or the REST server, so counting a page view costs a few milliseconds
 * instead of a full WordPress request. If this file cannot start (unusual folder layout), it answers 404
 * and the script falls back to the normal REST route.
 *
 * @package Sidcraft_RankBoost
 */

// phpcs:disable WordPress.Security.NonceVerification, WordPress.WP.AlternativeFunctions, WordPress.Security.ValidatedSanitizedInput -- Public counting endpoint: it only stores anonymous page views and every value is sanitised in the tracker class.

if ( ! defined( 'ABSPATH' ) ) {
	// A direct request to this file is how the counting script reaches it. Start only the WordPress core (SHORTINIT), nothing else.
	if ( 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) ) {
		http_response_code( 405 );
		exit;
	}
	$sidcraft_vs_root = dirname( __FILE__, 6 );
	if ( ! is_file( $sidcraft_vs_root . '/wp-load.php' ) ) {
		http_response_code( 404 );
		exit;
	}
	define( 'SHORTINIT', true );
	require $sidcraft_vs_root . '/wp-load.php';
}
if ( ! defined( 'ABSPATH' ) ) {
	exit; // WordPress could not be started.
}
if ( ! defined( 'SHORTINIT' ) || ! SHORTINIT ) {
	exit; // This file only ever runs as the lightweight endpoint.
}

$sidcraft_vs_main = basename( dirname( __FILE__, 3 ) ) . '/sidcraft-search-boost-suite.php';
$sidcraft_vs_on   = (array) get_option( 'active_plugins', array() );
if ( ! in_array( $sidcraft_vs_main, $sidcraft_vs_on, true ) ) {
	http_response_code( 404 ); // Plugin not active (network-wide activation uses the REST route).
	exit;
}

require_once ABSPATH . WPINC . '/kses.php'; // esc_url_raw() needs it.
require_once ABSPATH . WPINC . '/http.php'; // wp_parse_url().
define( 'SIDCRAFT_VS_VERSION', '0' );
require_once __DIR__ . '/class-settings.php';
require_once __DIR__ . '/class-db.php';
require_once __DIR__ . '/class-ua.php';
require_once __DIR__ . '/class-tracker.php';

$sidcraft_vs_raw = file_get_contents( 'php://input', false, null, 0, 4096 );
$sidcraft_vs_in  = json_decode( (string) $sidcraft_vs_raw, true );
if ( ! is_array( $sidcraft_vs_in ) ) {
	$sidcraft_vs_in = $_POST;
}
$sidcraft_vs_act = isset( $_GET['a'] ) && 'time' === $_GET['a'] ? 'time' : 'hit';

nocache_headers();
header( 'Content-Type: application/json; charset=utf-8' );
header( 'X-Content-Type-Options: nosniff' );
echo wp_json_encode( 'time' === $sidcraft_vs_act ? Sidcraft_VS_Tracker::record_time( $sidcraft_vs_in ) : Sidcraft_VS_Tracker::record( $sidcraft_vs_in ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON.
exit;
