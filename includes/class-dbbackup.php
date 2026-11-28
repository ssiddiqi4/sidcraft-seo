<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.WP.AlternativeFunctions -- a database dump reads WordPress's own tables directly, in small batches, and streams the text to the browser or to a private file.

/**
 * Database backup: writes the site's tables as one SQL file (structure and rows).
 * Streams in small batches, so memory stays low however large the database is.
 */
class Sidcraft_RankBoost_DbBackup {

	const SUBDIR = 'sidcraft-search-boost-suite/backups';

	/** Only administrators, and on a multisite network only super admins, may read a full dump. */
	public static function allowed() {
		return current_user_can( 'manage_options' ) && ( ! is_multisite() || is_super_admin() );
	}

	/** Names of this site's tables (everything that starts with the table prefix), views left out. */
	public static function tables() {
		global $wpdb;
		$rows = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix ) . '%' ) );
		$out  = array();
		foreach ( (array) $rows as $t ) {
			if ( is_string( $t ) && 0 === strpos( $t, $wpdb->prefix ) ) { $out[] = $t; }
		}
		sort( $out );
		return $out;
	}

	/** One SQL string value. Binary columns are written as hex so nothing is damaged. */
	private static function q( $v, $binary ) {
		if ( null === $v ) { return 'NULL'; }
		$v = (string) $v;
		if ( $binary && '' !== $v ) { return '0x' . bin2hex( $v ); }
		return "'" . str_replace( array( '\\', "'", "\n", "\r", "\0", "\x1a" ), array( '\\\\', "\\'", '\\n', '\\r', '\\0', '\\Z' ), $v ) . "'";
	}

	/**
	 * Write one table: DROP, CREATE, then its rows in batches.
	 *
	 * @param string   $table Table name.
	 * @param callable $write Receives each piece of text.
	 * @return bool False when the table could not be read (it is then skipped with a comment).
	 */
	public static function dump_table( $table, $write ) {
		global $wpdb;
		$create = $wpdb->get_row( $wpdb->prepare( 'SHOW CREATE TABLE %i', $table ), ARRAY_N );
		if ( ! is_array( $create ) || ! isset( $create[1] ) || 0 !== stripos( (string) $create[1], 'CREATE TABLE' ) ) {
			call_user_func( $write, "-- Skipped `{$table}` (not a table, or it could not be read)\n\n" );
			return false;
		}
		$cols = $wpdb->get_results( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table ), ARRAY_A );
		$bin  = array(); $names = array(); $pk = ''; $pk_int = false; $pk_count = 0;
		foreach ( (array) $cols as $c ) {
			$names[] = '`' . str_replace( '`', '``', $c['Field'] ) . '`';
			$bin[]   = (bool) preg_match( '/blob|binary|bit/i', (string) $c['Type'] );
			if ( 'PRI' === $c['Key'] ) { $pk_count++; $pk = $c['Field']; $pk_int = (bool) preg_match( '/int/i', (string) $c['Type'] ); }
		}
		$ident = '`' . str_replace( '`', '``', $table ) . '`';
		call_user_func( $write, "--\n-- Table structure for {$ident}\n--\n\nDROP TABLE IF EXISTS {$ident};\n" . $create[1] . ";\n\n" );
		if ( ! $names ) { return true; }
		$head   = "INSERT INTO {$ident} (" . implode( ',', $names ) . ") VALUES\n";
		$last   = null; $offset = 0; $limit = 200; $wrote = false;
		$keyset = 1 === $pk_count && $pk_int;
		while ( true ) {
			if ( $keyset ) {
				$rows = null === $last
					? $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY %i ASC LIMIT %d', $table, $pk, $limit ), ARRAY_N )
					: $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE %i > %s ORDER BY %i ASC LIMIT %d', $table, $pk, $last, $pk, $limit ), ARRAY_N );
			} else {
				$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i LIMIT %d OFFSET %d', $table, $limit, $offset ), ARRAY_N );
			}
			if ( ! $rows ) { break; }
			$vals = array(); $size = 0;
			foreach ( $rows as $r ) {
				$parts = array();
				foreach ( $r as $i => $v ) { $parts[] = self::q( $v, ! empty( $bin[ $i ] ) ); }
				$line   = '(' . implode( ',', $parts ) . ')';
				$vals[] = $line; $size += strlen( $line );
				if ( $size > 700000 ) { call_user_func( $write, $head . implode( ",\n", $vals ) . ";\n" ); $vals = array(); $size = 0; $wrote = true; }
			}
			if ( $vals ) { call_user_func( $write, $head . implode( ",\n", $vals ) . ";\n" ); $wrote = true; }
			$offset += count( $rows );
			if ( $keyset ) {
				$idx  = array_search( $pk, array_map( static function ( $n ) { return trim( $n, '`' ); }, $names ), true );
				$tail = end( $rows );
				$last = false !== $idx && isset( $tail[ $idx ] ) ? $tail[ $idx ] : null;
				if ( null === $last ) { break; }
			}
			if ( count( $rows ) < $limit ) { break; }
			if ( connection_aborted() ) { return false; }
		}
		call_user_func( $write, ( $wrote ? "\n" : '' ) );
		return true;
	}

	/** SQL file header, so the file says where it came from and restores cleanly. */
	public static function header_text( $what ) {
		global $wpdb;
		return "-- Sidcraft Search Boost Suite database backup\n-- {$what}\n-- Site: " . home_url( '/' ) . "\n-- Date (UTC): " . gmdate( 'Y-m-d H:i:s' ) . "\n-- WordPress " . get_bloginfo( 'version' ) . ' / MySQL ' . $wpdb->db_version() . "\n--\n-- Restore with phpMyAdmin (Import) or: mysql -u USER -p DATABASE < database.sql\n\n/*!40101 SET NAMES utf8mb4 */;\n/*!40103 SET TIME_ZONE='+00:00' */;\n/*!40014 SET FOREIGN_KEY_CHECKS=0 */;\n/*!40101 SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;\n\n";
	}

	public static function footer_text() { return "\n/*!40014 SET FOREIGN_KEY_CHECKS=1 */;\n-- End of backup\n"; }

	/** Send the whole database to the browser as database.sql. Does not return. */
	public static function download() {
		if ( ! self::allowed() ) { wp_die( esc_html__( 'Not allowed.', 'sidcraft-search-boost-suite' ), 403 ); }
		if ( headers_sent() ) { wp_die( esc_html__( 'Output has already started, so the download cannot begin. Check for stray output or warnings and try again.', 'sidcraft-search-boost-suite' ) ); }
		$tables = self::tables();
		while ( ob_get_level() > 0 ) { ob_end_clean(); }
		nocache_headers();
		header( 'Content-Type: application/sql; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="database.sql"' );
		header( 'X-Content-Type-Options: nosniff' );
		$sent  = 0;
		$write = static function ( $s ) use ( &$sent ) { echo $s; $sent += strlen( $s ); if ( $sent > 262144 ) { $sent = 0; flush(); } }; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- this is an SQL file, not HTML.
		$write( self::header_text( count( $tables ) . ' tables' ) );
		foreach ( $tables as $t ) {
			if ( connection_aborted() ) { break; }
			self::dump_table( $t, $write );
		}
		$write( self::footer_text() );
		exit;
	}

	/* ------------------------------------------- private copies kept on the server */

	/** Folder for the copies saved before a clean-up: not readable from the web. Empty string if it cannot be made. */
	public static function dir() {
		$up = wp_get_upload_dir();
		if ( ! empty( $up['error'] ) ) { return ''; }
		$d = trailingslashit( $up['basedir'] ) . self::SUBDIR;
		if ( ! wp_mkdir_p( $d ) ) { return ''; }
		foreach ( array( 'index.php' => "<?php\n// Silence is golden.\n", '.htaccess' => "Require all denied\nDeny from all\n", 'web.config' => '<?xml version="1.0"?><configuration><system.webServer><authorization><deny users="*" /></authorization></system.webServer></configuration>' ) as $f => $body ) {
			if ( ! file_exists( $d . '/' . $f ) ) { file_put_contents( $d . '/' . $f, $body ); }
		}
		return $d;
	}

	/** Saved copies, newest first: name, size, time. */
	public static function saved() {
		$d = self::dir(); $out = array();
		if ( '' === $d ) { return $out; }
		foreach ( (array) glob( $d . '/cleanup-*.sql' ) as $f ) {
			if ( self::valid_name( basename( $f ) ) ) { $out[] = array( 'name' => basename( $f ), 'size' => (int) filesize( $f ), 'time' => (int) filemtime( $f ) ); }
		}
		usort( $out, static function ( $a, $b ) { return $b['time'] <=> $a['time']; } );
		return $out;
	}

	public static function valid_name( $n ) { return is_string( $n ) && (bool) preg_match( '/^cleanup-\d{8}-\d{6}-[0-9a-f]{16}\.sql$/', $n ); }

	public static function send_saved( $name ) {
		if ( ! self::allowed() || ! self::valid_name( $name ) ) { wp_die( esc_html__( 'Not allowed.', 'sidcraft-search-boost-suite' ), 403 ); }
		$f = self::dir() . '/' . $name;
		if ( '' === self::dir() || ! is_file( $f ) ) { wp_die( esc_html__( 'That file no longer exists.', 'sidcraft-search-boost-suite' ), 404 ); }
		while ( ob_get_level() > 0 ) { ob_end_clean(); }
		nocache_headers();
		header( 'Content-Type: application/sql; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $name . '"' );
		header( 'Content-Length: ' . filesize( $f ) );
		header( 'X-Content-Type-Options: nosniff' );
		readfile( $f );
		exit;
	}

	public static function delete_saved( $name ) {
		if ( ! self::allowed() || ! self::valid_name( $name ) ) { return false; }
		$f = self::dir() . '/' . $name;
		if ( '' === self::dir() || ! is_file( $f ) ) { return false; }
		wp_delete_file( $f );
		return ! is_file( $f );
	}
}
