<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Unlinked content and the Archive.
 *
 * Finds pages and posts that nothing on the site links to (other content, page-builder data, menus, widgets,
 * theme settings, front page, posts page, privacy page) and lets you move them to a hidden Archive status that
 * can be restored at any time. The scan reads the site a small chunk at a time, so it stays light on large sites.
 */
class Sidcraft_RankBoost_Unlinked {

	const LIST   = 'sidcraft_rankboost_unlinked';       // Last result: time, ids, checked.
	const STATE  = 'sidcraft_rankboost_unlinked_state'; // Scan in progress.
	const EVENT  = 'sidcraft_rankboost_unlinked_more';
	const LOCK   = 'sidcraft_rankboost_unlinked_lock';
	const STATUS = 'ucar_archive';                      // Same status as the former stand-alone plugin, so archived items carry over.
	const PREV   = '_ucar_prev_status';
	const TYPES  = array( 'page', 'post' );
	const LIVE   = array( 'publish', 'private', 'draft', 'pending', 'future' );
	const SKIP   = array( 'attachment', 'revision', 'customize_changeset', 'oembed_cache', 'user_request' );
	const FIXED  = array( 'page_on_front', 'page_for_posts', 'wp_page_for_privacy_policy' );
	const CHUNK  = 100;

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_status' ) );
		add_action( self::EVENT, array( __CLASS__, 'event' ) );
		if ( ! is_admin() ) { return; }
		foreach ( self::TYPES as $t ) {
			add_filter( "views_edit-{$t}", function ( $views ) use ( $t ) { return Sidcraft_RankBoost_Unlinked::views( $views, $t ); } );
			add_filter( "bulk_actions-edit-{$t}", array( __CLASS__, 'bulk_actions' ) );
			add_filter( "handle_bulk_actions-edit-{$t}", array( __CLASS__, 'handle_bulk' ), 10, 3 );
		}
		add_filter( 'page_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
		add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
		add_action( 'admin_post_sidcraft_rankboost_restore', array( __CLASS__, 'handle_restore' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
	}

	/* ---------- the Archive status ---------- */

	public static function register_status() {
		register_post_status( self::STATUS, array(
			'label'                     => _x( 'Archive', 'post status', 'sidcraft-search-boost-suite' ),
			/* translators: %s: number of archived items */
			'label_count'               => _n_noop( 'Archive <span class="count">(%s)</span>', 'Archive <span class="count">(%s)</span>', 'sidcraft-search-boost-suite' ),
			'public'                    => false,
			'internal'                  => false,
			'private'                   => false,
			'protected'                 => false,
			'exclude_from_search'       => true,
			'show_in_admin_all_list'    => false,
			'show_in_admin_status_list' => false,
		) );
	}

	/** Adds an "Archive (n)" view after Trash. */
	public static function views( $views, $type ) {
		$counts  = wp_count_posts( $type );
		$count   = isset( $counts->{self::STATUS} ) ? (int) $counts->{self::STATUS} : 0;
		$current = isset( $_GET['post_status'] ) && self::STATUS === sanitize_key( wp_unslash( $_GET['post_status'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- view switch only.
		$link    = sprintf( '<a href="%1$s"%2$s>%3$s <span class="count">(%4$s)</span></a>', esc_url( add_query_arg( array( 'post_type' => $type, 'post_status' => self::STATUS ), admin_url( 'edit.php' ) ) ), $current ? ' class="current" aria-current="page"' : '', esc_html__( 'Archive', 'sidcraft-search-boost-suite' ), esc_html( number_format_i18n( $count ) ) );
		$out = array();
		$placed = false;
		foreach ( $views as $key => $html ) {
			$out[ $key ] = $html;
			if ( 'trash' === $key ) { $out[ self::STATUS ] = $link; $placed = true; }
		}
		if ( ! $placed ) { $out[ self::STATUS ] = $link; }
		return $out;
	}

	public static function bulk_actions( $actions ) {
		if ( isset( $_REQUEST['post_status'] ) && self::STATUS === sanitize_key( wp_unslash( $_REQUEST['post_status'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- list filter.
			$actions['sidcraft_rankboost_restore'] = __( 'Restore', 'sidcraft-search-boost-suite' );
		}
		return $actions;
	}

	public static function row_actions( $actions, $post ) {
		if ( self::STATUS !== $post->post_status || ! current_user_can( 'edit_post', $post->ID ) ) { return $actions; }
		$url = wp_nonce_url( admin_url( 'admin-post.php?action=sidcraft_rankboost_restore&post=' . (int) $post->ID ), 'sidcraft_rankboost_restore_' . (int) $post->ID );
		return array( 'sidcraft_rankboost_restore' => '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Restore', 'sidcraft-search-boost-suite' ) . '</a>' ) + $actions;
	}

	public static function handle_restore() {
		$id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked below.
		check_admin_referer( 'sidcraft_rankboost_restore_' . $id );
		if ( ! $id || ! current_user_can( 'edit_post', $id ) ) { wp_die( esc_html__( 'You are not allowed to do this.', 'sidcraft-search-boost-suite' ), 403 ); }
		$n    = self::restore( $id ) ? 1 : 0;
		$post = get_post( $id );
		$back = $post ? admin_url( 'edit.php?post_status=' . self::STATUS . '&post_type=' . $post->post_type ) : admin_url( 'edit.php' );
		wp_safe_redirect( add_query_arg( array( 'sidcraft_msg' => 'restored', 'sidcraft_n' => $n ), $back ) );
		exit;
	}

	/** Back to the status it had before archiving (Draft when unknown). */
	public static function restore( $id ) {
		if ( self::STATUS !== get_post_status( $id ) ) { return false; }
		$prev   = (string) get_post_meta( $id, self::PREV, true );
		$status = in_array( $prev, array( 'publish', 'private', 'pending', 'draft' ), true ) ? $prev : 'draft';
		$ok     = wp_update_post( array( 'ID' => $id, 'post_status' => $status ) );
		if ( $ok && ! is_wp_error( $ok ) ) { delete_post_meta( $id, self::PREV ); return true; }
		return false;
	}

	public static function handle_bulk( $redirect, $action, $ids ) {
		if ( 'sidcraft_rankboost_restore' !== $action ) { return $redirect; }
		$n = 0;
		foreach ( (array) $ids as $id ) {
			$id = (int) $id;
			if ( $id && current_user_can( 'edit_post', $id ) && self::restore( $id ) ) { ++$n; }
		}
		return add_query_arg( array( 'sidcraft_msg' => 'restored', 'sidcraft_n' => $n ), $redirect );
	}

	public static function notices() {
		if ( ! isset( $_GET['sidcraft_msg'] ) || ! current_user_can( 'edit_pages' ) ) { return; } // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$n = isset( $_GET['sidcraft_n'] ) ? absint( $_GET['sidcraft_n'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'restored' !== sanitize_key( wp_unslash( $_GET['sidcraft_msg'] ) ) ) { return; } // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		/* translators: %s: number of items */
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( __( '%s item(s) restored.', 'sidcraft-search-boost-suite' ), number_format_i18n( $n ) ) ) . '</p></div>';
	}

	/** Move the chosen items to the Archive. Returns the number archived. */
	public static function archive( $ids ) {
		$done = array();
		foreach ( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) as $id ) {
			$post = get_post( $id );
			if ( ! $post || ! in_array( $post->post_type, self::TYPES, true ) || ! in_array( $post->post_status, self::LIVE, true ) || ! current_user_can( 'edit_post', $id ) ) { continue; }
			$ok = wp_update_post( array( 'ID' => $id, 'post_status' => self::STATUS ) );
			if ( $ok && ! is_wp_error( $ok ) ) { update_post_meta( $id, self::PREV, $post->post_status ); $done[] = $id; }
		}
		$data = self::result();
		if ( $done && ! empty( $data['ids'] ) ) {
			$data['ids'] = array_values( array_diff( array_map( 'intval', $data['ids'] ), $done ) );
			update_option( self::LIST, $data, false );
		}
		return count( $done );
	}

	/* ---------- the scan (a chunk at a time) ---------- */

	public static function result() {
		$d = get_option( self::LIST );
		return is_array( $d ) ? $d : array();
	}

	public static function state() {
		$s = get_option( self::STATE );
		return is_array( $s ) ? $s : array();
	}

	/** Begin a new scan: list the candidates (IDs and addresses only, no text). */
	public static function start() {
		global $wpdb;
		$in   = implode( ',', array_fill( 0, count( self::LIVE ), '%s' ) );
		$ty   = implode( ',', array_fill( 0, count( self::TYPES ), '%s' ) );
		$rows = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type IN ($ty) AND post_status IN ($in) ORDER BY ID ASC", array_merge( self::TYPES, self::LIVE ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- placeholders are generated above.
		$cand = array();
		$by   = array();
		foreach ( (array) $rows as $id ) {
			$id = (int) $id;
			$cand[] = $id;
			$key = self::path_key( get_permalink( $id ) );
			if ( '' !== $key ) { $by[ $key ] = $id; }
		}
		// Category, tag and other archive pages list their posts, so a link to one counts as a link to its posts (stored as negative term IDs).
		foreach ( get_taxonomies( array( 'public' => true ), 'names' ) as $tax ) {
			if ( 'post_format' === $tax ) { continue; }
			$terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => true, 'fields' => 'ids', 'number' => 5000 ) );
			foreach ( is_wp_error( $terms ) ? array() : $terms as $tid ) {
				$link = get_term_link( (int) $tid, $tax );
				$key  = is_wp_error( $link ) ? '' : self::path_key( $link );
				if ( '' !== $key && ! isset( $by[ $key ] ) ) { $by[ $key ] = -(int) $tid; }
			}
		}
		$linked = array();
		foreach ( self::FIXED as $o ) { $id = (int) get_option( $o ); if ( $id ) { $linked[ $id ] = 1; } }
		update_option( self::STATE, array( 'phase' => 'posts', 'cursor' => 0, 'started' => time(), 'cand' => $cand, 'by' => $by, 'linked' => $linked, 'lterms' => array(), 'seen' => 0 ), false );
	}

	/** Do some work. Returns true while more remains. */
	public static function batch( $seconds = 15 ) {
		global $wpdb;
		$st = self::state();
		if ( ! $st ) { return false; }
		$deadline = time() + $seconds;
		$types    = array_values( array_diff( get_post_types(), self::SKIP ) );
		$tin      = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		$lin      = implode( ',', array_fill( 0, count( self::LIVE ), '%s' ) );
		while ( 'posts' === $st['phase'] && time() < $deadline ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_type, post_content, post_excerpt FROM {$wpdb->posts} WHERE ID > %d AND post_type IN ($tin) AND post_status IN ($lin) ORDER BY ID ASC LIMIT %d", array_merge( array( (int) $st['cursor'] ), $types, self::LIVE, array( self::CHUNK ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- placeholders are generated above.
			if ( ! $rows ) { $st['phase'] = 'options'; break; }
			$ids   = array_map( 'intval', wp_list_pluck( $rows, 'ID' ) );
			$ph    = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			$meta  = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id IN ($ph) AND meta_key NOT IN ('_edit_lock','_edit_last','_wp_old_slug','_thumbnail_id') AND meta_value <> ''", $ids ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- placeholders are generated above; reads only this chunk's custom fields.
			$by_id = array();
			foreach ( (array) $meta as $m ) { $by_id[ (int) $m->post_id ][] = $m; }
			foreach ( $rows as $r ) {
				$id   = (int) $r->ID;
				$text = $r->post_content . ' ' . $r->post_excerpt;
				$tids = array();
				$mt   = '';
				$mo   = 0;
				foreach ( isset( $by_id[ $id ] ) ? $by_id[ $id ] : array() as $m ) {
					if ( '_menu_item_object_id' === $m->meta_key ) { $mo = (int) $m->meta_value; continue; }
					if ( '_menu_item_type' === $m->meta_key ) { $mt = (string) $m->meta_value; continue; }
					if ( 0 === strpos( $m->meta_key, '_menu_item_' ) ) { continue; }
					$text .= ' ' . self::text( maybe_unserialize( $m->meta_value ) );
				}
				if ( 'nav_menu_item' === $r->post_type && $mo ) {
					if ( 'post_type' === $mt ) { $tids[] = $mo; } elseif ( 'taxonomy' === $mt ) { $tids[] = -$mo; }
				}
				foreach ( array_merge( $tids, self::targets( $text, $st['by'] ) ) as $t ) { self::mark( $st, (int) $t, $id ); }
				$st['cursor'] = $id;
				++$st['seen'];
			}
			unset( $rows, $meta, $by_id );
		}
		if ( 'options' === $st['phase'] && time() <= $deadline + 5 ) {
			$vals = $wpdb->get_col( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR ( option_name LIKE %s AND option_name NOT LIKE %s AND option_name NOT LIKE %s )", $wpdb->esc_like( 'widget_' ) . '%', $wpdb->esc_like( 'theme_mods_' ) . '%', $wpdb->esc_like( 'sidcraft' ) . '%', $wpdb->esc_like( 'sidcraft_rankboost' ) . '%', $wpdb->esc_like( 'sidcraft_vs' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- reads options by name prefix; no WordPress API does this.
			foreach ( (array) $vals as $v ) {
				foreach ( self::targets( self::text( maybe_unserialize( $v ) ), $st['by'] ) as $t ) { self::mark( $st, (int) $t, 0 ); }
			}
			$st['phase'] = 'done';
		}
		if ( 'done' === $st['phase'] ) {
			if ( ! empty( $st['lterms'] ) ) {
				$tids = array_map( 'intval', array_keys( $st['lterms'] ) );
				$ph   = implode( ',', array_fill( 0, count( $tids ), '%d' ) );
				$objs = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT tr.object_id FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id WHERE tt.term_id IN ($ph)", $tids ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- placeholders are generated above.
				foreach ( (array) $objs as $o ) { $st['linked'][ (int) $o ] = 1; }
			}
			$ids = array();
			foreach ( $st['cand'] as $id ) { if ( empty( $st['linked'][ $id ] ) ) { $ids[] = (int) $id; } }
			update_option( self::LIST, array( 'time' => time(), 'ids' => $ids, 'checked' => count( $st['cand'] ) ), false );
			delete_option( self::STATE );
			return false;
		}
		update_option( self::STATE, $st, false );
		return true;
	}

	public static function event() {
		if ( Sidcraft_RankBoost_Audit::busy() ) { wp_schedule_single_event( time() + 10 * MINUTE_IN_SECONDS, self::EVENT ); return; }
		if ( get_transient( self::LOCK ) ) { return; }
		set_transient( self::LOCK, 1, 2 * MINUTE_IN_SECONDS );
		$more = self::batch( 8 );
		delete_transient( self::LOCK );
		if ( $more ) { wp_schedule_single_event( time() + 2 * MINUTE_IN_SECONDS, self::EVENT ); }
	}

	/** Record one link target: a positive number is a post, a negative one a category, tag or other term. */
	private static function mark( array &$st, $target, $source ) {
		if ( $target < 0 ) { $st['lterms'][ -$target ] = 1; } elseif ( $target !== (int) $source ) { $st['linked'][ $target ] = 1; }
	}

	private static function text( $value ) {
		return is_scalar( $value ) ? (string) $value : (string) wp_json_encode( $value );
	}

	/** IDs of candidates that a piece of text links to. */
	public static function targets( $text, array $by_path ) {
		$text  = str_replace( array( '\\/', '&amp;', '&#038;' ), array( '/', '&', '&' ), (string) $text );
		$found = array();
		if ( false === strpos( $text, '/' ) && ! preg_match( '/\d/', $text ) ) { return $found; }
		if ( preg_match_all( '/\b(?:page_id|post_id|object_id|p|id)\b["\']?\s*[:=]\s*["\']?(\d+)/i', $text, $m ) ) {
			foreach ( $m[1] as $n ) { $found[] = (int) $n; }
		}
		if ( preg_match_all( '/[?&]cat=(\d+)/i', $text, $m ) ) {
			foreach ( $m[1] as $n ) { $found[] = -(int) $n; }
		}
		if ( preg_match_all( '#(?:https?:)?//[^\s"\'<>()\[\]{}\\\\]+|(?<=[\s"\'(=,])/[^\s"\'<>()\[\]{}\\\\]*#i', $text, $m ) ) {
			foreach ( $m[0] as $url ) {
				$key = self::path_key( $url );
				if ( '' !== $key && isset( $by_path[ $key ] ) ) { $found[] = (int) $by_path[ $key ]; }
			}
		}
		return array_values( array_unique( $found ) );
	}

	public static function path_key( $url ) {
		$path = wp_parse_url( html_entity_decode( (string) $url ), PHP_URL_PATH );
		return ( is_string( $path ) && '' !== $path ) ? strtolower( untrailingslashit( rawurldecode( $path ) ) ) : '';
	}
}
