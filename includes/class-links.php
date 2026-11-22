<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Broken link finder: scans the links inside pages and posts, lists the ones that do not work, and repairs them. */
class Sidcraft_RankBoost_Links {

	const ITEMS   = 'sidcraft_rankboost_links_items';
	const STATE   = 'sidcraft_rankboost_links_state';
	const IGNORED = 'sidcraft_rankboost_links_ignored';
	const EVENT   = 'sidcraft_rankboost_links_more';

	public static function init() {
		add_action( self::EVENT, array( __CLASS__, 'event' ) );
		add_action( Sidcraft_RankBoost_Audit::CRON, array( __CLASS__, 'daily' ), 20 );
	}

	/* ---------- reading links out of a post ---------- */

	public static function reasons() {
		return array(
			'missing'     => __( 'Page not found (404)', 'sidcraft-rankboost' ),
			'unpublished' => __( 'Points to a page that is not published', 'sidcraft-rankboost' ),
			'redirected'  => __( 'Only works through a redirect', 'sidcraft-rankboost' ),
			'file'        => __( 'File is missing', 'sidcraft-rankboost' ),
			'gone'        => __( 'Page not found (404)', 'sidcraft-rankboost' ),
			'dns'         => __( 'Website could not be found', 'sidcraft-rankboost' ),
		);
	}

	/** Every link target in the post's text and in its page-builder layout. */
	public static function extract( $post ) {
		$src   = $post->post_content . ' ' . (string) get_post_meta( $post->ID, '_sidsyn_document_data', true );
		$cands = array();
		if ( preg_match_all( '#href=\\\\?["\']([^"\'\\\\]+)#i', $src, $m ) ) { $cands = array_merge( $cands, $m[1] ); }
		if ( preg_match_all( '#"(?:url|link|href)"\s*:\s*"([^"]+)"#i', $src, $m2 ) ) { $cands = array_merge( $cands, array_map( 'stripslashes', $m2[1] ) ); }
		$out = array();
		foreach ( $cands as $href ) {
			$href = trim( html_entity_decode( $href ) );
			if ( '' === $href || '#' === $href[0] || preg_match( '#^(?:mailto:|tel:|sms:|javascript:|data:|\{|\[)#i', $href ) ) { continue; }
			if ( preg_match( '#\.(?:jpe?g|png|gif|webp|svg|avif|ico)(\?.*)?$#i', $href ) && false === strpos( $href, '/uploads/' ) ) { continue; }
			if ( 0 === strpos( $href, '//' ) ) { $href = set_url_scheme( $href ); }
			if ( '/' === $href[0] ) { $href = home_url( $href ); }
			if ( ! preg_match( '#^https?://#i', $href ) ) { continue; }
			$out[ $href ] = true;
		}
		return array_keys( $out );
	}

