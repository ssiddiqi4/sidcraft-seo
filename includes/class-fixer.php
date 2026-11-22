<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** One-click repairs for audit findings that can be fixed safely without a person writing anything. */
class Sidcraft_RankBoost_Fixer {

	const EXTRA = '_sidcraft_rankboost_extra_links';

	/** Issues that can be fixed automatically, with the wording shown on the button. */
	public static function catalog() {
		return array(
			'orphan' => array( 'label' => __( 'Link to these pages', 'sidcraft-rankboost' ), 'confirm' => '', 'hint' => __( 'Adds each page to the “You may also like” list of a related page, so it gets a link from somewhere.', 'sidcraft-rankboost' ) ),
			'links'  => array( 'label' => __( 'Add related-page lists', 'sidcraft-rankboost' ), 'confirm' => '', 'hint' => __( 'Turns on the “You may also like” list for pages that link nowhere.', 'sidcraft-rankboost' ) ),
			'slug'   => array( 'label' => __( 'Shorten web addresses', 'sidcraft-rankboost' ), 'confirm' => __( 'Shorten these web addresses? The old addresses will keep working through automatic redirects.', 'sidcraft-rankboost' ), 'hint' => __( 'Shortens very long web addresses. The old address redirects to the new one.', 'sidcraft-rankboost' ) ),
			'excerpt' => array( 'label' => __( 'Write missing excerpts', 'sidcraft-rankboost' ), 'confirm' => '', 'hint' => __( 'Writes a short excerpt for pages and posts that have none. Excerpts you wrote are never changed.', 'sidcraft-rankboost' ) ),
		);
	}

	/** Advice for issues that need a person. */
	public static function advice( $id ) {
		$a = array(
			'thin'     => __( 'Needs a person: add more useful text (300+ words).', 'sidcraft-rankboost' ),
			'desc'     => __( 'Needs a person: add a few sentences of text, or write an excerpt.', 'sidcraft-rankboost' ),
			'read'     => __( 'Needs a person: split long sentences.', 'sidcraft-rankboost' ),
			'h2'       => __( 'Needs a person: add section headings (H2).', 'sidcraft-rankboost' ),
			'title'    => __( 'Needs a person: make the title more descriptive.', 'sidcraft-rankboost' ),
			'kw_title' => __( 'Needs a person: use the main phrase in the title.', 'sidcraft-rankboost' ),
			'kw_intro' => __( 'Needs a person: use the main phrase in the first paragraph.', 'sidcraft-rankboost' ),
			'noimg'    => __( 'Needs a person: add an image.', 'sidcraft-rankboost' ),
			'social'   => __( 'Needs a person: add a featured image.', 'sidcraft-rankboost' ),
			'h1'       => __( 'Needs a person: keep one main heading (H1).', 'sidcraft-rankboost' ),
			'noindex'  => __( 'Your choice: this page is hidden on purpose.', 'sidcraft-rankboost' ),
		);
		return isset( $a[ $id ] ) ? $a[ $id ] : '';
	}

