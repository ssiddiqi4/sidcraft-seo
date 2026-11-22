<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Performance and debugging helpers: slow-page log, admin-bar timing, error-log reader, server health checks, speed test. */
class Sidcraft_RankBoost_Perf {

	const SLOW = 'sidcraft_rankboost_slow';

	public static function init() {
		if ( ! Sidcraft_RankBoost_Speed::all()['profile'] ) { return; }
		add_action( 'shutdown', array( __CLASS__, 'record' ), 0 );
		add_action( 'admin_bar_menu', array( __CLASS__, 'bar' ), 100 );
	}

	/** Seconds since the request began, memory peak and query count as one array. */
	private static function now() {
		$start = isset( $_SERVER['REQUEST_TIME_FLOAT'] ) ? (float) $_SERVER['REQUEST_TIME_FLOAT'] : microtime( true );
		return array( 'ms' => (int) round( ( microtime( true ) - $start ) * 1000 ), 'q' => function_exists( 'get_num_queries' ) ? (int) get_num_queries() : 0, 'mem' => memory_get_peak_usage( true ) );
	}

	/** Keep the slowest recent page loads (only those over the limit you set). */
	public static function record() {
		if ( 'cli' === PHP_SAPI || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) { return; }
		$n = self::now(); if ( $n['ms'] < (int) Sidcraft_RankBoost_Speed::all()['slow_ms'] ) { return; }
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$log = get_option( self::SLOW, array() ); $log = is_array( $log ) ? $log : array();
		array_unshift( $log, array( 't' => time(), 'url' => substr( $uri, 0, 200 ), 'ms' => $n['ms'], 'q' => $n['q'], 'mem' => $n['mem'], 'where' => is_admin() ? 'admin' : 'site' ) );
		update_option( self::SLOW, array_slice( $log, 0, 50 ), false );
	}

	public static function slow_log() { $l = get_option( self::SLOW, array() ); return is_array( $l ) ? $l : array(); }

	public static function bar( $bar ) {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$n = self::now();
		$bar->add_node( array( 'id' => 'sidcraft-rankboost-perf', 'title' => esc_html( sprintf( /* translators: 1: seconds, 2: number of database queries, 3: memory */ __( '⏱ %1$ss · %2$d queries · %3$s', 'sidcraft-rankboost' ), number_format_i18n( $n['ms'] / 1000, 2 ), $n['q'], size_format( $n['mem'], 0 ) ) ), 'href' => admin_url( 'admin.php?page=sidcraft-rankboost&tab=speed&sec=debug' ) ) );
	}

	/* ------------------------------------------------------------ error log */

	public static function log_path() {
		if ( defined( 'WP_DEBUG_LOG' ) && is_string( WP_DEBUG_LOG ) && '' !== WP_DEBUG_LOG ) { return WP_DEBUG_LOG; }
		$p = WP_CONTENT_DIR . '/debug.log'; if ( is_file( $p ) ) { return $p; }
		$e = (string) ini_get( 'error_log' ); return ( '' !== $e && is_file( $e ) ) ? $e : $p;
	}

	/** The end of the error log, grouped by message, with the plugin or theme each error comes from. */
	public static function read_log() {
		$p = self::log_path(); $out = array( 'path' => $p, 'exists' => is_file( $p ), 'size' => 0, 'groups' => array(), 'by' => array() );
		if ( ! $out['exists'] || ! is_readable( $p ) ) { return $out; }
		$out['size'] = (int) filesize( $p ); $fh = fopen( $p, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- reading the end of a log file.
		if ( ! $fh ) { return $out; }
		if ( $out['size'] > 400000 ) { fseek( $fh, -400000, SEEK_END ); } $txt = (string) stream_get_contents( $fh ); fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		foreach ( preg_split( '/\R/', $txt ) as $line ) {
			if ( '' === trim( $line ) || ! preg_match( '/^\[([^\]]+)\]\s+(.*)$/', $line, $m ) ) { continue; }
			$msg = $m[2]; $key = md5( preg_replace( '/\d+/', '#', $msg ) );
			if ( ! isset( $out['groups'][ $key ] ) ) { $out['groups'][ $key ] = array( 'msg' => $msg, 'n' => 0, 'last' => $m[1], 'src' => self::source( $msg ), 'lvl' => self::level( $msg ) ); }
			$out['groups'][ $key ]['n']++; $out['groups'][ $key ]['last'] = $m[1]; $out['groups'][ $key ]['msg'] = $msg;
		}
		uasort( $out['groups'], function ( $a, $b ) { return $b['n'] <=> $a['n']; } );
		foreach ( $out['groups'] as $g ) { $out['by'][ $g['src'] ] = ( isset( $out['by'][ $g['src'] ] ) ? $out['by'][ $g['src'] ] : 0 ) + $g['n']; }
		arsort( $out['by'] ); $out['groups'] = array_slice( $out['groups'], 0, 40, true );
		return $out;
	}

	private static function source( $msg ) {
		if ( preg_match( '#/wp-content/plugins/([^/\s]+)#', $msg, $m ) ) { return 'Plugin: ' . $m[1]; }
		if ( preg_match( '#/wp-content/themes/([^/\s]+)#', $msg, $m ) ) { return 'Theme: ' . $m[1]; }
		if ( preg_match( '#/wp-(includes|admin)/#', $msg ) ) { return 'WordPress core'; }
		return 'Other';
	}

	private static function level( $msg ) {
		if ( preg_match( '/Fatal error|Parse error|Uncaught/i', $msg ) ) { return 'fatal'; }
		if ( preg_match( '/Warning/i', $msg ) ) { return 'warning'; }
		if ( preg_match( '/Deprecated/i', $msg ) ) { return 'deprecated'; }
		return 'notice';
	}

	public static function clear_log() {
		$p = self::log_path(); if ( ! is_file( $p ) ) { return false; }
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) { require_once ABSPATH . 'wp-admin/includes/file.php'; WP_Filesystem(); }
		return $wp_filesystem ? $wp_filesystem->put_contents( $p, '' ) : false;
	}

