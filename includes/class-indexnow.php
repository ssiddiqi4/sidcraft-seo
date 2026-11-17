<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Tells Bing, Yandex and other IndexNow search engines about new and changed pages the moment you publish. */
class Sidcraft_AutoSEO_IndexNow {

	private static $queue = array();

	public static function init() {
		if ( ! Sidcraft_AutoSEO_Settings::on( 'indexnow' ) ) { return; }
		add_action( 'template_redirect', array( __CLASS__, 'serve_key' ), 0 );
		add_action( 'transition_post_status', array( __CLASS__, 'queue' ), 10, 3 );
		add_action( 'shutdown', array( __CLASS__, 'send' ) );
	}

	public static function key() {
		$key = get_option( 'sidcraft_autoseo_indexnow_key' );
		if ( ! is_string( $key ) || ! preg_match( '/^[a-f0-9]{32}$/', $key ) ) {
			$key = bin2hex( random_bytes( 16 ) );
			update_option( 'sidcraft_autoseo_indexnow_key', $key, false );
		}
		return $key;
	}

	public static function key_url() { return home_url( '/' . self::key() . '.txt' ); }

	public static function serve_key() {
		$path = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH ) : '';
		if ( $path && substr( (string) $path, -37 ) === '/' . self::key() . '.txt' ) {
			status_header( 200 );
			header( 'Content-Type: text/plain; charset=utf-8' );
			echo esc_html( self::key() );
			exit;
		}
	}

	/** Public, live sites only: never ping from localhost, staging or a "discourage search engines" site. */
	public static function allowed() {
		if ( ! get_option( 'blog_public' ) || wp_get_environment_type() !== 'production' ) { return false; }
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		if ( '' === $host || 'localhost' === $host || filter_var( $host, FILTER_VALIDATE_IP ) || preg_match( '/\.(local|test|localhost|invalid|example)$/', $host ) ) { return false; }
		return (bool) apply_filters( 'sidcraft_autoseo_indexnow_allowed', true );
	}

	public static function queue( $new, $old, $post ) {
		if ( ! in_array( $post->post_type, Sidcraft_AutoSEO_Util::post_types(), true ) ) { return; }
		if ( 'publish' !== $new && 'publish' !== $old ) { return; }
		if ( wp_is_post_revision( $post ) ) { return; }
		$url = get_permalink( $post );
		if ( $url ) { self::$queue[ $url ] = true; }
	}

	public static function send() {
		if ( ! self::$queue || ! self::allowed() ) { return; }
		$urls = array_slice( array_keys( self::$queue ), 0, 100 );
		$body = array( 'host' => wp_parse_url( home_url(), PHP_URL_HOST ), 'key' => self::key(), 'keyLocation' => self::key_url(), 'urlList' => $urls );
		wp_remote_post( 'https://api.indexnow.org/indexnow', array(
			'timeout' => 5, 'blocking' => false,
			'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
			'body'    => wp_json_encode( $body ),
		) );
		update_option( 'sidcraft_autoseo_indexnow_last', array( 'time' => time(), 'count' => count( $urls ) ), false );
		self::$queue = array();
	}
}
