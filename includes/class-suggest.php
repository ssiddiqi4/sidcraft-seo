<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Rule-based suggestions for the problems that need a person. No AI and no outside service:
 * everything is built from the text, headings, categories and media that are already on the site.
 */
class Sidcraft_AutoSEO_Suggest {

	/** Suggestions for one issue id. Each item: array( 'kind' => title|desc|thumb|text, 'text' => string, 'apply' => '', 'value' => '', 'note' => '' ). */
	public static function for_issue( $post, $id ) {
		$post = get_post( $post );
		if ( ! $post ) { return array(); }
		switch ( $id ) {
			case 'title':    return self::titles( $post, false );
			case 'kw_title': return self::titles( $post, true );
			case 'desc':     return self::descriptions( $post );
			case 'read':     return self::long_sentences( $post );
			case 'h2':       return self::headings( $post );
			case 'thin':     return self::outline( $post );
			case 'kw_intro': return self::intro( $post );
			case 'social':
			case 'noimg':    return self::images( $post, 'social' === $id );
		}
		return array();
	}

	public static function supported() { return array( 'title', 'kw_title', 'desc', 'read', 'h2', 'thin', 'kw_intro', 'social', 'noimg' ); }

	/* ---------- helpers ---------- */

	private static function sentences( $post ) {
		$text = Sidcraft_AutoSEO_Util::post_text( $post );
		$text = trim( preg_replace( '/\s+/u', ' ', preg_replace( '/\[\/?[a-z0-9_-]+[^\]]*\]/i', ' ', $text ) ) ); // leftover shortcodes.
		return preg_split( '/(?<=[.!?])\s+/u', trim( $text ), -1, PREG_SPLIT_NO_EMPTY );
	}

	private static function focus( $post ) { return trim( (string) Sidcraft_AutoSEO_Meta::focus_for_post( $post->ID ) ); }

	private static function ucf( $s ) {
		$s = (string) $s;
		return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( mb_substr( $s, 0, 1 ) ) . mb_substr( $s, 1 ) : ucfirst( $s );
	}

	private static function words( $s ) { return preg_split( '/\s+/u', trim( $s ), -1, PREG_SPLIT_NO_EMPTY ); }

	private static function title_case( $s ) {
		$small = array( 'a', 'an', 'and', 'of', 'the', 'in', 'on', 'for', 'to', 'at', 'or' );
		$w     = self::words( $s );
		foreach ( $w as $i => $x ) {
			$w[ $i ] = ( $i > 0 && in_array( Sidcraft_AutoSEO_Util::lower( $x ), $small, true ) ) ? Sidcraft_AutoSEO_Util::lower( $x ) : self::ucf( $x );
		}
		return implode( ' ', $w );
	}

	/** Keep only candidates that are 25-60 characters, unique and different from the current title. */
	private static function fit_titles( $cands, $current ) {
		$out = array();
		foreach ( $cands as $c ) {
			$c = trim( Sidcraft_AutoSEO_Util::clean( $c ), " -–—|:" );
			$l = Sidcraft_AutoSEO_Util::len( $c );
			if ( $l >= 25 && $l <= 60 && 0 !== strcasecmp( $c, $current ) ) { $out[ Sidcraft_AutoSEO_Util::lower( $c ) ] = $c; }
		}
		return array_values( $out );
	}

	/* ---------- titles ---------- */