	/* --------------------------------------------------------- server health */

	/** Rows of array( label, value, status ok|warn|bad, advice ). */
	public static function health() {
		global $wpdb; $r = array();
		$add = function ( $l, $v, $s, $a = '' ) use ( &$r ) { $r[] = array( $l, $v, $s, $a ); };
		$php = PHP_VERSION;
		$add( 'PHP', $php, version_compare( $php, '8.1', '>=' ) ? 'ok' : ( version_compare( $php, '7.4', '>=' ) ? 'warn' : 'bad' ), version_compare( $php, '8.1', '>=' ) ? '' : __( 'Newer PHP versions are noticeably faster. Ask your host to move you to PHP 8.2 or newer.', 'sidcraft-rankboost' ) );
		$ml = wp_convert_hr_to_bytes( ini_get( 'memory_limit' ) );
		$add( __( 'PHP memory limit', 'sidcraft-rankboost' ), ini_get( 'memory_limit' ) . ' · ' . sprintf( /* translators: %s: memory */ __( 'this page used %s at its peak', 'sidcraft-rankboost' ), size_format( memory_get_peak_usage( true ), 0 ) ), ( -1 === $ml || $ml >= 256 * MB_IN_BYTES ) ? 'ok' : ( $ml >= 128 * MB_IN_BYTES ? 'warn' : 'bad' ), ( -1 === $ml || $ml >= 256 * MB_IN_BYTES ) ? '' : __( 'Page builders, WooCommerce and backups run out of memory below 256M. Raise memory_limit with your host.', 'sidcraft-rankboost' ) );
		$add( __( 'Time limit', 'sidcraft-rankboost' ), (string) ini_get( 'max_execution_time' ) . 's', ( 0 === (int) ini_get( 'max_execution_time' ) || (int) ini_get( 'max_execution_time' ) >= 60 ) ? 'ok' : 'warn', __( 'Under 60 seconds can cut off imports, backups and scans.', 'sidcraft-rankboost' ) );
		$ver = (string) $wpdb->get_var( 'SELECT VERSION()' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- asking the server for its version; nothing to cache.
		 $add( __( 'Database', 'sidcraft-rankboost' ), $ver, 'ok' );
		$oc = wp_using_ext_object_cache(); $add( __( 'Object cache (Redis/Memcached)', 'sidcraft-rankboost' ), $oc ? __( 'active', 'sidcraft-rankboost' ) : __( 'not used', 'sidcraft-rankboost' ), $oc ? 'ok' : 'warn', $oc ? '' : __( 'An object cache saves database work on busy or dynamic sites. Many hosts offer Redis for free.', 'sidcraft-rankboost' ) );
		$op = function_exists( 'opcache_get_status' ) && ini_get( 'opcache.enable' ); $add( 'OPcache', $op ? __( 'on', 'sidcraft-rankboost' ) : __( 'off', 'sidcraft-rankboost' ), $op ? 'ok' : 'bad', $op ? '' : __( 'OPcache keeps compiled PHP in memory. Turning it on is the cheapest big speed-up; ask your host.', 'sidcraft-rankboost' ) );
		$gz = extension_loaded( 'zlib' ); $add( __( 'Compression (zlib)', 'sidcraft-rankboost' ), $gz ? __( 'available', 'sidcraft-rankboost' ) : __( 'missing', 'sidcraft-rankboost' ), $gz ? 'ok' : 'warn', __( 'Pages should be sent gzip or brotli compressed; most hosts do this at the server.', 'sidcraft-rankboost' ) );
		$add( 'HTTPS', is_ssl() ? __( 'yes', 'sidcraft-rankboost' ) : __( 'no', 'sidcraft-rankboost' ), is_ssl() ? 'ok' : 'warn', is_ssl() ? '' : __( 'Browsers and search engines expect HTTPS.', 'sidcraft-rankboost' ) );
		$dbg = defined( 'WP_DEBUG' ) && WP_DEBUG; $add( 'WP_DEBUG', $dbg ? __( 'on', 'sidcraft-rankboost' ) : __( 'off', 'sidcraft-rankboost' ), $dbg ? 'warn' : 'ok', $dbg ? __( 'Fine while you are debugging. Turn it off on a live site: it slows pages and can show errors to visitors.', 'sidcraft-rankboost' ) : '' );
		if ( defined( 'SAVEQUERIES' ) && SAVEQUERIES ) { $add( 'SAVEQUERIES', __( 'on', 'sidcraft-rankboost' ), 'warn', __( 'Records every query in memory. Turn it off when you finish debugging.', 'sidcraft-rankboost' ) ); }
		$cron = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON; $add( 'WP-Cron', $cron ? __( 'run by the server', 'sidcraft-rankboost' ) : __( 'runs when visitors arrive', 'sidcraft-rankboost' ), 'ok', '' );
		list( $auto, ) = Sidcraft_RankBoost_DbClean::autoload();
		$add( __( 'Autoloaded options', 'sidcraft-rankboost' ), size_format( $auto, 1 ), $auto < 800000 ? 'ok' : ( $auto < 3000000 ? 'warn' : 'bad' ), $auto < 800000 ? '' : __( 'This data is loaded on every page. See the biggest entries on the Database screen; they usually belong to plugins you can remove.', 'sidcraft-rankboost' ) );
		$ap = count( (array) get_option( 'active_plugins', array() ) ); $add( __( 'Active plugins', 'sidcraft-rankboost' ), (string) $ap, $ap <= 25 ? 'ok' : 'warn', $ap <= 25 ? '' : __( 'Every plugin adds loading time. Remove ones you do not use.', 'sidcraft-rankboost' ) );
		if ( ! function_exists( 'get_plugins' ) ) { require_once ABSPATH . 'wp-admin/includes/plugin.php'; }
		$inactive = max( 0, count( get_plugins() ) - $ap ); $add( __( 'Inactive plugins', 'sidcraft-rankboost' ), (string) $inactive, 0 === $inactive ? 'ok' : 'warn', 0 === $inactive ? '' : __( 'Deactivated plugins still need updates and can be a way in for attackers. Delete the ones you will not use.', 'sidcraft-rankboost' ) );
		$up = wp_get_upload_dir(); $free = ! empty( $up['basedir'] ) ? @disk_free_space( $up['basedir'] ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- not available on every host.
		if ( false !== $free ) { $add( __( 'Free disk space', 'sidcraft-rankboost' ), size_format( $free, 1 ), $free > 500 * MB_IN_BYTES ? 'ok' : 'warn', $free > 500 * MB_IN_BYTES ? '' : __( 'Low disk space breaks uploads, caches and backups.', 'sidcraft-rankboost' ) ); }
		$s = Sidcraft_RankBoost_Speed::all(); $add( __( 'Page cache', 'sidcraft-rankboost' ), $s['cache'] ? __( 'on', 'sidcraft-rankboost' ) : __( 'off', 'sidcraft-rankboost' ), $s['cache'] ? 'ok' : 'warn', $s['cache'] ? '' : __( 'Turning on the page cache is the biggest speed-up for most sites.', 'sidcraft-rankboost' ) );
		return $r;
	}

	/** Time the home page three ways: fresh, then twice more so a cached copy can show. */
	public static function speed_test() {
		$home = home_url( '/' ); $rows = array();
		foreach ( array( array( __( 'Fresh page (cache bypassed)', 'sidcraft-rankboost' ), add_query_arg( 'sbs_t', wp_rand( 1000, 999999 ), $home ) ), array( __( 'Normal page, first visit', 'sidcraft-rankboost' ), $home ), array( __( 'Normal page, second visit', 'sidcraft-rankboost' ), $home ) ) as $t ) {
			$s = microtime( true ); $res = wp_remote_get( $t[1], array( 'timeout' => 20, 'redirection' => 3, 'cookies' => array(), 'user-agent' => 'SidcraftRankBoost-Test' ) ); $ms = (int) round( ( microtime( true ) - $s ) * 1000 );
			if ( is_wp_error( $res ) ) { $rows[] = array( $t[0], 0, 0, $res->get_error_message() ); continue; }
			$rows[] = array( $t[0], $ms, strlen( (string) wp_remote_retrieve_body( $res ) ), 'HIT' === wp_remote_retrieve_header( $res, 'x-sidcraft-cache' ) ? 'cached' : 'built by WordPress', (int) wp_remote_retrieve_response_code( $res ) );
		}
		return $rows;
	}
}
