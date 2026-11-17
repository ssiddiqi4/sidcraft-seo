<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** One-click repairs for audit findings that can be fixed safely without a person writing anything. */
class Sidcraft_AutoSEO_Fixer {

	const EXTRA = '_sidcraft_autoseo_extra_links';

	/** Issues that can be fixed automatically, with the wording shown on the button. */
	public static function catalog() {
		return array(
			'orphan' => array( 'label' => __( 'Link to these pages', 'sidcraft-autoseo' ), 'confirm' => '', 'hint' => __( 'Adds each page to the “You may also like” list of a related page, so it gets a link from somewhere.', 'sidcraft-autoseo' ) ),
			'links'  => array( 'label' => __( 'Add related-page lists', 'sidcraft-autoseo' ), 'confirm' => '', 'hint' => __( 'Turns on the “You may also like” list for pages that link nowhere.', 'sidcraft-autoseo' ) ),
			'slug'   => array( 'label' => __( 'Shorten web addresses', 'sidcraft-autoseo' ), 'confirm' => __( 'Shorten these web addresses? The old addresses will keep working through automatic redirects.', 'sidcraft-autoseo' ), 'hint' => __( 'Shortens very long web addresses. The old address redirects to the new one.', 'sidcraft-autoseo' ) ),
			'excerpt' => array( 'label' => __( 'Write missing excerpts', 'sidcraft-autoseo' ), 'confirm' => '', 'hint' => __( 'Writes a short excerpt for pages and posts that have none. Excerpts you wrote are never changed.', 'sidcraft-autoseo' ) ),
		);
	}

	/** Advice for issues that need a person. */
	public static function advice( $id ) {
		$a = array(
			'thin'     => __( 'Needs a person: add more useful text (300+ words).', 'sidcraft-autoseo' ),
			'desc'     => __( 'Needs a person: add a few sentences of text, or write an excerpt.', 'sidcraft-autoseo' ),
			'read'     => __( 'Needs a person: split long sentences.', 'sidcraft-autoseo' ),
			'h2'       => __( 'Needs a person: add section headings (H2).', 'sidcraft-autoseo' ),
			'title'    => __( 'Needs a person: make the title more descriptive.', 'sidcraft-autoseo' ),
			'kw_title' => __( 'Needs a person: use the main phrase in the title.', 'sidcraft-autoseo' ),
			'kw_intro' => __( 'Needs a person: use the main phrase in the first paragraph.', 'sidcraft-autoseo' ),
			'noimg'    => __( 'Needs a person: add an image.', 'sidcraft-autoseo' ),
			'social'   => __( 'Needs a person: add a featured image.', 'sidcraft-autoseo' ),
			'h1'       => __( 'Needs a person: keep one main heading (H1).', 'sidcraft-autoseo' ),
			'noindex'  => __( 'Your choice: this page is hidden on purpose.', 'sidcraft-autoseo' ),
		);
		return isset( $a[ $id ] ) ? $a[ $id ] : '';
	}

