<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Scores every page and post (0-100) on practical SEO checks, on save and once a day, and repairs what can be repaired. */
class Sidcraft_AutoSEO_Audit {

	const CRON = 'sidcraft_autoseo_daily_audit';

	public static function schedule() {
		if ( ! wp_next_scheduled( self::CRON ) ) { wp_schedule_event( time() + 300, 'daily', self::CRON ); }
	}

	public static function unschedule() { wp_clear_scheduled_hook( self::CRON ); wp_clear_scheduled_hook( 'sidcraft_autoseo_audit_more' ); }

	public static function init() {
		add_action( self::CRON, array( __CLASS__, 'daily' ) );
		add_action( 'sidcraft_autoseo_audit_more', array( __CLASS__, 'batch_event' ) );
		add_action( 'wp_ajax_sidcraft_autoseo_audit', array( __CLASS__, 'ajax_step' ) );
		add_action( 'save_post', array( __CLASS__, 'on_save' ), 30, 2 );
		add_action( 'init', array( __CLASS__, 'schedule' ) );
		add_action( 'save_post', function () { delete_transient( 'sidcraft_autoseo_slugs' ); } );
	}

	/* ---------- the checks ---------- */

	/**
	 * @return array{score:int,issues:array<int,array{id:string,sev:string,msg:string}>,focus:string}
	 */
	public static function analyze( $post ) {
		$post   = get_post( $post );
		$text   = Sidcraft_AutoSEO_Util::post_text( $post );
		$words  = Sidcraft_AutoSEO_Util::word_count( $text );
		$title  = Sidcraft_AutoSEO_Meta::title_for_post( $post );
		$desc   = Sidcraft_AutoSEO_Meta::description_for_post( $post );
		$focus  = Sidcraft_AutoSEO_Meta::focus_for_post( $post->ID );
		$score  = 0;
		$issues = array();
		$add    = function ( $id, $sev, $msg ) use ( &$issues ) { $issues[] = array( 'id' => $id, 'sev' => $sev, 'msg' => $msg ); };

		// 1. Title (10)
		$tl = Sidcraft_AutoSEO_Util::len( $title );
		if ( $tl >= 25 && $tl <= 60 ) { $score += 10; }
		elseif ( $tl > 0 ) { $score += 5; $add( 'title', 'low', $tl < 25 ? __( 'Title is short (under 25 characters).', 'sidcraft-autoseo' ) : __( 'Title is long and will be cut off in search results.', 'sidcraft-autoseo' ) ); }
		else { $add( 'title', 'high', __( 'No title.', 'sidcraft-autoseo' ) ); }

		// 2. Description (10)
		$dl = Sidcraft_AutoSEO_Util::len( $desc );
		if ( $dl >= 70 && $dl <= 160 ) { $score += 10; }
		elseif ( $dl >= 40 ) { $score += 6; $add( 'desc', 'low', __( 'Description is short; the page has little text to summarise.', 'sidcraft-autoseo' ) ); }
		else { $add( 'desc', 'medium', __( 'Not enough text to write a useful description.', 'sidcraft-autoseo' ) ); }

		// 3. Content length (15)
		if ( $words >= 300 ) { $score += 15; }
		elseif ( $words >= 150 ) { $score += 9; $add( 'thin', 'low', sprintf( /* translators: %d: number of words on the page. */ __( 'Short page (%d words). 300+ words ranks more easily.', 'sidcraft-autoseo' ), $words ) ); }
		else { $add( 'thin', 'medium', sprintf( /* translators: %d: number of words on the page. */ __( 'Thin content (%d words).', 'sidcraft-autoseo' ), $words ) ); }

		// 4. Headings (10)
		$heads = Sidcraft_AutoSEO_Util::headings( $post );
		$h1    = count( array_filter( $heads, function ( $h ) { return 1 === $h[0]; } ) );
		$h2    = count( array_filter( $heads, function ( $h ) { return 2 === $h[0]; } ) );
		if ( $h1 > 1 ) { $add( 'h1', 'medium', __( 'More than one H1 heading in the content.', 'sidcraft-autoseo' ) ); $score += 4; }
		elseif ( $words > 600 && $h2 < 2 ) { $add( 'h2', 'low', __( 'Long page without section headings (H2).', 'sidcraft-autoseo' ) ); $score += 6; }
		else { $score += 10; }

		// 5. Image alt text (10) - automatic alt text counts as handled.
		$imgs = Sidcraft_AutoSEO_Util::post_images( $post );
		if ( ! $imgs ) { $score += 5; $add( 'noimg', 'low', __( 'No images on the page.', 'sidcraft-autoseo' ) ); }
		else { $score += 10; }

		// 6. Internal links (10)
		$home  = wp_parse_url( home_url(), PHP_URL_HOST );
		$src   = $post->post_content . ' ' . (string) get_post_meta( $post->ID, '_sidsyn_document_data', true );
		$links = 0;
		$cands = array();
		if ( preg_match_all( '#href=\\\\?["\']([^"\'\\\\]+)#i', $src, $m ) ) { $cands = array_merge( $cands, $m[1] ); }
		// Page-builder data keeps links in "url"/"link" fields instead of href attributes.
		if ( preg_match_all( '#"(?:url|link|href)"\s*:\s*"([^"]+)"#i', $src, $m2 ) ) { $cands = array_merge( $cands, array_map( 'stripslashes', $m2[1] ) ); }
		foreach ( $cands as $href ) {
			$href = trim( $href );
			if ( '' === $href || '#' === $href[0] || 0 === strpos( $href, 'mailto:' ) || 0 === strpos( $href, 'tel:' ) || preg_match( '#\.(?:jpe?g|png|gif|webp|svg|avif)(\?.*)?$#i', $href ) ) { continue; }
			$h = wp_parse_url( $href, PHP_URL_HOST );
			if ( ! $h || $h === $home || 'www.' . $home === $h || $h === 'www.' . $home ) { $links++; }
		}
		if ( $links >= 1 || ( 'post' === $post->post_type && Sidcraft_AutoSEO_Settings::on( 'related_links' ) ) || ( 'post' !== $post->post_type && Sidcraft_AutoSEO_Settings::on( 'related_pages' ) ) ) { $score += 10; } else { $add( 'links', 'low', __( 'No links to other pages on this site.', 'sidcraft-autoseo' ) ); }

		// 7. Focus keyword (10)
		if ( '' !== $focus ) {
			$q        = function ( $t ) { return str_replace( array( '’', '‘', '“', '”' ), array( "'", "'", '"', '"' ), (string) $t ); };
			$in_title = false !== Sidcraft_AutoSEO_Util::ipos( $q( Sidcraft_AutoSEO_Util::clean( get_the_title( $post ) ) ), $q( $focus ) );
			$first    = Sidcraft_AutoSEO_Util::sub( Sidcraft_AutoSEO_Util::lower( $q( $text ) ), 0, 600 );
			$in_text  = false !== Sidcraft_AutoSEO_Util::pos( $first, $q( $focus ) );
			$score   += ( $in_title ? 5 : 0 ) + ( $in_text ? 5 : 0 );
			if ( ! $in_title ) { $add( 'kw_title', 'low', sprintf( /* translators: %s: main phrase of the page. */ __( 'Main phrase “%s” is not in the title.', 'sidcraft-autoseo' ), $focus ) ); }
			if ( ! $in_text && $words >= 100 ) { $add( 'kw_intro', 'low', sprintf( /* translators: %s: main phrase of the page. */ __( 'Main phrase “%s” is not in the first paragraph.', 'sidcraft-autoseo' ), $focus ) ); }
		} else { $score += 5; }

		// 8. Slug (5)
		$slug = (string) $post->post_name;
		if ( strlen( $slug ) <= 75 && ! preg_match( '/[A-Z_]/', $slug ) ) { $score += 5; }
		else { $add( 'slug', 'low', __( 'Web address (slug) is very long or has capitals/underscores.', 'sidcraft-autoseo' ) ); }

		// 9. Social image (5)
		if ( has_post_thumbnail( $post ) || $imgs ) { $score += 5; } else { $add( 'social', 'low', __( 'No image: social shares will show a plain card.', 'sidcraft-autoseo' ) ); }

		// 10. Readability (5)
		$avg = Sidcraft_AutoSEO_Util::avg_sentence_words( $text );
		if ( $words < 80 || $avg <= 25 ) { $score += 5; } else { $add( 'read', 'low', sprintf( /* translators: %d: average words per sentence. */ __( 'Sentences average %d words; shorter is easier to read.', 'sidcraft-autoseo' ), round( $avg ) ) ); $score += 2; }

		// 11. Indexable (5)
		if ( Sidcraft_AutoSEO_Meta::override( $post->ID, 'noindex' ) ) { $add( 'noindex', 'high', __( 'Hidden from search engines (noindex).', 'sidcraft-autoseo' ) ); } else { $score += 5; }

		// 12. Inbound links (5)
		$inbound = self::inbound( $post );
		if ( $inbound > 0 || is_front_page() ) { $score += 5; } else { $add( 'orphan', 'medium', __( 'No other page or menu links here (orphan page).', 'sidcraft-autoseo' ) ); }

		return array( 'score' => max( 0, min( 100, $score ) ), 'issues' => $issues, 'focus' => $focus );
	}

