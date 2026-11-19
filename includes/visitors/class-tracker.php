<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Records a visit. A tiny script on the page calls a REST route, so pages stored by a page cache are still counted. */
class Sidcraft_VS_Tracker {

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'sidcraft_vs_purge', array( __CLASS__, 'purge' ) );
	}

	public static function purge() { Sidcraft_VS_DB::purge( (int) Sidcraft_VS_Settings::all()['retention_days'] ); }

	/** Should this person's visits be left out? Checked while building the page, so excluded people never even load the script. */
	private static function skip_user() {
		if ( ! is_user_logged_in() ) { return false; }
		$ex = (array) Sidcraft_VS_Settings::all()['exclude_roles']; $u = wp_get_current_user();
		return (bool) array_intersect( $ex, (array) $u->roles );
	}

	public static function enqueue() {
		$o = Sidcraft_VS_Settings::all();
		if ( ! $o['enabled'] || is_admin() || is_preview() || is_customize_preview() || is_feed() || self::skip_user() ) { return; }
		if ( is_404() && ! $o['track_404'] ) { return; }
		wp_enqueue_script( 'sidcraft-vs-track', SIDCRAFT_VS_URL . 'assets/js/track.js', array(), SIDCRAFT_VS_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
		wp_localize_script( 'sidcraft-vs-track', 'sidcraftVS', array( 'url' => esc_url_raw( rest_url( 'sidcraft-vs/v1' ) ), 'nf' => is_404() ? 1 : 0, 'dnt' => $o['respect_dnt'] ? 1 : 0 ) );
	}

	public static function routes() {
		register_rest_route( 'sidcraft-vs/v1', '/hit', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'hit' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( 'sidcraft-vs/v1', '/time', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'time' ), 'permission_callback' => '__return_true' ) );
	}

	public static function ip() {
		$o = Sidcraft_VS_Settings::all(); $ip = '';
		if ( 'cloudflare' === $o['proxy'] && ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) { $ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ); }
		elseif ( 'xff' === $o['proxy'] && ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) { $ip = trim( explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) )[0] ); }
		if ( '' === $ip || false === filter_var( $ip, FILTER_VALIDATE_IP ) ) { $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : ''; }
		return $ip;
	}

	/** An id that cannot be traced back to a person and changes every day: no cookie, no stored IP address. */
	private static function visitor( $ip, $ua ) {
		$day = wp_date( 'Y-m-d' );
		return substr( hash( 'sha256', get_option( 'sidcraft_vs_salt', '' ) . '|' . $day . '|' . $ip . '|' . $ua ), 0, 16 );
	}

	private static function sign( $id ) { return substr( hash_hmac( 'sha256', 'svs' . (int) $id, wp_salt( 'auth' ) ), 0, 20 ); }

	public static function hit( WP_REST_Request $req ) {
		$o = Sidcraft_VS_Settings::all(); $none = new WP_REST_Response( array( 'ok' => 0 ), 200 );
		if ( ! $o['enabled'] ) { return $none; }
		if ( $o['respect_dnt'] && ( ( isset( $_SERVER['HTTP_DNT'] ) && '1' === $_SERVER['HTTP_DNT'] ) || ( isset( $_SERVER['HTTP_SEC_GPC'] ) && '1' === $_SERVER['HTTP_SEC_GPC'] ) ) ) { return $none; }
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 400 ) : '';
		$bot = Sidcraft_VS_UA::is_bot( $ua );
		if ( $bot && ! $o['track_bots'] ) { return $none; }
		$ip = self::ip();
		if ( '' === $ip || in_array( $ip, Sidcraft_VS_Settings::lines( $o['exclude_ips'] ), true ) ) { return $none; }

		$p = $req->get_json_params(); if ( ! is_array( $p ) ) { $p = $req->get_body_params(); }
		$path = isset( $p['p'] ) ? sanitize_text_field( (string) $p['p'] ) : '';
		if ( '' === $path || '/' !== $path[0] ) { return $none; }
		$path  = substr( $path, 0, 255 );
		$title = isset( $p['t'] ) ? substr( sanitize_text_field( (string) $p['t'] ), 0, 160 ) : '';
		$status = ! empty( $p['n'] ) ? 404 : 200;

		$visitor = self::visitor( $ip, $ua );
		$cap = 'svs_c_' . substr( $visitor, 0, 12 ); $n = (int) get_transient( $cap );
		if ( $n >= 300 ) { return $none; }
		set_transient( $cap, $n + 1, HOUR_IN_SECONDS );

		// Where the visitor came from.
		$host = ''; $src = 'direct'; $camp = '';
		$ref  = isset( $p['r'] ) ? esc_url_raw( (string) $p['r'] ) : '';
		if ( '' !== $ref ) { $h = wp_parse_url( $ref, PHP_URL_HOST ); $host = $h ? preg_replace( '/^www\./', '', strtolower( $h ) ) : ''; }
		$own = preg_replace( '/^www\./', '', strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) );
		if ( '' !== $host && $host === $own ) { $src = 'internal'; $host = ''; }
		elseif ( '' !== $host ) { $src = Sidcraft_VS_UA::source_of( $host ); }
		if ( ! empty( $p['q'] ) ) {
			parse_str( ltrim( substr( (string) $p['q'], 0, 300 ), '?' ), $q );
			if ( ! empty( $q['utm_source'] ) && is_string( $q['utm_source'] ) ) { $host = strtolower( substr( sanitize_text_field( $q['utm_source'] ), 0, 120 ) ); $src = 'campaign'; }
			if ( ! empty( $q['utm_campaign'] ) && is_string( $q['utm_campaign'] ) ) { $camp = substr( sanitize_text_field( $q['utm_campaign'] ), 0, 80 ); if ( 'internal' === $src || 'direct' === $src ) { $src = 'campaign'; } }
		}

		list( $browser, $os, $device ) = Sidcraft_VS_UA::parse( $ua );
		if ( $bot ) { $device = 'bot'; }
		$country = '';
		if ( ! empty( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ) { $country = strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ) ), 0, 2 ) ); }
		$country = (string) apply_filters( 'sidcraft_vs_country', $country, $ip );
		if ( 'XX' === $country || 'T1' === $country || strlen( $country ) !== 2 ) { $country = ''; }

		$id = Sidcraft_VS_DB::insert( array(
			'ts' => gmdate( 'Y-m-d H:i:s' ), 'day' => wp_date( 'Y-m-d' ), 'hr' => (int) wp_date( 'G' ), 'visitor' => $visitor, 'path' => $path, 'title' => $title, 'status' => $status,
			'ref_host' => $host, 'source' => $src, 'campaign' => $camp, 'browser' => $browser, 'os' => $os, 'device' => $device, 'country' => $country, 'duration' => 0,
		) );
		return new WP_REST_Response( array( 'ok' => $id ? 1 : 0, 'id' => $id, 'k' => $id ? self::sign( $id ) : '' ), 200 );
	}

	/** The page reports how long it was open (the visible time, capped at 30 minutes). */
	public static function time( WP_REST_Request $req ) {
		$p = $req->get_json_params(); if ( ! is_array( $p ) ) { $p = $req->get_body_params(); }
		$id = isset( $p['id'] ) ? (int) $p['id'] : 0; $k = isset( $p['k'] ) ? (string) $p['k'] : ''; $s = isset( $p['s'] ) ? (int) $p['s'] : 0;
		if ( $id > 0 && $s > 0 && hash_equals( self::sign( $id ), $k ) ) { Sidcraft_VS_DB::set_duration( $id, min( 1800, $s ) ); }
		return new WP_REST_Response( array( 'ok' => 1 ), 200 );
	}
}