	/** Posts whose last audit reported a given issue (or any issue when $id is ''). */
	public static function posts_with_issue( $id, $limit = 500 ) {
		$args = array(
			'post_type' => Sidcraft_AutoSEO_Util::post_types(), 'post_status' => 'publish', 'posts_per_page' => (int) $limit, 'fields' => 'ids', 'no_found_rows' => true,
		);
		if ( '' !== $id ) {
			$args['meta_query'] = array( array( 'key' => '_sidcraft_autoseo_issues', 'value' => '"id":"' . $id . '"', 'compare' => 'LIKE' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- finds pages with a given audit finding.
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
			Sidcraft_AutoSEO_Audit::store( $post );
			Sidcraft_AutoSEO_Audit::store( $host );
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
			if ( (int) $c->ID === (int) $post->ID || Sidcraft_AutoSEO_Related::is_hidden( $c ) ) { continue; }
			if ( count( self::extra_ids( $c->ID ) ) >= 6 ) { continue; }
			return $c;
		}
		return null;
	}

	/** Turn on the related-page list for pages that link nowhere. */
	public static function links() {
		$s = Sidcraft_AutoSEO_Settings::all();
		$changed = empty( $s['related_pages'] ) || empty( $s['related_links'] );
		$s['related_pages'] = 1;
		$s['related_links'] = 1;
		update_option( Sidcraft_AutoSEO_Settings::OPTION, $s, false );
		return $changed ? count( self::posts_with_issue( 'links' ) ) : 0;
	}

	/** Write an excerpt where there is none. */
	public static function excerpts() {
		$n = 0;
		$ids = get_posts( array( 'post_type' => Sidcraft_AutoSEO_Util::post_types(), 'post_status' => 'publish', 'posts_per_page' => 300, 'fields' => 'ids', 'no_found_rows' => true ) );
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( '' !== trim( $post->post_excerpt ) ) { continue; }
			$text = Sidcraft_AutoSEO_Util::lead_text( $post );
			if ( Sidcraft_AutoSEO_Util::len( $text ) < 60 ) { continue; }
			wp_update_post( wp_slash( array( 'ID' => $id, 'post_excerpt' => Sidcraft_AutoSEO_Util::truncate( $text, 200 ) ) ) );
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
		$stop  = Sidcraft_AutoSEO_Util::stopwords();
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
				return array( $n, sprintf( _n( '%d page now has a link from a related page.', '%d pages now have a link from a related page.', $n, 'sidcraft-autoseo' ), $n ) );
			case 'links':
				self::links();
				return array( 1, __( 'The “You may also like” list is now on for pages that link nowhere.', 'sidcraft-autoseo' ) );
			case 'slug':
				$n = self::slugs();
				/* translators: %d: number of pages. */
				return array( $n, sprintf( _n( '%d web address shortened (the old one redirects).', '%d web addresses shortened (the old ones redirect).', $n, 'sidcraft-autoseo' ), $n ) );
			case 'excerpt':
				$n = self::excerpts();
				/* translators: %d: number of pages. */
				return array( $n, sprintf( _n( 'Excerpt written for %d page.', 'Excerpts written for %d pages.', $n, 'sidcraft-autoseo' ), $n ) );
		}
		return array( 0, '' );
	}

	/** Everything that is safe to do without asking: not slugs, because those change web addresses. */
	public static function run_all_safe() {
		$before = Sidcraft_AutoSEO_Audit::summary();
		self::links();
		self::excerpts();
		Sidcraft_AutoSEO_Images::fix_library( 300 );
		self::orphans();
		self::recheck();
		$after = Sidcraft_AutoSEO_Audit::summary( true );
		/* translators: 1: average score before, 2: average score after. */
		return sprintf( __( 'Done. Average score %1$d → %2$d.', 'sidcraft-autoseo' ), (int) $before['avg'], (int) $after['avg'] );
	}

	/** Re-audit everything now (used after fixes). */
	public static function recheck() {
		global $wpdb;
		$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_sidcraft_autoseo_checked' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- reset audit marks so everything is checked again.
		$left = 1; $guard = 0; $start = time();
		while ( $left > 0 && $guard++ < 50 && ( time() - $start ) < 40 ) { $left = Sidcraft_AutoSEO_Audit::batch( 40, 15 ); }
		if ( $left > 0 ) { wp_schedule_single_event( time() + 30, 'sidcraft_autoseo_audit_more' ); }
		Sidcraft_AutoSEO_Audit::summary( true );
	}

	/** Apply every automatic fix that applies to a single post. */
	public static function fix_post( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) { return 0; }
		$done = 0;
		$issues = json_decode( (string) get_post_meta( $post->ID, '_sidcraft_autoseo_issues', true ), true );
		$ids = array_column( (array) $issues, 'id' );
		if ( in_array( 'orphan', $ids, true ) ) {
			$host = self::pick_host( $post );
			if ( $host ) {
				$extra = self::extra_ids( $host->ID ); $extra[] = (int) $post->ID;
				update_post_meta( $host->ID, self::EXTRA, ',' . implode( ',', array_unique( $extra ) ) . ',' );
				Sidcraft_AutoSEO_Audit::store( $host );
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
			$text = Sidcraft_AutoSEO_Util::lead_text( $post );
			if ( Sidcraft_AutoSEO_Util::len( $text ) >= 60 ) { wp_update_post( wp_slash( array( 'ID' => $post->ID, 'post_excerpt' => Sidcraft_AutoSEO_Util::truncate( $text, 200 ) ) ) ); $done++; }
		}
		if ( in_array( 'links', $ids, true ) ) { self::links(); $done++; }
		Sidcraft_AutoSEO_Audit::store( $post->ID );
		return $done;
	}
}