	/** How many menu items and other published pages link to this post. Capped for speed. */
	public static function inbound( $post ) {
		global $wpdb;
		$path = rtrim( (string) wp_parse_url( get_permalink( $post ), PHP_URL_PATH ), '/' );
		if ( '' === $path ) { return 1; }
		$like = '%' . $wpdb->esc_like( $path ) . '%';
		$n = (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- read-only count; result is cached by the daily audit.
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish' AND ID <> %d AND post_type NOT IN ('revision','attachment','nav_menu_item') AND post_content LIKE %s LIMIT 1",
			$post->ID, $like
		) );
		if ( $n ) { return $n; }
		$n = (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- read-only count; result is cached by the daily audit.
			"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id <> %d AND meta_key IN ('_sidsyn_document_data','_menu_item_url') AND meta_value LIKE %s",
			$post->ID, $like
		) );
		if ( $n ) { return $n; }
		// Links listed on a related page by the one-click fix.
		$n = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value LIKE %s", Sidcraft_AutoSEO_Fixer::EXTRA, '%,' . (int) $post->ID . ',%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- read-only count.
		if ( $n ) { return $n; }
		// Menu items that point at the post itself.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_menu_item_object_id' AND meta_value = %s", (string) $post->ID ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- read-only count.
	}

	/* ---------- running ---------- */

	public static function store( $post ) {
		$post = get_post( $post );
		$r    = self::analyze( $post );
		update_post_meta( $post->ID, '_sidcraft_autoseo_score', $r['score'] );
		update_post_meta( $post->ID, '_sidcraft_autoseo_issues', wp_slash( wp_json_encode( $r['issues'] ) ) );
		update_post_meta( $post->ID, '_sidcraft_autoseo_checked', time() );
		if ( '' === Sidcraft_AutoSEO_Meta::override( $post->ID, 'focus' ) && '' !== $r['focus'] ) { update_post_meta( $post->ID, '_sidcraft_autoseo_focus_auto', $r['focus'] ); }
		return $r;
	}

	public static function on_save( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || 'publish' !== $post->post_status ) { return; }
		if ( ! in_array( $post->post_type, Sidcraft_AutoSEO_Util::post_types(), true ) ) { return; }
		self::store( $post );
	}

	/** Audit up to $limit posts that have not been checked yet or changed since. Returns how many are still waiting. */
	public static function batch( $limit = 40, $budget = 20 ) {
		$start = microtime( true );
		$ids = get_posts( array(
			'post_type' => Sidcraft_AutoSEO_Util::post_types(), 'post_status' => 'publish', 'posts_per_page' => (int) $limit, 'fields' => 'ids', 'no_found_rows' => true,
			'meta_query' => // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- needed to find unaudited posts.
			 array( 'relation' => 'OR', array( 'key' => '_sidcraft_autoseo_checked', 'compare' => 'NOT EXISTS' ), array( 'key' => '_sidcraft_autoseo_checked', 'value' => time() - DAY_IN_SECONDS, 'compare' => '<', 'type' => 'NUMERIC' ) ),
		) );
		foreach ( $ids as $id ) {
			self::store( $id );
			if ( ( microtime( true ) - $start ) > $budget ) { break; }
		}
		return self::pending();
	}

	/** Forget every audit mark so all pages are checked again. */
	public static function reset_marks() {
		global $wpdb;
		$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_sidcraft_autoseo_checked' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- re-check everything, not only what is older than a day.
	}

	/** Published pages and posts that are audited. */
	public static function total() {
		$n = 0;
		foreach ( Sidcraft_AutoSEO_Util::post_types() as $pt ) { $c = wp_count_posts( $pt ); $n += isset( $c->publish ) ? (int) $c->publish : 0; }
		return $n;
	}

	/**
	 * One small step of a full audit, called again and again from the admin screen so no single request runs long
	 * (long requests hit the web server's time limit on slower hosting).
	 */
	public static function ajax_step() {
		check_ajax_referer( 'sidcraft_autoseo_audit' );
		if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( 'forbidden', 403 ); }
		if ( ! empty( $_POST['reset'] ) ) { self::reset_marks(); delete_transient( 'sidcraft_autoseo_resume' ); }
		$left = self::batch( 4, 6 );
		if ( $left <= 0 ) {
			self::summary( true );
			update_option( 'sidcraft_autoseo_last_run', time(), false );
			delete_transient( 'sidcraft_autoseo_resume' );
		}
		wp_send_json_success( array( 'left' => $left, 'total' => self::total(), 'done' => $left <= 0 ) );
	}

	public static function pending() {
		$q = new WP_Query( array(
			'post_type' => Sidcraft_AutoSEO_Util::post_types(), 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids',
			'meta_query' => // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- needed to find unaudited posts.
			 array( 'relation' => 'OR', array( 'key' => '_sidcraft_autoseo_checked', 'compare' => 'NOT EXISTS' ), array( 'key' => '_sidcraft_autoseo_checked', 'value' => time() - DAY_IN_SECONDS, 'compare' => '<', 'type' => 'NUMERIC' ) ),
		) );
		return (int) $q->found_posts;
	}

	public static function daily() {
		if ( ! Sidcraft_AutoSEO_Settings::on( 'daily_audit' ) ) { return; }
		if ( Sidcraft_AutoSEO_Settings::on( 'auto_alt' ) ) { Sidcraft_AutoSEO_Images::fix_library( 200 ); }
		Sidcraft_AutoSEO_Redirects::prune();
		delete_transient( 'sidcraft_autoseo_slugs' );
		$left = self::batch( 40 );
		self::summary( true );
		if ( $left > 0 ) { wp_schedule_single_event( time() + 60, 'sidcraft_autoseo_audit_more' ); }
		update_option( 'sidcraft_autoseo_last_run', time(), false );
	}

	public static function batch_event() {
		$left = self::batch( 40 );
		self::summary( true );
		if ( $left > 0 ) { wp_schedule_single_event( time() + 60, 'sidcraft_autoseo_audit_more' ); }
	}

	/** Site-wide numbers for the dashboard. */
	public static function summary( $refresh = false ) {
		$s = get_transient( 'sidcraft_autoseo_summary' );
		if ( ! $refresh && is_array( $s ) ) { return $s; }
		global $wpdb;
		$types = Sidcraft_AutoSEO_Util::post_types();
		$in    = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		$rows  = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- one %s per post type; summary is cached in a transient.
			"SELECT p.ID, CAST(s.meta_value AS UNSIGNED) AS score, i.meta_value AS issues FROM {$wpdb->posts} p
			 JOIN {$wpdb->postmeta} s ON s.post_id = p.ID AND s.meta_key = '_sidcraft_autoseo_score'
			 LEFT JOIN {$wpdb->postmeta} i ON i.post_id = p.ID AND i.meta_key = '_sidcraft_autoseo_issues'
			 WHERE p.post_status = 'publish' AND p.post_type IN ($in)", $types ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery -- one %s per post type, built above.
		$total = count( $rows ); $sum = 0; $good = 0; $ok = 0; $bad = 0; $by = array();
		foreach ( $rows as $r ) {
			$sc = (int) $r['score']; $sum += $sc;
			if ( $sc >= 80 ) { $good++; } elseif ( $sc >= 60 ) { $ok++; } else { $bad++; }
			$list = json_decode( (string) $r['issues'], true );
			foreach ( (array) $list as $is ) { $by[ $is['id'] ] = array( 'msg' => $is['msg'], 'n' => ( isset( $by[ $is['id'] ] ) ? $by[ $is['id'] ]['n'] : 0 ) + 1, 'sev' => $is['sev'] ); }
		}
		uasort( $by, function ( $a, $b ) { return $b['n'] <=> $a['n']; } );
		$s = array( 'total' => $total, 'avg' => $total ? (int) round( $sum / $total ) : 0, 'good' => $good, 'ok' => $ok, 'bad' => $bad, 'top' => array_slice( $by, 0, 8, true ), 'time' => time() );
		set_transient( 'sidcraft_autoseo_summary', $s, 12 * HOUR_IN_SECONDS );
		return $s;
	}
}