	private static function titles( $post, $need_focus ) {
		$title = Sidcraft_AutoSEO_Util::clean( get_the_title( $post ) );
		$focus = self::focus( $post );
		$F     = '' !== $focus ? self::title_case( $focus ) : '';
		$cands = array();
		if ( $need_focus && '' !== $F ) {
			$cands[] = $F . ': ' . $title;
			$cands[] = $title . ' – ' . $F;
			$cands[] = $F . ' – ' . $title;
		}
		foreach ( Sidcraft_AutoSEO_Util::headings( $post ) as $h ) {
			if ( ! empty( $h[1] ) && Sidcraft_AutoSEO_Util::len( $h[1] ) >= 15 ) {
				$cands[] = $h[1];
				if ( '' !== $F && false === Sidcraft_AutoSEO_Util::ipos( $h[1], $focus ) ) { $cands[] = $h[1] . ' – ' . $F; }
			}
		}
		if ( 'post' === $post->post_type ) {
			$cat = wp_get_post_categories( $post->ID, array( 'fields' => 'names' ) );
			if ( $cat && 'Uncategorized' !== $cat[0] ) { $cands[] = $title . ' – ' . $cat[0]; $cands[] = $cat[0] . ': ' . $title; }
		} elseif ( $post->post_parent ) {
			$cands[] = get_the_title( $post->post_parent ) . ': ' . $title;
		}
		$cands[] = $title . ' – ' . get_bloginfo( 'name' );
		$lead = self::sentences( $post );
		if ( $lead ) {
			$first = preg_split( '/[,;:–—-]\s+/u', $lead[0] );
			$cands[] = $title . ': ' . Sidcraft_AutoSEO_Util::truncate( $first[0], 30 );
		}
		$cands = self::fit_titles( $cands, $title );
		if ( $need_focus && '' !== $focus ) { $with = array_values( array_filter( $cands, function ( $c ) use ( $focus ) { return false !== Sidcraft_AutoSEO_Util::ipos( $c, $focus ); } ) ); $cands = $with ? $with : $cands; }
		$out = array();
		foreach ( array_slice( $cands, 0, 4 ) as $c ) { $out[] = array( 'kind' => 'title', 'text' => $c, 'apply' => 'title', 'value' => $c, 'note' => '' ); }
		if ( ! $out ) { $out[] = array( 'kind' => 'text', 'text' => $need_focus ? sprintf( /* translators: %s: main phrase */ __( 'Rename the page so its title contains “%s”.', 'sidcraft-autoseo' ), $focus ) : __( 'Add one or two descriptive words to the page title (what it is and who it is for).', 'sidcraft-autoseo' ), 'apply' => '', 'value' => '', 'note' => '' ); }
		return $out;
	}

	/* ---------- descriptions ---------- */

	private static function descriptions( $post ) {
		$title = Sidcraft_AutoSEO_Util::clean( get_the_title( $post ) );
		$sent  = self::sentences( $post );
		$site  = get_bloginfo( 'name' );
		$built = '';
		foreach ( $sent as $s ) {
			if ( Sidcraft_AutoSEO_Util::len( $built . ' ' . $s ) > 155 ) { break; }
			$built = trim( $built . ' ' . $s );
		}
		$cands = array();
		$real  = '' !== $built && 0 !== strcasecmp( trim( $built, ' .' ), $title ) && Sidcraft_AutoSEO_Util::len( $built ) > Sidcraft_AutoSEO_Util::len( $title ) + 10;
		if ( $real ) {
			if ( Sidcraft_AutoSEO_Util::len( $built ) >= 70 ) { $cands[] = $built; }
			$cands[] = $title . ': ' . $built;
			$cands[] = rtrim( $built, '.!? ' ) . '. ' . sprintf( /* translators: 1: page title, 2: site name */ __( '%1$s on %2$s.', 'sidcraft-autoseo' ), $title, $site );
		}
		$out = array(); $seen = array();
		foreach ( $cands as $c ) {
			$c = Sidcraft_AutoSEO_Util::truncate( $c, 158 );
			$l = Sidcraft_AutoSEO_Util::len( $c );
			if ( $l >= 70 && ! isset( $seen[ $c ] ) ) { $seen[ $c ] = 1; $out[] = array( 'kind' => 'desc', 'text' => $c, 'apply' => 'desc', 'value' => $c, 'note' => sprintf( /* translators: %d: characters */ __( '%d characters', 'sidcraft-autoseo' ), $l ) ); }
		}
		if ( ! $out ) { $out[] = array( 'kind' => 'text', 'text' => __( 'There is too little text on this page to build a description from. Write one or two sentences that say what the page offers, or fill in the excerpt.', 'sidcraft-autoseo' ), 'apply' => '', 'value' => '', 'note' => '' ); }
		return array_slice( $out, 0, 3 );
	}

	/* ---------- readability ---------- */