	/** Posts whose last audit reported a given issue (or any issue when $id is ''). */
	public static function posts_with_issue( $id, $limit = 500 ) {
		$args = array(
			'post_type' => Sidcraft_RankBoost_Util::post_types(), 'post_status' => 'publish', 'posts_per_page' => (int) $limit, 'fields' => 'ids', 'no_found_rows' => true,
		);
		if ( '' !== $id ) {
			$args['meta_query'] = array( array( 'key' => '_sidcraft_rankboost_issues', 'value' => '"id":"' . $id . '"', 'compare' => 'LIKE' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- finds pages with a given audit finding.
		}
		return get_posts( $args );
	}

	/* ---------- the fixes ---------- */

	/** Give every orphan page one inbound link by listing it on a related page. */
	public static function orphans() {
		$fixed = 0;
		foreach ( self::posts_with_issue( 'orphan' ) as $id ) {
			$post = get_post( $id );
			if ( ! $post ) { continue; }
			$host = self::pick_host( $post );
			if ( ! $host ) { continue; }
			$extra = self::extra_ids( $host->ID );
			if ( ! in_array( (int) $post->ID, $extra, true ) ) {
				$extra[] = (int) $post->ID;
				update_post_meta( $host->ID, self::EXTRA, ',' . implode( ',', $extra ) . ',' );
				$fixed++;
			}
			Sidcraft_RankBoost_Audit::store( $post );
			Sidcraft_RankBoost_Audit::store( $host );
		}
		return $fixed;
	}

	public static function extra_ids( $post_id ) {
		$raw = (string) get_post_meta( $post_id, self::EXTRA, true );
		return array_values( array_filter( array_map( 'intval', explode( ',', $raw ) ) ) );
	}

	private static function pick_host( $post ) {
		$base = array( 'post_type' => $post->post_type, 'post_status' => 'publish', 'posts_per_page' => 40, 'no_found_rows' => true, 'orderby' => 'date', 'order' => 'DESC', 'ignore_sticky_posts' => true );
		$cands = array();
		$tags  = 'post' === $post->post_type ? wp_get_post_tags( $post->ID, array( 'fields' => 'ids' ) ) : array();
		$cats  = 'post' === $post->post_type ? wp_get_post_categories( $post->ID ) : array();
		if ( $tags ) { $cands = array_merge( $cands, get_posts( array_merge( $base, array( 'tag__in' => $tags ) ) ) ); }
		if ( $cats ) { $cands = array_merge( $cands, get_posts( array_merge( $base, array( 'category__in' => $cats ) ) ) ); }
		$cands = array_merge( $cands, get_posts( $base ) );
		foreach ( $cands as $c ) {
			if ( (int) $c->ID === (int) $post->ID || Sidcraft_RankBoost_Related::is_hidden( $c ) ) { continue; }
			if ( count( self::extra_ids( $c->ID ) ) >= 6 ) { continue; }
			return $c;
		}
		return null;
	}

	/** Turn on the related-page list for pages that link nowhere. */
	public static function links() {
		$s = Sidcraft_RankBoost_Settings::all();
		$changed = empty( $s['related_pages'] ) || empty( $s['related_links'] );
		$s['related_pages'] = 1;
		$s['related_links'] = 1;
		update_option( Sidcraft_RankBoost_Settings::OPTION, $s, false );
		return $changed ? count( self::posts_with_issue( 'links' ) ) : 0;
	}

	/** Write an excerpt where there is none. */
	public static function excerpts() {
		$n = 0;
		$ids = get_posts( array( 'post_type' => Sidcraft_RankBoost_Util::post_types(), 'post_status' => 'publish', 'posts_per_page' => 300, 'fields' => 'ids', 'no_found_rows' => true ) );
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( '' !== trim( $post->post_excerpt ) ) { continue; }
			$text = Sidcraft_RankBoost_Util::lead_text( $post );
			if ( Sidcraft_RankBoost_Util::len( $text ) < 60 ) { continue; }
			wp_update_post( wp_slash( array( 'ID' => $id, 'post_excerpt' => Sidcraft_RankBoost_Util::truncate( $text, 200 ) ) ) );
			$n++;
		}
		return $n;
	}

	/** Shorten very long or untidy slugs. wp_update_post triggers the automatic 301 from the old address. */
	public static function slugs() {
		$n = 0;
		foreach ( self::posts_with_issue( 'slug' ) as $id ) {
			$post = get_post( $id );
			$new  = self::short_slug( $post->post_name );
			if ( '' === $new || $new === $post->post_name ) { continue; }
			$new = wp_unique_post_slug( $new, $post->ID, $post->post_status, $post->post_type, $post->post_parent );
			wp_update_post( array( 'ID' => $post->ID, 'post_name' => $new ) );
			$n++;
		}
		return $n;
	}

	public static function short_slug( $slug ) {
		$slug = sanitize_title( str_replace( '_', '-', strtolower( (string) $slug ) ) );
		if ( strlen( $slug ) <= 60 ) { return $slug; }
		$parts = explode( '-', $slug ); $out = '';
		$stop  = Sidcraft_RankBoost_Util::stopwords();
		foreach ( $parts as $p ) {
			if ( '' === $p || in_array( $p, $stop, true ) ) { continue; }
			if ( strlen( $out . '-' . $p ) > 60 ) { break; }
			$out .= ( '' === $out ? '' : '-' ) . $p;
		}
		return '' !== $out ? $out : substr( $slug, 0, 60 );
	}

	/** Run one fix by name. Returns array( count, message ). */
	public static function run( $what ) {
		switch ( $what ) {
			case 'orphan':
				$n = self::orphans();
				/* translators: %d: number of pages. */
				return array( $n, sprintf( _n( '%d page now has a link from a related page.', '%d pages now have a link from a related page.', $n, 'sidcraft-rankboost' ), $n ) );
			case 'links':
				self::links();
				return array( 1, __( 'The “You may also like” list is now on for pages that link nowhere.', 'sidcraft-rankboost' ) );
			case 'slug':
				$n = self::slugs();
				/* translators: %d: number of pages. */
				return array( $n, sprintf( _n( '%d web address shortened (the old one redirects).', '%d web addresses shortened (the old ones redirect).', $n, 'sidcraft-rankboost' ), $n ) );
			case 'excerpt':
				$n = self::excerpts();
				/* translators: %d: number of pages. */
				return array( $n, sprintf( _n( 'Excerpt written for %d page.', 'Excerpts written for %d pages.', $n, 'sidcraft-rankboost' ), $n ) );
		}
		return array( 0, '' );
	}

	/* ---------- fixing in small steps (so no single request runs long) ---------- */

	const JOB = 'sidcraft_rankboost_fixjob';

	/** Plan a fix. It then runs a few seconds at a time from the admin screen (job_step). */
	public static function job_start( $what ) {
		$plans = array( 'all' => array( 'links', 'excerpts', 'images', 'orphans' ), 'orphan' => array( 'orphans' ), 'slug' => array( 'slugs' ), 'excerpt' => array( 'excerpts' ), 'links' => array( 'links' ) );
		if ( ! isset( $plans[ $what ] ) ) { return false; }
		update_option( self::JOB, array( 'stages' => $plans[ $what ], 'i' => 0, 'cursor' => 0, 'ids' => null, 'n' => 0, 'what' => $what, 'started' => time() ), false );
		return true;
	}

	public static function job() { $j = get_option( self::JOB ); return is_array( $j ) ? $j : null; }

	/** Work for about five seconds. Returns array( done, stage, n ). */
	public static function job_step() {
		$j = self::job();
		if ( ! $j ) { return array( 'done' => true, 'stage' => '', 'n' => 0 ); }
		$end = microtime( true ) + 5; $count = count( $j['stages'] );
		while ( $j['i'] < $count && microtime( true ) < $end ) {
			if ( self::stage( $j['stages'][ $j['i'] ], $j, $end ) ) { $j['i']++; $j['cursor'] = 0; $j['ids'] = null; }
		}
		if ( $j['i'] >= $count ) {
			delete_option( self::JOB );
			self::recheck();
			return array( 'done' => true, 'stage' => '', 'n' => (int) $j['n'] );
		}
		update_option( self::JOB, $j, false );
		return array( 'done' => false, 'stage' => $j['stages'][ $j['i'] ], 'n' => (int) $j['n'] );
	}

	/** One slice of one stage. True when the stage has no more to do. */
	private static function stage( $name, array &$j, $end ) {
		global $wpdb;
		switch ( $name ) {
			case 'links':
				self::links();
				return true;
			case 'excerpts':
				$scope = Sidcraft_RankBoost_Audit::scope_ids();
				$types = Sidcraft_RankBoost_Util::post_types(); $in = implode( ',', array_fill( 0, count( $types ), '%s' ) );
				$only  = null === $scope ? '' : ( $scope ? ' AND ID IN (' . implode( ',', array_map( 'intval', $scope ) ) . ')' : ' AND 1 = 0' );
				$ids   = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE ID > %d AND post_status = 'publish' AND post_excerpt = '' AND post_type IN ($in){$only} ORDER BY ID ASC LIMIT 8", array_merge( array( (int) $j['cursor'] ), $types ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- integers only.
				if ( ! $ids ) { return true; }
				foreach ( $ids as $id ) {
					$post = get_post( (int) $id ); $j['cursor'] = (int) $id;
					if ( $post ) {
						$text = Sidcraft_RankBoost_Util::lead_text( $post );
						if ( Sidcraft_RankBoost_Util::len( $text ) >= 60 ) { wp_update_post( wp_slash( array( 'ID' => $post->ID, 'post_excerpt' => Sidcraft_RankBoost_Util::truncate( $text, 200 ) ) ) ); $j['n']++; }
					}
					if ( microtime( true ) > $end ) { break; }
				}
				return false;
			case 'images':
				$j['n'] += Sidcraft_RankBoost_Images::fix_library( 20 );
				$j['cursor']++;
				return $j['cursor'] >= 10;
			case 'orphans':
			case 'slugs':
				if ( ! is_array( $j['ids'] ) ) { $j['ids'] = self::posts_with_issue( 'orphans' === $name ? 'orphan' : 'slug', 500 ); }
				for ( $k = 0; $k < 5 && $j['cursor'] < count( $j['ids'] ); $k++, $j['cursor']++ ) {
					$post = get_post( (int) $j['ids'][ $j['cursor'] ] );
					if ( ! $post ) { continue; }
					if ( 'orphans' === $name ) { $j['n'] += self::fix_orphan( $post ); } else { $j['n'] += self::fix_slug( $post ); }
					if ( microtime( true ) > $end ) { $j['cursor']++; break; }
				}
				return $j['cursor'] >= count( $j['ids'] );
		}
		return true;
	}

	private static function fix_orphan( $post ) {
		$host = self::pick_host( $post );
		if ( ! $host ) { return 0; }
		$extra = self::extra_ids( $host->ID ); $n = 0;
		if ( ! in_array( (int) $post->ID, $extra, true ) ) { $extra[] = (int) $post->ID; update_post_meta( $host->ID, self::EXTRA, ',' . implode( ',', $extra ) . ',' ); $n = 1; }
		Sidcraft_RankBoost_Audit::store( $post ); Sidcraft_RankBoost_Audit::store( $host );
		return $n;
	}

	private static function fix_slug( $post ) {
		$new = self::short_slug( $post->post_name );
		if ( '' === $new || $new === $post->post_name ) { return 0; }
		wp_update_post( array( 'ID' => $post->ID, 'post_name' => wp_unique_post_slug( $new, $post->ID, $post->post_status, $post->post_type, $post->post_parent ) ) );
		return 1;
	}

	public static function ajax() {
		check_ajax_referer( 'sidcraft_rankboost_audit' );
		if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( 'forbidden', 403 ); }
		wp_send_json_success( self::job_step() );
	}

