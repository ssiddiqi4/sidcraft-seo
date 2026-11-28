<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- database clean-up works on WordPress's own tables; table names come from $wpdb and every value is prepared.

/** Finds and removes database clutter: old revisions, spam, expired transients, orphaned rows. Nothing is removed until you press a button. */
class Sidcraft_RankBoost_DbClean {

	const HOOK = 'sidcraft_rankboost_db_auto';

	public static function init() {
		add_action( self::HOOK, array( __CLASS__, 'auto' ) );
	}

	/** What can be cleaned: key => label, help. */
	public static function items() {
		return array(
			'revisions'   => array( __( 'Old post revisions', 'sidcraft-search-boost-suite' ), __( 'Saved copies of every edit. The newest few per post are kept (set above).', 'sidcraft-search-boost-suite' ) ),
			'autodrafts'  => array( __( 'Auto-drafts', 'sidcraft-search-boost-suite' ), __( 'Empty drafts WordPress creates when you open “Add new” and never save.', 'sidcraft-search-boost-suite' ) ),
			'trash'       => array( __( 'Posts and pages in the Trash', 'sidcraft-search-boost-suite' ), __( 'Items you already deleted.', 'sidcraft-search-boost-suite' ) ),
			'spam'        => array( __( 'Spam comments', 'sidcraft-search-boost-suite' ), __( 'Comments marked as spam.', 'sidcraft-search-boost-suite' ) ),
			'trashcomm'   => array( __( 'Trashed comments', 'sidcraft-search-boost-suite' ), __( 'Comments you already deleted.', 'sidcraft-search-boost-suite' ) ),
			'transients'  => array( __( 'Expired transients', 'sidcraft-search-boost-suite' ), __( 'Temporary data stored in the options table that has already expired.', 'sidcraft-search-boost-suite' ) ),
			'oembed'      => array( __( 'Embed (oEmbed) caches', 'sidcraft-search-boost-suite' ), __( 'Saved copies of embedded videos and tweets. They are fetched again when needed.', 'sidcraft-search-boost-suite' ) ),
			'orph_post'   => array( __( 'Orphaned post meta', 'sidcraft-search-boost-suite' ), __( 'Extra post data whose post no longer exists.', 'sidcraft-search-boost-suite' ) ),
			'orph_comm'   => array( __( 'Orphaned comment meta', 'sidcraft-search-boost-suite' ), __( 'Extra comment data whose comment no longer exists.', 'sidcraft-search-boost-suite' ) ),
			'orph_rel'    => array( __( 'Orphaned category/tag links', 'sidcraft-search-boost-suite' ), __( 'Category and tag links to posts that no longer exist.', 'sidcraft-search-boost-suite' ) ),
			'log404'      => array( __( 'Old broken-URL log entries', 'sidcraft-search-boost-suite' ), __( 'Search Boost Suite’s own 404 log entries not seen for 60 days.', 'sidcraft-search-boost-suite' ) ),
		);
	}