	private static function long_sentences( $post ) {
		$out = array();
		foreach ( self::sentences( $post ) as $s ) {
			$w = self::words( $s );
			if ( count( $w ) <= 25 ) { continue; }
			$odd = count( array_filter( $w, function ( $x ) { return preg_match( '/\d|:$/', $x ); } ) );
			if ( $odd > count( $w ) * 0.25 ) { continue; } // schedules, prices and lists are not prose.
			$mid = count( $w ) / 2; $best = null; $dist = 1e9; $pos = 0;
			foreach ( $w as $i => $x ) {
				$prev = $i > 0 ? $w[ $i - 1 ] : '';
				$cut  = ( ';' === substr( $prev, -1 ) ) || ( ',' === substr( $prev, -1 ) && in_array( Sidcraft_AutoSEO_Util::lower( $x ), array( 'and', 'but', 'so' ), true ) );
				if ( $cut && $i > 6 && $i < count( $w ) - 6 && abs( $i - $mid ) < $dist ) { $dist = abs( $i - $mid ); $best = $i; }
			}
			if ( null === $best ) { $out[] = array( 'kind' => 'text', 'text' => Sidcraft_AutoSEO_Util::truncate( $s, 160 ), 'apply' => '', 'value' => '', 'note' => sprintf( /* translators: %d: words */ __( '%d words: find a natural place to start a new sentence.', 'sidcraft-autoseo' ), count( $w ) ) ); }
			else {
				$a = rtrim( implode( ' ', array_slice( $w, 0, $best ) ), ',;' ) . '.';
				$bw = array_slice( $w, $best );
				if ( $bw && in_array( Sidcraft_AutoSEO_Util::lower( $bw[0] ), array( 'and', 'but', 'so' ), true ) ) { array_shift( $bw ); }
				$b = $bw ? self::ucf( implode( ' ', $bw ) ) : '';
				$out[] = array( 'kind' => 'text', 'text' => $a . ' ' . $b, 'apply' => '', 'value' => '', 'note' => sprintf( /* translators: %d: words */ __( 'Was %d words. Suggested split:', 'sidcraft-autoseo' ), count( $w ) ) );
			}
			if ( count( $out ) >= 3 ) { break; }
		}
		return $out;
	}

	/* ---------- headings ---------- */

	private static function headings( $post ) {
		$sent = self::sentences( $post );
		if ( count( $sent ) < 6 ) { return array(); }
		$stop   = Sidcraft_AutoSEO_Util::stopwords();
		$lead   = array( 'he', 'she', 'it', 'they', 'his', 'her', 'its', 'their', 'this', 'that', 'these', 'those', 'we', 'our', 'you', 'your', 'there', 'here', 'also', 'however', 'in', 'at', 'on' );
		$groups = array_chunk( $sent, max( 5, (int) ceil( count( $sent ) / 4 ) ) );
		$out    = array();
		foreach ( array_slice( $groups, 0, 4 ) as $gi => $g ) {
			if ( 0 === $gi ) { continue; } // the opening needs no heading.
			$w = self::words( preg_split( '/[,;:–—]\s+/u', $g[0] )[0] );
			while ( $w && in_array( Sidcraft_AutoSEO_Util::lower( trim( $w[0], '.,' ) ), $lead, true ) ) { array_shift( $w ); }
			$w = array_slice( $w, 0, 4 );
			while ( $w && in_array( Sidcraft_AutoSEO_Util::lower( trim( end( $w ), '.,' ) ), $stop, true ) ) { array_pop( $w ); }
			if ( count( $w ) < 2 ) { continue; }
			$out[] = array( 'kind' => 'text', 'text' => self::title_case( trim( implode( ' ', $w ), '.,;:' ) ), 'apply' => '', 'value' => '', 'note' => sprintf( /* translators: %s: first words of the paragraph */ __( 'Before the section starting “%s”', 'sidcraft-autoseo' ), Sidcraft_AutoSEO_Util::truncate( $g[0], 60 ) ) );
		}
		return $out;
	}

	/* ---------- thin content ---------- */