	/** Everything that is safe to do without asking: not slugs, because those change web addresses. */
	public static function run_all_safe() {
		self::links();
		self::excerpts();
		Sidcraft_RankBoost_Images::fix_library( 300 );
		self::orphans();
		self::recheck();
		return __( 'Done. Re-checking every page now; the scores update as it goes.', 'sidcraft-rankboost' );
	}

	/** Mark everything for a new audit. The Overview screen then re-checks page by page without long requests. */
	public static function recheck() {
		Sidcraft_RankBoost_Audit::reset_marks();
		set_transient( 'sidcraft_rankboost_resume', 1, 10 * MINUTE_IN_SECONDS );
		wp_schedule_single_event( time() + 60, 'sidcraft_rankboost_audit_more' );
	}

	/** Apply every automatic fix that applies to a single post. */
	public static function fix_post( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) { return 0; }
		$done = 0;
		$issues = json_decode( (string) get_post_meta( $post->ID, '_sidcraft_rankboost_issues', true ), true );
		$ids = array_column( (array) $issues, 'id' );
		if ( in_array( 'orphan', $ids, true ) ) {
			$host = self::pick_host( $post );
			if ( $host ) {
				$extra = self::extra_ids( $host->ID ); $extra[] = (int) $post->ID;
				update_post_meta( $host->ID, self::EXTRA, ',' . implode( ',', array_unique( $extra ) ) . ',' );
				Sidcraft_RankBoost_Audit::store( $host );
				$done++;
			}
		}
		if ( in_array( 'slug', $ids, true ) ) {
			$new = self::short_slug( $post->post_name );
			if ( '' !== $new && $new !== $post->post_name ) {
				wp_update_post( array( 'ID' => $post->ID, 'post_name' => wp_unique_post_slug( $new, $post->ID, $post->post_status, $post->post_type, $post->post_parent ) ) );
				$done++;
			}
		}
		if ( '' === trim( $post->post_excerpt ) ) {
			$text = Sidcraft_RankBoost_Util::lead_text( $post );
			if ( Sidcraft_RankBoost_Util::len( $text ) >= 60 ) { wp_update_post( wp_slash( array( 'ID' => $post->ID, 'post_excerpt' => Sidcraft_RankBoost_Util::truncate( $text, 200 ) ) ) ); $done++; }
		}
		if ( in_array( 'links', $ids, true ) ) { self::links(); $done++; }
		Sidcraft_RankBoost_Audit::store( $post->ID );
		return $done;
	}
}