	/** Number of rows each item would remove. */
	public static function counts() {
		global $wpdb; $keep = (int) Sidcraft_RankBoost_Speed::all()['db_keep_rev']; $c = array();
		$c['revisions']  = self::extra_revisions( $keep, true );
		$c['autodrafts'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'auto-draft'" );
		$c['trash']      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'trash'" );
		$c['spam']       = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'spam'" );
		$c['trashcomm']  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'trash'" );
		$c['transients'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d", $wpdb->esc_like( '_transient_timeout_' ) . '%', time() ) ) + (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d", $wpdb->esc_like( '_site_transient_timeout_' ) . '%', time() ) );
		$c['oembed']     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $wpdb->esc_like( '_oembed_' ) . '%' ) );
		$c['orph_post']  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id NOT IN ( SELECT ID FROM {$wpdb->posts} )" );
		$c['orph_comm']  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->commentmeta} WHERE comment_id NOT IN ( SELECT comment_ID FROM {$wpdb->comments} )" );
		$c['orph_rel']   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->term_relationships} WHERE object_id NOT IN ( SELECT ID FROM {$wpdb->posts} ) AND term_taxonomy_id IN ( SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy IN ( 'category', 'post_tag' ) )" );
		$c['log404']     = self::log_table() ? (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE last_seen < %s', self::log_table(), gmdate( 'Y-m-d H:i:s', time() - 60 * DAY_IN_SECONDS ) ) ) : 0;
		return $c;
	}

	private static function log_table() {
		global $wpdb; $t = $wpdb->prefix . 'sidcraft_rankboost_404';
		return $t === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $t ) ) ) ? $t : '';
	}

	/** IDs of revisions beyond the newest $keep for their post (or just the count). */
	private static function extra_revisions( $keep, $count_only = false, $limit = 2000 ) {
		global $wpdb;
		if ( $keep <= 0 ) {
			if ( $count_only ) { return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'" ); }
			return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'revision' LIMIT %d", $limit ) ) );
		}
		$rows = $wpdb->get_results( "SELECT ID, post_parent FROM {$wpdb->posts} WHERE post_type = 'revision' ORDER BY post_parent ASC, post_date DESC, ID DESC LIMIT 200000" );
		$seen = array(); $ids = array();
		foreach ( (array) $rows as $r ) { $p = (int) $r->post_parent; $seen[ $p ] = isset( $seen[ $p ] ) ? $seen[ $p ] + 1 : 1; if ( $seen[ $p ] > $keep ) { $ids[] = (int) $r->ID; } }
		return $count_only ? count( $ids ) : array_slice( $ids, 0, $limit );
	}

	/** Clean the chosen items. Returns key => rows removed. */
	public static function run( $keys ) {
		global $wpdb; $out = array(); $keep = (int) Sidcraft_RankBoost_Speed::all()['db_keep_rev']; $end = microtime( true ) + 20;
		foreach ( $keys as $k ) {
			$n = 0;
			switch ( $k ) {
				case 'revisions':
					do { $ids = self::extra_revisions( $keep, false, 300 ); foreach ( $ids as $id ) { wp_delete_post_revision( $id ); $n++; } } while ( $ids && microtime( true ) < $end );
					break;
				case 'autodrafts':
					do { $ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'auto-draft' LIMIT 300" ); foreach ( $ids as $id ) { wp_delete_post( (int) $id, true ); $n++; } } while ( $ids && microtime( true ) < $end );
					break;
				case 'trash':
					do { $ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'trash' LIMIT 300" ); foreach ( $ids as $id ) { wp_delete_post( (int) $id, true ); $n++; } } while ( $ids && microtime( true ) < $end );
					break;
				case 'spam':
				case 'trashcomm':
					$st = 'spam' === $k ? 'spam' : 'trash';
					do { $ids = $wpdb->get_col( $wpdb->prepare( "SELECT comment_ID FROM {$wpdb->comments} WHERE comment_approved = %s LIMIT 300", $st ) ); foreach ( $ids as $id ) { wp_delete_comment( (int) $id, true ); $n++; } } while ( $ids && microtime( true ) < $end );
					break;
				case 'transients':
					foreach ( array( '_transient_timeout_' => 'delete_transient', '_site_transient_timeout_' => 'delete_site_transient' ) as $pre => $fn ) {
						do {
							$names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d LIMIT 500", $wpdb->esc_like( $pre ) . '%', time() ) );
							foreach ( $names as $nm ) { $fn( substr( $nm, strlen( $pre ) ) ); $n++; }
						} while ( $names && microtime( true ) < $end );
					}
					break;
				case 'oembed':
					$n = (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $wpdb->esc_like( '_oembed_' ) . '%' ) );
					break;
				case 'orph_post':
					$n = (int) $wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE post_id NOT IN ( SELECT ID FROM {$wpdb->posts} )" );
					break;
				case 'orph_comm':
					$n = (int) $wpdb->query( "DELETE FROM {$wpdb->commentmeta} WHERE comment_id NOT IN ( SELECT comment_ID FROM {$wpdb->comments} )" );
					break;
				case 'orph_rel':
					$n = (int) $wpdb->query( "DELETE FROM {$wpdb->term_relationships} WHERE object_id NOT IN ( SELECT ID FROM {$wpdb->posts} ) AND term_taxonomy_id IN ( SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy IN ( 'category', 'post_tag' ) )" );
					break;
				case 'log404':
					if ( self::log_table() ) { $n = (int) $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE last_seen < %s', self::log_table(), gmdate( 'Y-m-d H:i:s', time() - 60 * DAY_IN_SECONDS ) ) ); }
					break;
				default:
					continue 2;
			}
			$out[ $k ] = $n;
		}
		return $out;
	}

	public static function auto() { Sidcraft_RankBoost_Speed::plan_next( self::HOOK, (int) Sidcraft_RankBoost_Speed::all()['db_days'] ); self::run( array( 'revisions', 'autodrafts', 'trash', 'spam', 'trashcomm', 'transients', 'orph_post', 'orph_comm', 'orph_rel', 'log404' ) ); self::optimize(); }

	/** Tables with size and reclaimable space. */
	public static function tables() {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT TABLE_NAME AS name, ENGINE AS engine, DATA_LENGTH AS data, INDEX_LENGTH AS idx, DATA_FREE AS free, TABLE_ROWS AS nrows FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME LIKE %s ORDER BY DATA_LENGTH + INDEX_LENGTH DESC', DB_NAME, $wpdb->esc_like( $wpdb->prefix ) . '%' ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	public static function is_mysql() { global $wpdb; return empty( $wpdb->is_sqlite ) && ! class_exists( 'WP_SQLite_DB' ) && ! defined( 'SQLITE_DB_DROPIN_VERSION' ) && $wpdb->get_var( 'SELECT VERSION()' ) && false === stripos( (string) $wpdb->get_var( 'SELECT VERSION()' ), 'sqlite' ); }

	/** OPTIMIZE TABLE on the site's tables that have wasted space. Returns the number of tables done. */
	public static function optimize() {
		global $wpdb; $n = 0;
		if ( ! self::is_mysql() ) { return 0; }
		foreach ( self::tables() as $t ) {
			if ( (int) $t['free'] > 100000 ) { $wpdb->query( $wpdb->prepare( 'OPTIMIZE TABLE %i', $t['name'] ) ); $n++; }
		}
		return $n;
	}

	/** Autoloaded options: total size and the biggest ones (these are read on every page load). */
	public static function autoload() {
		global $wpdb;
		$tot = (int) $wpdb->get_var( "SELECT SUM( LENGTH( option_value ) ) FROM {$wpdb->options} WHERE autoload IN ( 'yes', 'on', 'auto-on', 'auto' )" );
		$top = $wpdb->get_results( "SELECT option_name AS name, LENGTH( option_value ) AS bytes FROM {$wpdb->options} WHERE autoload IN ( 'yes', 'on', 'auto-on', 'auto' ) ORDER BY bytes DESC LIMIT 10", ARRAY_A );
		return array( $tot, is_array( $top ) ? $top : array() );
	}
}
