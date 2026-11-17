<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** 301 redirects created automatically when a URL changes, plus a 404 log that repairs near-miss URLs by itself. */
class Sidcraft_AutoSEO_Redirects {

	public static function table()  { global $wpdb; return $wpdb->prefix . 'sidcraft_autoseo_redirects'; }
	public static function table404() { global $wpdb; return $wpdb->prefix . 'sidcraft_autoseo_404'; }

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		dbDelta( "CREATE TABLE " . self::table() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			source varchar(191) NOT NULL,
			target text NOT NULL,
			code smallint(3) NOT NULL DEFAULT 301,
			auto tinyint(1) NOT NULL DEFAULT 0,
			hits bigint(20) unsigned NOT NULL DEFAULT 0,
			created datetime NOT NULL,
			last_hit datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY source (source)
		) $charset;" );
		dbDelta( "CREATE TABLE " . self::table404() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			path varchar(191) NOT NULL,
			hits bigint(20) unsigned NOT NULL DEFAULT 1,
			last_seen datetime NOT NULL,
			referrer varchar(250) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			UNIQUE KEY path (path)
		) $charset;" );
		update_option( 'sidcraft_autoseo_db_version', SIDCRAFT_AUTOSEO_VERSION, false );
	}

	public static function init() {
		add_action( 'init', function () { if ( get_option( 'sidcraft_autoseo_db_version' ) !== SIDCRAFT_AUTOSEO_VERSION ) { self::install(); } } );
		if ( ! Sidcraft_AutoSEO_Settings::on( 'auto_redirects' ) ) { return; }
		add_action( 'template_redirect', array( __CLASS__, 'handle' ), 0 );
		add_action( 'template_redirect', array( __CLASS__, 'handle_404' ), 99 );
		add_action( 'post_updated', array( __CLASS__, 'on_post_updated' ), 10, 3 );
	}

	/** Normalise a request path or URL to "/path" without query string or trailing slash. */
	public static function normalize( $url ) {
		$path = wp_parse_url( (string) $url, PHP_URL_PATH );
		$home = rtrim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
		$path = (string) $path;
		if ( '' !== $home && 0 === strpos( $path, $home ) ) { $path = substr( $path, strlen( $home ) ); }
		$path = '/' . trim( rawurldecode( $path ), '/' );
		return strtolower( substr( $path, 0, 190 ) );
	}

	public static function add( $source, $target, $auto = 0, $code = 301 ) {
		global $wpdb;
		$src = self::normalize( $source );
		if ( '/' === $src || '' === $src ) { return false; }
		$target = esc_url_raw( $target );
		if ( '' === $target || self::normalize( $target ) === $src ) { return false; }
		$wpdb->query( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom plugin table.
			'INSERT INTO %i (source, target, code, auto, created) VALUES (%s, %s, %d, %d, %s) ON DUPLICATE KEY UPDATE target = VALUES(target), code = VALUES(code)',
			self::table(), $src, $target, (int) $code, (int) $auto, current_time( 'mysql', true )
		) );
		$wpdb->delete( self::table404(), array( 'path' => $src ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom plugin table.
		return true;
	}

	public static function delete( $id ) {
		global $wpdb;
		return $wpdb->delete( self::table(), array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom plugin table.
	}

	private static function skip_request() {
		return isset( $_SERVER['HTTP_X_SIDCRAFT_LINKCHECK'] ) || is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || is_customize_preview() || is_preview();
	}

	public static function handle() {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'GET';
		if ( self::skip_request() || 'GET' !== $method ) { return; }
		global $wpdb;
		$path = self::normalize( isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '' );
		$row  = $wpdb->get_row( $wpdb->prepare( 'SELECT id, target, code FROM %i WHERE source = %s', self::table(), $path ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom plugin table, redirect lookups must be live.
		if ( ! $row ) { return; }
		$wpdb->query( $wpdb->prepare( 'UPDATE %i SET hits = hits + 1, last_hit = %s WHERE id = %d', self::table(), current_time( 'mysql', true ), $row->id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom plugin table.
		wp_redirect( $row->target, (int) $row->code ); // phpcs:ignore WordPress.Security.SafeRedirect -- target was validated with esc_url_raw when saved; may be external by design.
		exit;
	}

	/** Slug changed on a published post: the old URL keeps working. */
	public static function on_post_updated( $post_id, $after, $before ) {
		if ( 'publish' !== $after->post_status || 'publish' !== $before->post_status ) { return; }
		if ( ! in_array( $after->post_type, Sidcraft_AutoSEO_Util::post_types(), true ) ) { return; }
		$old = get_permalink( $before );
		$new = get_permalink( $after );
		if ( $old && $new && $old !== $new ) {
			self::add( $old, $new, 1 );
			// Keep redirects one hop: anything that pointed at the old address now points at the new one.
			global $wpdb;
			$wpdb->query( $wpdb->prepare( 'UPDATE %i SET target = %s WHERE target = %s', self::table(), esc_url_raw( $new ), esc_url_raw( $old ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom plugin table.
		}
	}

	/** On a 404: try to find the page the visitor meant; otherwise record it. */
	public static function handle_404() {
		if ( self::skip_request() || ! is_404() ) { return; }
		$raw  = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$path = self::normalize( $raw );
		if ( preg_match( '#\.(?:jpe?g|png|gif|webp|svg|ico|css|js|map|woff2?|ttf|php|xml|txt|zip|pdf)$#', $path ) ) { return; }
		if ( Sidcraft_AutoSEO_Settings::on( 'fuzzy_404' ) ) {
			$target = self::guess( $path );
			if ( $target ) {
				self::add( $raw, $target, 1 );
				wp_redirect( $target, 301 ); // phpcs:ignore WordPress.Security.SafeRedirect -- internal permalink.
				exit;
			}
		}
		global $wpdb;
		$ref = isset( $_SERVER['HTTP_REFERER'] ) ? substr( esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ), 0, 250 ) : '';
		$wpdb->query( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom plugin table.
			'INSERT INTO %i (path, hits, last_seen, referrer) VALUES (%s, 1, %s, %s) ON DUPLICATE KEY UPDATE hits = hits + 1, last_seen = VALUES(last_seen), referrer = IF(VALUES(referrer) <> %s, VALUES(referrer), referrer)',
			self::table404(), $path, current_time( 'mysql', true ), $ref, ''
		) );
	}

	/** Find the published page or post this missing URL most likely meant. */
	public static function guess( $path, $min = 88 ) {
		$segments = array_values( array_filter( explode( '/', $path ) ) );
		if ( ! $segments ) { return ''; }
		$last = sanitize_title( end( $segments ) );
		if ( '' === $last || strlen( $last ) < 3 ) { return ''; }
		$slugs = get_transient( 'sidcraft_autoseo_slugs' );
		if ( ! is_array( $slugs ) ) {
			global $wpdb;
			$types = Sidcraft_AutoSEO_Util::post_types();
			$in    = implode( ',', array_fill( 0, count( $types ), '%s' ) );
			$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_name FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ($in) AND post_name <> '' LIMIT 5000", $types ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- one %s per post type, built above.
			$slugs = wp_list_pluck( $rows, 'post_name', 'ID' );
			set_transient( 'sidcraft_autoseo_slugs', $slugs, HOUR_IN_SECONDS );
		}
		$best = 0; $best_id = 0;
		foreach ( $slugs as $id => $slug ) {
			if ( $slug === $last ) { $best = 100; $best_id = $id; break; }
			similar_text( $last, $slug, $pct );
			// A slug that merely starts with the missing one (or the reverse) is a strong hint too.
			if ( strlen( $last ) >= 6 && ( 0 === strpos( $slug, $last ) || 0 === strpos( $last, $slug ) ) ) { $pct = max( $pct, 90 ); }
			if ( $pct > $best ) { $best = $pct; $best_id = $id; }
		}
		if ( $best_id && $best >= $min ) {
			$link = get_permalink( $best_id );
			if ( $link && self::normalize( $link ) !== $path ) { return $link; }
		}
		return '';
	}

	public static function counts() {
		global $wpdb;
		return array(
			'redirects' => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', self::table() ) ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom plugin table.
			'auto'      => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE auto = %d', self::table(), 1 ) ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom plugin table.
			'hits'      => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(hits),0) FROM %i', self::table() ) ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom plugin table.
			'missing'   => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', self::table404() ) ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom plugin table.
		);
	}

	public static function delete_404( $id ) {
		global $wpdb;
		$wpdb->delete( self::table404(), array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom plugin table.
	}

	/** Keep the 404 log small: drop entries older than 90 days and cap the table. */
	public static function prune() {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE last_seen < %s', self::table404(), gmdate( 'Y-m-d H:i:s', time() - 90 * DAY_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom plugin table.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE id NOT IN (SELECT id FROM (SELECT id FROM %i ORDER BY hits DESC, last_seen DESC LIMIT 1000) t)', self::table404(), self::table404() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom plugin table.
	}
}