	private static function is_internal( $url ) {
		$home = preg_replace( '#^www\.#', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		$host = preg_replace( '#^www\.#', '', (string) wp_parse_url( $url, PHP_URL_HOST ) );
		return '' !== $host && $host === $home;
	}

	/* ---------- checking one link ---------- */

	private static function has_redirect( $path ) {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE source = %s', Sidcraft_RankBoost_Redirects::table(), $path ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom plugin table.
	}

	/** Returns array( reason, suggestion ) or null when the link works (or cannot be judged). */
	/** Loopback requests made in this PHP request (capped so one scan step cannot flood the server). */
	private static $loopbacks = 0;

	public static function check( $url, $allow_external ) {
		$url = preg_replace( '/#.*$/', '', $url );
		if ( self::is_internal( $url ) ) { return self::check_internal( $url ); }
		return $allow_external ? self::check_external( $url ) : null;
	}

	private static function check_internal( $url ) {
		$path = Sidcraft_RankBoost_Redirects::normalize( $url );
		if ( '/' === $path || '' === $path ) { return null; }
		if ( preg_match( '#^/(?:wp-admin|wp-login|wp-json|wp-includes|feed|comments)#', $path ) ) { return null; }
		$up = wp_get_upload_dir();
		if ( 0 === strpos( $url, $up['baseurl'] ) ) {
			$rel = rawurldecode( (string) wp_parse_url( substr( $url, strlen( $up['baseurl'] ) ), PHP_URL_PATH ) );
			return file_exists( $up['basedir'] . $rel ) ? null : array( 'file', '' );
		}
		if ( preg_match( '#/wp-content/#', $path ) ) { return null; }
		if ( self::has_redirect( $path ) ) { return null; }
		$id = url_to_postid( $url );
		if ( $id ) {
			return 'publish' === get_post_status( $id ) || 'attachment' === get_post_type( $id ) ? null : array( 'unpublished', '' );
		}
		// Not a post or page we know: ask the site itself (covers archives, shop pages, custom routes).
		// Each of these ties up a second PHP worker, so they are cached, capped per run and skipped while the server is busy.
		$lk = 'sidcraft_rankboost_lb_' . md5( $url );
		$hit = get_transient( $lk );
		if ( is_array( $hit ) ) { return $hit ? $hit : null; }
		if ( self::$loopbacks >= 5 || Sidcraft_RankBoost_Audit::busy() ) { return null; }
		++self::$loopbacks;
		$res = wp_remote_head( $url, array( 'timeout' => 3, 'redirection' => 0, 'sslverify' => (bool) apply_filters( 'sidcraft_rankboost_loopback_sslverify', false ), 'headers' => array( 'X-Sidcraft-Linkcheck' => '1' ) ) ); // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout -- short, local request.
		if ( is_wp_error( $res ) ) { return null; }
		$code = (int) wp_remote_retrieve_response_code( $res );
		$out  = array();
		if ( 404 === $code || 410 === $code ) { $out = array( 'missing', Sidcraft_RankBoost_Redirects::guess( $path ) ); }
		elseif ( $code >= 300 && $code < 400 ) {
			$to = (string) wp_remote_retrieve_header( $res, 'location' );
			if ( '' !== $to && Sidcraft_RankBoost_Redirects::normalize( $to ) !== $path && self::is_internal( $to ) ) { $out = array( 'redirected', $to ); }
		}
		set_transient( $lk, $out, 6 * HOUR_IN_SECONDS );
		return $out ? $out : null;
	}

	private static function check_external( $url ) {
		$key    = 'sidcraft_rankboost_lc_' . md5( $url );
		$cached = get_transient( $key );
		if ( false !== $cached ) { return 'ok' === $cached ? null : array( $cached, '' ); }
		$args = array( 'timeout' => 8, 'redirection' => 4, 'user-agent' => 'Mozilla/5.0 (compatible; SidcraftRankBoost link checker)' );
		$res  = wp_safe_remote_head( $url, $args );
		$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
		if ( in_array( $code, array( 403, 405, 501 ), true ) || ( ! is_wp_error( $res ) && $code >= 400 && 404 !== $code && 410 !== $code ) ) {
			$res  = wp_safe_remote_get( $url, array_merge( $args, array( 'limit_response_size' => 4096 ) ) );
			$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
		}
		$result = 'ok';
		if ( 404 === $code || 410 === $code ) { $result = 'gone'; }
		elseif ( is_wp_error( $res ) && false !== stripos( $res->get_error_message(), 'resolve' ) ) { $result = 'dns'; }
		set_transient( $key, $result, 6 * HOUR_IN_SECONDS );
		return 'ok' === $result ? null : array( $result, '' );
	}

	/* ---------- the stored list ---------- */

	public static function items() {
		$i = get_option( self::ITEMS );
		return is_array( $i ) ? $i : array();
	}

	/** The list is kept small: a huge serialized option is read and rewritten on every step. */
	private static function save_items( $items ) { update_option( self::ITEMS, array_slice( $items, 0, 1500, true ), false ); }

	public static function item_id( $post_id, $url ) { return md5( (int) $post_id . '|' . $url ); }

	public static function ignored() {
		$i = get_option( self::IGNORED );
		return is_array( $i ) ? $i : array();
	}

	/** Visible items (ignored ones left out). */
	public static function visible() {
		$ign = self::ignored();
		return array_filter( self::items(), function ( $it ) use ( $ign ) { return ! in_array( md5( $it['url'] ), $ign, true ); } );
	}

	public static function count() { return count( self::visible() ); }

	public static function state() {
		$s = get_option( self::STATE );
		return wp_parse_args( is_array( $s ) ? $s : array(), array( 'cursor' => 0, 'done' => false, 'started' => 0, 'finished' => 0, 'posts' => 0, 'links' => 0 ) );
	}

	/* ---------- scanning ---------- */

	public static function start() {
		self::save_items( array() );
		update_option( self::STATE, array( 'cursor' => 0, 'done' => false, 'started' => time(), 'finished' => 0, 'posts' => 0, 'links' => 0 ), false );
	}

	/** Scan one post and replace its entries in the list. Returns the number of links looked at. */
	public static function scan_post( $post, &$items, $allow_external, $deadline = 0, &$complete = true ) {
		$complete = true;
		foreach ( $items as $k => $it ) { if ( (int) $it['post_id'] === (int) $post->ID ) { unset( $items[ $k ] ); } }
		$n = 0;
		foreach ( self::extract( $post ) as $url ) {
			if ( $deadline && time() > $deadline ) { $complete = false; break; }
			++$n;
			$r = self::check( $url, $allow_external );
			if ( $r ) {
				$items[ self::item_id( $post->ID, $url ) ] = array( 'post_id' => (int) $post->ID, 'url' => $url, 'reason' => $r[0], 'suggest' => $r[1], 'internal' => self::is_internal( $url ) ? 1 : 0 );
			}
		}
		return $n;
	}

	/** Scan the next few posts. Returns true while there is more to do. */
	public static function batch( $seconds = 20 ) {
		global $wpdb;
		$st = self::state();
		if ( $st['done'] ) { return false; }
		$deadline = time() + $seconds;
		$items    = self::items();
		$ext      = Sidcraft_RankBoost_Settings::on( 'check_external' );
		$types    = Sidcraft_RankBoost_Util::post_types();
		$in       = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		$more     = true;
		$scope    = Sidcraft_RankBoost_Audit::scope_ids();
		while ( time() < $deadline ) {
			$only = null === $scope ? '' : ( $scope ? ' AND ID IN (' . implode( ',', array_map( 'intval', $scope ) ) . ')' : ' AND 1 = 0' );
			$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE ID > %d AND post_status = 'publish' AND post_type IN ($in){$only} ORDER BY ID ASC LIMIT 10", array_merge( array( (int) $st['cursor'] ), $types ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- one %s per post type, built above.
			if ( ! $ids ) { $st['done'] = true; $st['finished'] = time(); $more = false; break; }
			foreach ( $ids as $id ) {
				$post = get_post( (int) $id );
				$complete = true;
				if ( $post ) {
					$st['links'] += self::scan_post( $post, $items, $ext, $deadline + 4, $complete );
					if ( ! $complete ) { break 2; } // out of time mid-page: pick this page up again next round.
					++$st['posts'];
				}
				$st['cursor'] = (int) $id;
				if ( time() >= $deadline ) { break; }
			}
		}
		self::save_items( $items );
		update_option( self::STATE, $st, false );
		return $more;
	}

	public static function event() {
		if ( Sidcraft_RankBoost_Audit::busy() ) { wp_schedule_single_event( time() + 10 * MINUTE_IN_SECONDS, self::EVENT ); return; }
		// Never two scan steps at once (WP-Cron can start a second run while the first is still going).
		if ( get_transient( 'sidcraft_rankboost_links_lock' ) ) { return; }
		set_transient( 'sidcraft_rankboost_links_lock', 1, 2 * MINUTE_IN_SECONDS );
		$more = self::batch( 5 );
		delete_transient( 'sidcraft_rankboost_links_lock' );
		if ( $more ) { wp_schedule_single_event( time() + 5 * MINUTE_IN_SECONDS, self::EVENT ); }
	}

	public static function daily() {
		if ( ! Sidcraft_RankBoost_Settings::on( 'daily_audit' ) ) { return; }
		self::start();
		wp_schedule_single_event( time() + 60, self::EVENT );
	}

	/* ---------- repairing ---------- */

	private static function variants( $url ) {
		return array_unique( array( $url, str_replace( '&', '&amp;', $url ), str_replace( '/', '\\/', $url ) ) );
	}

	/** Swap one link address for another in the post's text and layout. Returns how many places changed. */
	public static function replace( $post_id, $old, $new ) {
		$post = get_post( $post_id );
		if ( ! $post ) { return 0; }
		$count = 0;
		$text  = $post->post_content;
		foreach ( self::variants( $old ) as $v ) {
			$nv = ( false !== strpos( $v, '\\/' ) ) ? str_replace( '/', '\\/', $new ) : ( false !== strpos( $v, '&amp;' ) ? str_replace( '&', '&amp;', $new ) : $new );
			foreach ( array( '"', "'" ) as $q ) { $text = str_replace( $q . $v . $q, $q . $nv . $q, $text, $c ); $count += $c; }
		}
		if ( $text !== $post->post_content ) { wp_update_post( array( 'ID' => $post_id, 'post_content' => wp_slash( $text ) ) ); }
		$doc = get_post_meta( $post_id, '_sidsyn_document_data', true );
		if ( is_string( $doc ) && '' !== $doc ) {
			$d2 = $doc;
			foreach ( self::variants( $old ) as $v ) {
				$nv  = ( false !== strpos( $v, '\\/' ) ) ? str_replace( '/', '\\/', $new ) : $new;
				$d2  = str_replace( '"' . $v . '"', '"' . $nv . '"', $d2, $c ); $count += $c;
			}
			if ( $d2 !== $doc ) { update_post_meta( $post_id, '_sidsyn_document_data', wp_slash( $d2 ) ); }
		}
		return $count;
	}

	/** Remove the link but keep its words. */
	public static function unlink( $post_id, $old ) {
		$post = get_post( $post_id );
		if ( ! $post ) { return 0; }
		$text  = $post->post_content;
		$count = 0;
		foreach ( self::variants( $old ) as $v ) {
			$text = preg_replace( '#<a\b[^>]*\bhref=(["\'])' . preg_quote( $v, '#' ) . '\1[^>]*>(.*?)</a>#is', '$2', $text, -1, $c );
			$count += (int) $c;
		}
		if ( $text !== $post->post_content ) { wp_update_post( array( 'ID' => $post_id, 'post_content' => wp_slash( $text ) ) ); }
		return $count + self::replace( $post_id, $old, '' );
	}

	/** Forget this post's entries and look at its links again. */
	public static function rescan( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) { return; }
		$items = self::items();
		self::scan_post( $post, $items, Sidcraft_RankBoost_Settings::on( 'check_external' ), time() + 20 );
		self::save_items( $items );
	}