	private static function outline( $post ) {
		$focus = self::focus( $post ); $F = '' !== $focus ? $focus : Sidcraft_AutoSEO_Util::lower( get_the_title( $post ) );
		$words = Sidcraft_AutoSEO_Util::word_count( Sidcraft_AutoSEO_Util::post_text( $post ) );
		$need  = max( 0, 300 - $words );
		$out   = array( array( 'kind' => 'text', 'text' => sprintf( /* translators: 1: words missing, 2: paragraphs */ __( 'About %1$d more words, roughly %2$d short paragraphs.', 'sidcraft-autoseo' ), $need, max( 1, (int) ceil( $need / 70 ) ) ), 'apply' => '', 'value' => '', 'note' => '' ) );
		$prompts = array(
			/* translators: %s: topic */ sprintf( __( 'What %s is, in two sentences', 'sidcraft-autoseo' ), $F ),
			__( 'Who it is for and what they get from it', 'sidcraft-autoseo' ),
			__( 'When, where or how to take part', 'sidcraft-autoseo' ),
			/* translators: %s: topic */ sprintf( __( '3 short questions people ask about %s, each answered in two sentences', 'sidcraft-autoseo' ), $F ),
			__( 'How to get in touch or take the next step', 'sidcraft-autoseo' ),
		);
		foreach ( $prompts as $p ) { $out[] = array( 'kind' => 'text', 'text' => $p, 'apply' => '', 'value' => '', 'note' => __( 'Write about:', 'sidcraft-autoseo' ) ); }
		foreach ( Sidcraft_AutoSEO_Related::related_posts( $post, 3 ) as $r ) {
			$out[] = array( 'kind' => 'link', 'text' => wp_strip_all_tags( html_entity_decode( get_the_title( $r ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ), 'apply' => '', 'value' => (string) $r->ID, 'note' => __( 'Related page with material you can build on:', 'sidcraft-autoseo' ) );
		}
		return $out;
	}

	/* ---------- main phrase in the first paragraph ---------- */

	private static function intro( $post ) {
		$focus = self::focus( $post );
		$sent  = self::sentences( $post );
		if ( '' === $focus ) { return array(); }
		$first = $sent ? $sent[0] : '';
		$F     = self::ucf( $focus );
		$out   = array( array( 'kind' => 'text', 'text' => sprintf( /* translators: %s: main phrase */ __( '%s: ', 'sidcraft-autoseo' ), $F ) . $first, 'apply' => '', 'value' => '', 'note' => __( 'Open the first paragraph like this:', 'sidcraft-autoseo' ) ) );
		$out[] = array( 'kind' => 'text', 'text' => sprintf( /* translators: %s: main phrase */ __( 'This page is about %s. ', 'sidcraft-autoseo' ), $focus ) . $first, 'apply' => '', 'value' => '', 'note' => __( 'Or like this:', 'sidcraft-autoseo' ) );
		return $out;
	}

	/* ---------- images from the media library ---------- */

	private static function images( $post, $can_apply ) {
		$stop  = Sidcraft_AutoSEO_Util::stopwords();
		$terms = array_filter( preg_split( '/[^\p{L}\p{N}]+/u', Sidcraft_AutoSEO_Util::lower( get_the_title( $post ) . ' ' . self::focus( $post ) ), -1, PREG_SPLIT_NO_EMPTY ), function ( $w ) use ( $stop ) { return Sidcraft_AutoSEO_Util::len( $w ) > 3 && ! in_array( $w, $stop, true ); } );
		$found = array();
		foreach ( array_slice( array_unique( $terms ), 0, 4 ) as $t ) {
			foreach ( get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => 'image', 'posts_per_page' => 4, 's' => $t, 'no_found_rows' => true ) ) as $a ) { $found[ $a->ID ] = $a; }
		}
		$out = array();
		foreach ( array_slice( $found, 0, 4, true ) as $a ) {
			$out[] = array( 'kind' => 'thumb', 'text' => wp_strip_all_tags( html_entity_decode( get_the_title( $a ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ), 'apply' => $can_apply ? 'thumb' : '', 'value' => (string) $a->ID, 'note' => '' );
		}
		if ( ! $out ) { $out[] = array( 'kind' => 'text', 'text' => __( 'No image in your media library matches this page. Upload one and name the file after the page topic.', 'sidcraft-autoseo' ), 'apply' => '', 'value' => '', 'note' => '' ); }
		return $out;
	}

	/* ---------- applying (only SEO-side settings: never rewrites page text) ---------- */

	public static function apply( $post_id, $what, $value ) {
		$post = get_post( $post_id );
		if ( ! $post || ! current_user_can( 'edit_post', $post_id ) ) { return false; }
		if ( 'title' === $what ) { $v = sanitize_text_field( $value ); if ( '' === $v ) { return false; } update_post_meta( $post_id, '_sidcraft_autoseo_title', $v ); }
		elseif ( 'desc' === $what ) { $v = sanitize_text_field( $value ); if ( '' === $v ) { return false; } update_post_meta( $post_id, '_sidcraft_autoseo_desc', $v ); }
		elseif ( 'thumb' === $what ) { $aid = (int) $value; if ( ! $aid || 'attachment' !== get_post_type( $aid ) ) { return false; } set_post_thumbnail( $post_id, $aid ); }
		else { return false; }
		Sidcraft_AutoSEO_Audit::store( $post );
		Sidcraft_AutoSEO_Audit::summary( true );
		return true;
	}
}
