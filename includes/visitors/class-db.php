<?php
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- this plugin stores its own statistics in its own table; the table name and column names come from fixed whitelists, every value goes through $wpdb->prepare(), and results change on every visit so caching would be wrong.
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** The statistics table and every query against it. */
class Sidcraft_VS_DB {

	const DB_VERSION = '1';

	public static function table() { global $wpdb; return $wpdb->prefix . 'svs_hits'; }

	public static function install() {
		global $wpdb; require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$t = self::table(); $c = $wpdb->get_charset_collate();
		dbDelta( "CREATE TABLE {$t} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  ts datetime NOT NULL,
  day date NOT NULL,
  hr tinyint(3) unsigned NOT NULL DEFAULT 0,
  visitor char(16) NOT NULL,
  path varchar(255) NOT NULL,
  title varchar(160) NOT NULL DEFAULT '',
  status smallint(5) unsigned NOT NULL DEFAULT 200,
  ref_host varchar(120) NOT NULL DEFAULT '',
  source varchar(16) NOT NULL DEFAULT 'direct',
  campaign varchar(80) NOT NULL DEFAULT '',
  browser varchar(24) NOT NULL DEFAULT '',
  os varchar(24) NOT NULL DEFAULT '',
  device varchar(10) NOT NULL DEFAULT '',
  country char(2) NOT NULL DEFAULT '',
  duration smallint(5) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  KEY day_visitor (day,visitor),
  KEY day_path (day,path(100)),
  KEY ts (ts)
) {$c};" );
		update_option( 'sidcraft_vs_db', self::DB_VERSION, false );
		if ( ! get_option( 'sidcraft_vs_salt' ) ) { update_option( 'sidcraft_vs_salt', wp_generate_password( 40, true, true ), false ); }
		if ( ! wp_next_scheduled( 'sidcraft_vs_purge' ) ) { wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'sidcraft_vs_purge' ); }
	}

	public static function maybe_upgrade() {
		if ( get_option( 'sidcraft_vs_db' ) !== self::DB_VERSION ) { self::install(); }
		if ( ! wp_next_scheduled( 'sidcraft_vs_purge' ) ) { wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'sidcraft_vs_purge' ); }
	}

	/* ------------------------------------------------------------ writes */

	public static function insert( array $row ) {
		global $wpdb;
		$ok = $wpdb->insert( self::table(), $row, array( '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' ) );
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	public static function set_duration( $id, $seconds ) {
		global $wpdb; $t = self::table();
		return $wpdb->query( $wpdb->prepare( "UPDATE %i SET duration = %d WHERE id = %d AND duration < %d", $t, $seconds, $id, $seconds ) );
	}

	public static function purge( $days ) {
		if ( $days < 1 ) { return 0; }
		global $wpdb; $t = self::table();
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE day < %s", $t, wp_date( 'Y-m-d', time() - $days * DAY_IN_SECONDS ) ) );
	}

	public static function delete_all() { global $wpdb; $t = self::table(); return (int) $wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $t ) ); }

	public static function count_rows() { global $wpdb; $t = self::table(); return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $t ) ); }

	/* ----------------------------------------------------------- reports */

	/** Page views and distinct visitors for each day. Visitor ids change every day, so a visitor is counted once per day. */
	public static function series( $from, $to ) {
		global $wpdb; $t = self::table();
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT day, COUNT(*) AS p, COUNT(DISTINCT visitor) AS v FROM %i WHERE day BETWEEN %s AND %s AND status = 200 AND device <> 'bot' GROUP BY day ORDER BY day", $t, $from, $to ), ARRAY_A );
		$by = array(); foreach ( (array) $rows as $r ) { $by[ $r['day'] ] = array( (int) $r['v'], (int) $r['p'] ); }
		$out = array(); $d = strtotime( $from . ' 00:00:00 UTC' ); $e = strtotime( $to . ' 00:00:00 UTC' ); $n = 0;
		while ( $d <= $e && $n++ < 800 ) { $k = gmdate( 'Y-m-d', $d ); $out[] = array( 'd' => $k, 'v' => isset( $by[ $k ] ) ? $by[ $k ][0] : 0, 'p' => isset( $by[ $k ] ) ? $by[ $k ][1] : 0 ); $d += DAY_IN_SECONDS; }
		return $out;
	}

	/** Totals for a period: visitors, page views, visits that viewed one page only, average seconds on a page. */
	public static function summary( $from, $to ) {
		global $wpdb; $t = self::table(); $s = self::series( $from, $to );
		$v = 0; $p = 0; foreach ( $s as $r ) { $v += $r['v']; $p += $r['p']; }
		$bounce = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM (SELECT 1 FROM %i WHERE day BETWEEN %s AND %s AND status = 200 AND device <> 'bot' GROUP BY day, visitor HAVING COUNT(*) = 1) x", $t, $from, $to ) );
		$dur    = (float) $wpdb->get_var( $wpdb->prepare( "SELECT AVG(duration) FROM %i WHERE day BETWEEN %s AND %s AND status = 200 AND device <> 'bot' AND duration > 0", $t, $from, $to ) );
		return array( 'visitors' => $v, 'views' => $p, 'pps' => $v ? round( $p / $v, 2 ) : 0, 'bounce' => $v ? round( 100 * $bounce / $v, 1 ) : 0, 'duration' => (int) round( $dur ) );
	}

	public static function top( $col, $from, $to, $limit = 10 ) {
		$ok = array( 'path', 'ref_host', 'source', 'campaign', 'country', 'browser', 'os', 'device' );
		if ( ! in_array( $col, $ok, true ) ) { return array(); }
		global $wpdb; $t = self::table(); $limit = max( 1, min( 100, (int) $limit ) );
		$more = 'source' === $col ? " AND source <> 'internal'" : ( 'ref_host' === $col ? " AND source <> 'internal'" : '' );
		$title = 'path' === $col ? ', MAX(title) AS title' : ", '' AS title";
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT %i AS v, COUNT(*) AS views, COUNT(DISTINCT visitor) AS visitors{$title} FROM %i WHERE day BETWEEN %s AND %s AND status = 200 AND device <> 'bot' AND %i <> ''{$more} GROUP BY %i ORDER BY views DESC, v ASC LIMIT %d", $col, $t, $from, $to, $col, $col, $limit ), ARRAY_A );
		if ( ! is_array( $rows ) ) { return array(); }
		foreach ( $rows as &$r ) { $r['views'] = (int) $r['views']; $r['visitors'] = (int) $r['visitors']; } unset( $r );
		return is_array( $rows ) ? $rows : array();
	}

	public static function not_found( $from, $to, $limit = 10 ) {
		global $wpdb; $t = self::table();
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT path AS v, COUNT(*) AS views, COUNT(DISTINCT visitor) AS visitors, '' AS title FROM %i WHERE day BETWEEN %s AND %s AND status = 404 AND device <> 'bot' GROUP BY path ORDER BY views DESC LIMIT %d", $t, $from, $to, $limit ), ARRAY_A );
		if ( ! is_array( $rows ) ) { return array(); }
		foreach ( $rows as &$r ) { $r['views'] = (int) $r['views']; $r['visitors'] = (int) $r['visitors']; } unset( $r );
		return is_array( $rows ) ? $rows : array();
	}

	/** Page views for each hour of the day (site time zone). */
	public static function hours( $from, $to ) {
		global $wpdb; $t = self::table(); $out = array_fill( 0, 24, 0 );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT hr, COUNT(*) AS p FROM %i WHERE day BETWEEN %s AND %s AND status = 200 AND device <> 'bot' GROUP BY hr", $t, $from, $to ), ARRAY_A );
		foreach ( (array) $rows as $r ) { $h = (int) $r['hr']; if ( $h >= 0 && $h < 24 ) { $out[ $h ] = (int) $r['p']; } }
		return $out;
	}

	public static function online( $minutes = 5 ) {
		global $wpdb; $t = self::table();
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT visitor) FROM %i WHERE ts >= %s AND device <> 'bot'", $t, gmdate( 'Y-m-d H:i:s', time() - $minutes * MINUTE_IN_SECONDS ) ) );
	}

	public static function bots( $from, $to ) {
		global $wpdb; $t = self::table();
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE day BETWEEN %s AND %s AND device = 'bot'", $t, $from, $to ) );
	}

	/** Raw rows for the CSV export. */
	public static function export_rows( $from, $to, $limit = 100000 ) {
		global $wpdb; $t = self::table();
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT ts, path, title, status, source, ref_host, campaign, browser, os, device, country, duration FROM %i WHERE day BETWEEN %s AND %s ORDER BY id LIMIT %d", $t, $from, $to, $limit ), ARRAY_A );
	}
}