	/** Apply one repair to one list entry. Returns a short message. */
	public static function repair( $id, $how, $new = '' ) {
		$items = self::items();
		if ( ! isset( $items[ $id ] ) ) { return __( 'That link is no longer in the list.', 'sidcraft-rankboost' ); }
		$it = $items[ $id ];
		if ( 'ignore' === $how ) {
			$ign   = self::ignored();
			$ign[] = md5( $it['url'] );
			update_option( self::IGNORED, array_values( array_unique( $ign ) ), false );
			return __( 'Link ignored. It will not be listed again.', 'sidcraft-rankboost' );
		}
		if ( ! current_user_can( 'edit_post', $it['post_id'] ) ) { return __( 'You are not allowed to edit that page.', 'sidcraft-rankboost' ); }
		$new = esc_url_raw( trim( $new ) );
		if ( 'unlink' === $how ) {
			self::unlink( $it['post_id'], $it['url'] );
			unset( $items[ $id ] ); self::save_items( $items ); self::rescan( $it['post_id'] );
			return __( 'Link removed. The words stay on the page.', 'sidcraft-rankboost' );
		}
		if ( '' === $new ) { return __( 'Type or choose a new address first.', 'sidcraft-rankboost' ); }
		if ( 'redirect' === $how ) {
			if ( ! $it['internal'] ) { return __( 'Redirects only work for addresses on your own site.', 'sidcraft-rankboost' ); }
			if ( ! Sidcraft_RankBoost_Redirects::add( $it['url'], $new ) ) { return __( 'Could not save that redirect: check the address.', 'sidcraft-rankboost' ); }
			foreach ( $items as $k => $o ) { if ( $o['url'] === $it['url'] ) { unset( $items[ $k ] ); } }
			self::save_items( $items );
			return __( 'Redirect saved. Everyone who follows the old address now arrives at the new one.', 'sidcraft-rankboost' );
		}
		$n = self::replace( $it['post_id'], $it['url'], $new );
		unset( $items[ $id ] ); self::save_items( $items ); self::rescan( $it['post_id'] );
		/* translators: %d: number of places changed. */
		return $n ? sprintf( _n( 'Link updated (%d place).', 'Link updated (%d places).', $n, 'sidcraft-rankboost' ), $n ) : __( 'Nothing to change: the link was not found in the page text.', 'sidcraft-rankboost' );
	}

	/** Apply the suggested address to every entry that has one. */
	public static function fix_all() {
		$n = 0;
		foreach ( self::visible() as $id => $it ) {
			if ( '' !== $it['suggest'] && current_user_can( 'edit_post', $it['post_id'] ) ) {
				self::repair( $id, 'replace', $it['suggest'] );
				++$n;
			}
		}
		/* translators: %d: number of links. */
		return sprintf( _n( '%d link repaired using the closest matching page.', '%d links repaired using the closest matching page.', $n, 'sidcraft-rankboost' ), $n );
	}
}
