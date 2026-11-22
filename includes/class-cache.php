<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Page cache. The first visitor's copy of a page is saved as a file; later visitors who are not logged in are given
 * that file before WordPress builds the page. Needs no change to wp-config.php, .htaccess or the server. It also
 * shrinks the HTML (comments and runs of spaces) when that option is on. Switched off by default.
 */
class Sidcraft_RankBoost_Cache {

	const HOOK = 'sidcraft_rankboost_preload';
	const CLEAR_HOOK = 'sidcraft_rankboost_cache_clear';
	/** Query arguments that do not change the page, so the plain cached copy is used. */
	private static $ignore = array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'fbclid', 'gclid', 'msclkid' );

	public static function dir() {
		$up = wp_get_upload_dir();
		return empty( $up['error'] ) ? trailingslashit( $up['basedir'] ) . 'sidcraft-rankboost/pages' : '';
	}

	/* ---------------------------------------------------------- serving */

	/** Called while the plugin file loads, before WordPress builds anything. */
	public static function maybe_serve() {
		$o = Sidcraft_RankBoost_Speed::all();
		if ( ! $o['cache'] || ! self::request_ok() ) { return; }
		$f = self::file_for_request(); if ( '' === $f || ! is_file( $f ) ) { return; }
		if ( time() - (int) filemtime( $f ) > (int) $o['cache_hours'] * HOUR_IN_SECONDS ) { return; }
		if ( ! headers_sent() ) {
			header( 'Content-Type: text/html; charset=UTF-8' );
			header( 'X-Sidcraft-Cache: HIT' );
			header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', (int) filemtime( $f ) ) . ' GMT' );
		}
		readfile( $f ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- sending a stored page file; nothing user-supplied is in the output.
		exit;
	}

	/** Cheap checks that need no WordPress functions: method, path, query string, cookies. */
	private static function request_ok() {
		$m = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		if ( 'get' !== $m ) { return false; }
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only compared and hashed, never output.
		if ( '' === $uri || strlen( $uri ) > 600 ) { return false; }
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH ); $qs = (string) wp_parse_url( $uri, PHP_URL_QUERY );
		if ( '' !== $qs ) { parse_str( $qs, $q ); foreach ( array_keys( $q ) as $k ) { if ( ! in_array( strtolower( (string) $k ), self::$ignore, true ) ) { return false; } } }
		if ( preg_match( '#(^|/)(wp-admin|wp-json|wp-login\.php|wp-cron\.php|xmlrpc\.php|wp-content|wp-includes)(/|$)#', $path ) || preg_match( '#/(feed|embed|comments/feed)/?$#', $path ) || preg_match( '#\.(php|xml|txt|json|css|js)$#i', $path ) ) { return false; }
		foreach ( $_COOKIE as $k => $v ) {
			if ( 0 === strpos( $k, 'wordpress_logged_in_' ) || 0 === strpos( $k, 'wp-postpass_' ) || 0 === strpos( $k, 'comment_author_' ) || 0 === strpos( $k, 'wp_woocommerce_session_' ) || 'woocommerce_items_in_cart' === $k ) { return false; }
		}
		foreach ( Sidcraft_RankBoost_Speed::lines( Sidcraft_RankBoost_Speed::all()['cache_exclude'] ) as $x ) {
			if ( false !== strpos( $uri, $x ) ) { return false; }
		}
		return true;
	}

	private static function file_for_request() {
		$d = self::dir(); if ( '' === $d ) { return ''; }
		$host = isset( $_SERVER['HTTP_HOST'] ) ? preg_replace( '/[^a-z0-9.:-]/', '', strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) ) ) : '';
		$path = (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/', PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only hashed.
		$own = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		if ( '' === $host || '' === $own || preg_replace( '/:\d+$/', '', $host ) !== $own ) { return ''; }
		return $d . '/' . ( is_ssl() ? 's-' : 'h-' ) . $host . '/' . sha1( $path ) . '.html';
	}

	/* ---------------------------------------------------------- storing */

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'start' ), -9999 );
		{
			foreach ( array( 'save_post', 'deleted_post', 'trashed_post', 'untrashed_post', 'comment_post', 'edit_comment', 'delete_comment', 'wp_set_comment_status', 'switch_theme', 'wp_update_nav_menu', 'customize_save_after', 'activated_plugin', 'deactivated_plugin', 'upgrader_process_complete', 'update_option_permalink_structure', 'update_option_sidebars_widgets', 'update_option_blogname', 'update_option_page_on_front', 'update_option_show_on_front', 'update_option_widget_block' ) as $h ) {
				add_action( $h, array( __CLASS__, 'clear_soon' ) );
			}
		}
		add_action( self::HOOK, array( __CLASS__, 'preload_step' ) );
		add_action( self::CLEAR_HOOK, array( __CLASS__, 'scheduled_clear' ) );
		add_action( 'admin_bar_menu', array( __CLASS__, 'bar' ), 90 );
	}

	/** Decide whether this page may be saved, and if so catch its HTML. */
	public static function start() {
		$o = Sidcraft_RankBoost_Speed::all();
		if ( ! $o['cache'] && ! $o['min_html'] ) { return; }
		if ( is_admin() || is_feed() || is_embed() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) { return; }
		ob_start( array( __CLASS__, 'finish' ) );
	}

	/** Output-buffer callback: shrink the HTML, then save a copy when everything about the request says it is safe. */
	public static function finish( $html ) {
		if ( ! is_string( $html ) || strlen( $html ) < 255 || false === stripos( $html, '</html>' ) || 0 !== strpos( ltrim( $html ), '<' ) ) { return $html; }
		foreach ( headers_list() as $h ) { if ( 0 === stripos( $h, 'content-type:' ) && false === stripos( $h, 'text/html' ) ) { return $html; } }
		$o = Sidcraft_RankBoost_Speed::all();
		if ( $o['min_html'] ) { $m = Sidcraft_RankBoost_Minify::html( $html ); if ( strlen( $m ) > 200 && false !== stripos( $m, '</html>' ) ) { $html = $m; } }
		if ( $o['cache'] && self::storable() ) {
			$f = self::file_for_request();
			if ( '' !== $f && wp_mkdir_p( dirname( $f ) ) ) {
				self::guard_dir();
				Sidcraft_RankBoost_Minify::write( $f, $html . "\n<!-- Page cached by Sidcraft RankBoost " . gmdate( 'Y-m-d H:i' ) . " UTC -->" );
			}
		}
		return $html;
	}

	private static function storable() {
		if ( ! self::request_ok() || is_user_logged_in() || is_404() || is_search() || is_feed() || is_preview() || is_customize_preview() || post_password_required() ) { return false; }
		if ( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE ) { return false; }
		if ( 200 !== (int) http_response_code() ) { return false; }
		if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() || is_account_page() ) ) { return false; }
		return true;
	}

	private static function guard_dir() {
		$d = self::dir(); $root = dirname( $d );
		if ( ! is_file( $root . '/index.php' ) ) { Sidcraft_RankBoost_Minify::write( $root . '/index.php', "<?php\n// Silence is golden.\n" ); }
	}

	/* --------------------------------------------------------- clearing */

	/** Several saves in one request clear only once. */
	public static function clear_soon( $id = 0 ) {
		static $done = false; if ( $done ) { return; }
		// Autosaves and revisions never change what visitors see; clearing (and re-preloading) for each one is wasted work.
		if ( $id && in_array( current_filter(), array( 'save_post', 'deleted_post', 'trashed_post', 'untrashed_post' ), true ) && ( wp_is_post_revision( $id ) || wp_is_post_autosave( $id ) ) ) { return; }
		$done = true;
		self::clear_all();
	}

	public static function clear_all() {
		$d = self::dir(); $n = '' === $d ? 0 : Sidcraft_RankBoost_Speed::rm_dir( $d );
		if ( Sidcraft_RankBoost_Speed::all()['cache'] && Sidcraft_RankBoost_Speed::all()['cache_preload'] && ! wp_next_scheduled( self::HOOK, array( 0 ) ) ) { wp_schedule_single_event( time() + 20, self::HOOK, array( 0 ) ); }
		return $n;
	}

	/** The timed clear: empty the cache, then plan the next one. */
	public static function scheduled_clear() {
		Sidcraft_RankBoost_Speed::plan_next( self::CLEAR_HOOK, (int) Sidcraft_RankBoost_Speed::all()['cache_days'] );
		self::clear_all();
	}

	/* ---------------------------------------------------------- preload */

	/** Visit the home page and a few pages and posts at a time so visitors find them already cached. */
	public static function preload_step( $offset = 0 ) {
		$o = Sidcraft_RankBoost_Speed::all(); if ( ! $o['cache'] ) { return; }
		if ( Sidcraft_RankBoost_Audit::busy() ) { wp_schedule_single_event( time() + 10 * MINUTE_IN_SECONDS, self::HOOK, array( (int) $offset ) ); return; }
		$offset = max( 0, (int) $offset ); $per = 3; $urls = array();
		if ( 0 === $offset ) { $urls[] = home_url( '/' ); }
		$ids = get_posts( array( 'post_type' => array( 'page', 'post' ), 'post_status' => 'publish', 'fields' => 'ids', 'numberposts' => $per, 'offset' => $offset, 'orderby' => 'modified', 'order' => 'DESC', 'no_found_rows' => true ) );
		foreach ( $ids as $id ) { $urls[] = get_permalink( $id ); }
		foreach ( $urls as $u ) { wp_remote_get( $u, array( 'timeout' => 5, 'redirection' => 0, 'user-agent' => 'SidcraftRankBoost-Preload', 'cookies' => array() ) ); }
		if ( count( $ids ) === $per && $offset < 150 ) { wp_schedule_single_event( time() + 45, self::HOOK, array( $offset + $per ) ); }
	}

	public static function bar( $bar ) {
		if ( ! current_user_can( 'manage_options' ) || is_admin() || ! Sidcraft_RankBoost_Speed::all()['cache'] ) { return; }
		$bar->add_node( array( 'id' => 'sidcraft-rankboost-cache', 'title' => esc_html__( 'Clear page cache', 'sidcraft-rankboost' ), 'href' => wp_nonce_url( admin_url( 'admin-post.php?action=sidcraft_rankboost_speed&do=clear_cache&back=front' ), 'sidcraft_rankboost_speed' ) ) );
	}
}
