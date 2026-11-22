<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Titles, descriptions, canonical, robots and social cards, generated automatically for every URL. */
class Sidcraft_RankBoost_Meta {

	public static function init() {
		add_filter( 'pre_get_document_title', array( __CLASS__, 'title' ), 20 );
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ), 20 );
		remove_action( 'wp_head', 'rel_canonical' );
		add_action( 'wp_head', array( __CLASS__, 'head' ), 2 );
		add_action( 'save_post', array( __CLASS__, 'on_save' ), 20, 2 );
	}

	/* ---------- values ---------- */

	public static function site_name() { return wp_strip_all_tags( get_bloginfo( 'name' ) ); }

	/** Override stored by the (optional) meta box, or ''. */
	public static function override( $post_id, $key ) {
		return trim( (string) get_post_meta( $post_id, '_sidcraft_rankboost_' . $key, true ) );
	}

	public static function title_for_post( $post ) {
		$post = get_post( $post );
		$ov   = self::override( $post->ID, 'title' );
		return '' !== $ov ? $ov : self::auto_title( $post );
	}

	/** The title made automatically (what is used when nothing is typed by hand). */
	public static function auto_title( $post ) {
		$post = get_post( $post );
		return self::with_suffix( Sidcraft_RankBoost_Util::clean( get_the_title( $post ) ) );
	}

	/** "Title | Site" when it fits in 60 characters, otherwise just the title. */
	public static function with_suffix( $base ) {
		$base = Sidcraft_RankBoost_Util::truncate( $base, 60 );
		if ( Sidcraft_RankBoost_Settings::on( 'title_suffix' ) ) {
			$sep = trim( (string) Sidcraft_RankBoost_Settings::get( 'title_sep' ) ) ?: '|';
			$full = $base . ' ' . $sep . ' ' . self::site_name();
			if ( Sidcraft_RankBoost_Util::len( $full ) <= 60 ) { return $full; }
		}
		return $base;
	}

	public static function description_for_post( $post ) {
		$post = get_post( $post );
		$ov   = self::override( $post->ID, 'desc' );
		return '' !== $ov ? $ov : self::auto_description( $post );
	}

	/** The description made automatically. */
	public static function auto_description( $post ) {
		$post = get_post( $post );
		if ( has_excerpt( $post ) ) {
			$d = Sidcraft_RankBoost_Util::clean( wp_strip_all_tags( $post->post_excerpt ) );
			if ( Sidcraft_RankBoost_Util::len( $d ) >= 50 ) { return Sidcraft_RankBoost_Util::truncate( $d, 158 ); }
		}
		$text = Sidcraft_RankBoost_Util::lead_text( $post );
		// Skip the repeated title at the start of the text.
		$title = Sidcraft_RankBoost_Util::clean( get_the_title( $post ) );
		if ( '' !== $title && 0 === stripos( $text, $title ) ) { $text = ltrim( substr( $text, strlen( $title ) ), " .:-–—" ); }
		if ( Sidcraft_RankBoost_Util::len( $text ) < 40 ) {
			$text = $title . ( '' !== $text ? '. ' . $text : '' ) . ' – ' . self::site_name();
		}
		return Sidcraft_RankBoost_Util::truncate( $text, 158 );
	}

	/** Describe whatever the current request is. */
	public static function current() {
		$c = array( 'title' => '', 'desc' => '', 'url' => '', 'type' => 'website', 'post' => null, 'noindex' => false, 'image' => array() );
		$site = self::site_name();
		$tag  = trim( wp_strip_all_tags( get_bloginfo( 'description' ) ) );

		if ( is_front_page() && is_singular() ) {
			$post = get_queried_object();
			$c['post']  = $post;
			$ov = self::override( $post->ID, 'title' );
			$c['title'] = '' !== $ov ? $ov : ( $tag && Sidcraft_RankBoost_Util::len( $site . ' – ' . $tag ) <= 62 ? $site . ' – ' . $tag : self::with_suffix( Sidcraft_RankBoost_Util::clean( get_the_title( $post ) ) ) );
			$c['desc']  = self::description_for_post( $post );
			$c['url']   = home_url( '/' );
			$c['type']  = 'website';
			$c['image'] = Sidcraft_RankBoost_Util::post_image( $post );
			if ( self::override( $post->ID, 'noindex' ) ) { $c['noindex'] = true; }
			self::apply_manual( $c, $post );
		} elseif ( is_singular() ) {
			$post = get_queried_object();
			$c['post']  = $post;
			$c['title'] = self::title_for_post( $post );
			$c['desc']  = self::description_for_post( $post );
			$c['url']   = self::canonical_for_post( $post );
			$c['type']  = is_singular( 'page' ) ? 'website' : 'article';
			$c['image'] = Sidcraft_RankBoost_Util::post_image( $post );
			if ( self::override( $post->ID, 'noindex' ) ) { $c['noindex'] = true; }
			self::apply_manual( $c, $post );
		} elseif ( is_front_page() || is_home() ) {
			$c['title'] = $tag && Sidcraft_RankBoost_Util::len( $site . ' – ' . $tag ) <= 62 ? $site . ' – ' . $tag : $site;
			$c['desc']  = $tag ? Sidcraft_RankBoost_Util::truncate( $tag . '. ' . $site, 158 ) : $site;
			$c['url']   = home_url( '/' );
			if ( is_paged() ) { $c['url'] = get_pagenum_link( get_query_var( 'paged' ) ); }
			$c['image'] = Sidcraft_RankBoost_Util::post_image( null );
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( $term instanceof WP_Term ) {
				$c['title'] = self::with_suffix( $term->name );
				$d = Sidcraft_RankBoost_Util::clean( wp_strip_all_tags( term_description( $term ) ) );
				$c['desc']  = $d ? Sidcraft_RankBoost_Util::truncate( $d, 158 ) : Sidcraft_RankBoost_Util::truncate( sprintf( 'Articles and pages about %s on %s.', $term->name, $site ), 158 );
				$c['url']   = get_term_link( $term );
				if ( is_paged() ) { $c['url'] = get_pagenum_link( (int) get_query_var( 'paged' ) ); }
				if ( Sidcraft_RankBoost_Settings::on( 'noindex_thin_tags' ) && $term->count < 2 && ! is_category() ) { $c['noindex'] = true; }
				$c['image'] = Sidcraft_RankBoost_Util::post_image( null );
			}
		} elseif ( is_author() ) {
			$u = get_queried_object();
			$c['title'] = self::with_suffix( $u ? $u->display_name : 'Author' );
			$c['desc']  = Sidcraft_RankBoost_Util::truncate( $u ? Sidcraft_RankBoost_Util::clean( get_the_author_meta( 'description', $u->ID ) ) : '', 158 );
			if ( '' === $c['desc'] ) { $c['desc'] = sprintf( 'Posts by %s on %s.', $u ? $u->display_name : '', $site ); }
			$c['url']   = $u ? get_author_posts_url( $u->ID ) : '';
		} elseif ( is_post_type_archive() ) {
			$pt = get_queried_object();
			$c['title'] = self::with_suffix( $pt && isset( $pt->labels->name ) ? $pt->labels->name : 'Archive' );
			$c['desc']  = Sidcraft_RankBoost_Util::truncate( sprintf( 'Browse %s on %s.', $pt ? $pt->labels->name : 'content', $site ), 158 );
			$c['url']   = get_post_type_archive_link( get_query_var( 'post_type' ) );
		} elseif ( is_search() ) {
			$c['title'] = self::with_suffix( sprintf( 'Search results for “%s”', get_search_query() ) );
			$c['noindex'] = true;
		} elseif ( is_404() ) {
			$c['title'] = self::with_suffix( 'Page not found' );
			$c['noindex'] = true;
		} elseif ( is_date() ) {
			$c['title'] = self::with_suffix( wp_strip_all_tags( get_the_archive_title() ) );
			$c['noindex'] = true;
		}
		if ( '' === $c['url'] ) { $c['url'] = home_url( add_query_arg( null, null ) ); }
		return apply_filters( 'sidcraft_rankboost_current', $c );
	}

	/** Hand-written social card values and nofollow for a post. */
	private static function apply_manual( array &$c, $post ) {
		$c['og_title'] = self::override( $post->ID, 'og_title' ); $c['og_desc'] = self::override( $post->ID, 'og_desc' );
		$img = self::override( $post->ID, 'og_image' );
		if ( '' !== $img ) { $c['image'] = array( 'url' => $img, 'w' => 0, 'h' => 0 ); }
		if ( self::override( $post->ID, 'nofollow' ) ) { $c['nofollow'] = true; }
	}

	public static function canonical_for_post( $post ) {
		$post = get_post( $post );
		$ov = $post ? self::override( $post->ID, 'canonical' ) : '';
		if ( '' !== $ov ) { return $ov; }
		$url = get_permalink( $post );
		$page = (int) get_query_var( 'page' );
		if ( $page > 1 ) { $url = trailingslashit( $url ) . user_trailingslashit( $page, 'single_paged' ); }
		return $url;
	}

	/* ---------- output ---------- */

	public static function title( $title ) {
		if ( is_feed() || is_embed() ) { return $title; }
		$c = self::current();
		return $c['title'] ? $c['title'] : $title;
	}

	public static function robots( $robots ) {
		// Respect Settings > Reading > "Discourage search engines from indexing this site".
		// WordPress already adds noindex in that case; forcing index here would undo it.
		if ( '0' === (string) get_option( 'blog_public' ) ) { return $robots; }
		$c = self::current();
		if ( $c['noindex'] ) {
			$robots['noindex'] = true; unset( $robots['index'] );
			$robots['follow'] = true;
		} elseif ( empty( $robots['noindex'] ) ) {
			// Only add "index" when nothing else has asked for noindex.
			$robots['index'] = true;
		}
		if ( ! empty( $c['nofollow'] ) ) { $robots['nofollow'] = true; unset( $robots['follow'] ); }
		$robots['max-image-preview'] = 'large';
		$robots['max-snippet']       = '-1';
		$robots['max-video-preview'] = '-1';
		return $robots;
	}

	public static function head() {
		if ( is_feed() || is_embed() || is_robots() ) { return; }
		$c = self::current();
		$out = array();
		if ( $c['desc'] ) { $out[] = '<meta name="description" content="' . esc_attr( $c['desc'] ) . '" />'; }
		if ( $c['url'] && ! is_404() && ! is_search() ) { $out[] = '<link rel="canonical" href="' . esc_url( $c['url'] ) . '" />'; }

		if ( Sidcraft_RankBoost_Settings::on( 'social_cards' ) ) {
			$out[] = '<meta property="og:locale" content="' . esc_attr( get_locale() ) . '" />';
			$out[] = '<meta property="og:type" content="' . esc_attr( $c['type'] ) . '" />';
			$st = ! empty( $c['og_title'] ) ? $c['og_title'] : $c['title']; $sd = ! empty( $c['og_desc'] ) ? $c['og_desc'] : $c['desc'];
			$out[] = '<meta property="og:title" content="' . esc_attr( $st ) . '" />';
			if ( $sd ) { $out[] = '<meta property="og:description" content="' . esc_attr( $sd ) . '" />'; }
			$out[] = '<meta property="og:url" content="' . esc_url( $c['url'] ) . '" />';
			$out[] = '<meta property="og:site_name" content="' . esc_attr( self::site_name() ) . '" />';
			$card  = 'summary';
			if ( ! empty( $c['image']['url'] ) ) {
				$out[] = '<meta property="og:image" content="' . esc_url( $c['image']['url'] ) . '" />';
				if ( ! empty( $c['image']['w'] ) ) {
					$out[] = '<meta property="og:image:width" content="' . (int) $c['image']['w'] . '" />';
					$out[] = '<meta property="og:image:height" content="' . (int) $c['image']['h'] . '" />';
				}
				$card = 'summary_large_image';
			}
			if ( 'article' === $c['type'] && $c['post'] ) {
				$out[] = '<meta property="article:published_time" content="' . esc_attr( get_the_date( 'c', $c['post'] ) ) . '" />';
				$out[] = '<meta property="article:modified_time" content="' . esc_attr( get_the_modified_date( 'c', $c['post'] ) ) . '" />';
			}
			$out[] = '<meta name="twitter:card" content="' . esc_attr( $card ) . '" />';
			$out[] = '<meta name="twitter:title" content="' . esc_attr( $st ) . '" />';
			if ( $sd ) { $out[] = '<meta name="twitter:description" content="' . esc_attr( $sd ) . '" />'; }
			if ( ! empty( $c['image']['url'] ) ) { $out[] = '<meta name="twitter:image" content="' . esc_url( $c['image']['url'] ) . '" />'; }
			$tw = ltrim( (string) Sidcraft_RankBoost_Settings::get( 'twitter_site' ), '@' );
			if ( $tw ) { $out[] = '<meta name="twitter:site" content="@' . esc_attr( $tw ) . '" />'; }
		}
		echo "\n<!-- Sidcraft RankBoost -->\n" . implode( "\n", $out ) . "\n<!-- /Sidcraft RankBoost -->\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- every part escaped above.
	}

	/* ---------- save-time automation ---------- */

	public static function on_save( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || 'publish' !== $post->post_status ) { return; }
		if ( ! in_array( $post->post_type, Sidcraft_RankBoost_Util::post_types(), true ) ) { return; }
		// Empty excerpt: write one from the text. Never overwrites an excerpt a person wrote.
		if ( Sidcraft_RankBoost_Settings::on( 'auto_excerpt' ) && '' === trim( $post->post_excerpt ) ) {
			$text = Sidcraft_RankBoost_Util::post_text( $post );
			if ( Sidcraft_RankBoost_Util::len( $text ) >= 60 ) {
				remove_action( 'save_post', array( __CLASS__, 'on_save' ), 20 );
				wp_update_post( array( 'ID' => $post_id, 'post_excerpt' => Sidcraft_RankBoost_Util::truncate( $text, 200 ) ) );
				add_action( 'save_post', array( __CLASS__, 'on_save' ), 20, 2 );
			}
		}
		if ( '' === self::override( $post_id, 'focus' ) ) {
			update_post_meta( $post_id, '_sidcraft_rankboost_focus_auto', Sidcraft_RankBoost_Util::auto_focus( $post ) );
		}
	}

	public static function focus_for_post( $post_id ) {
		$f = self::override( $post_id, 'focus' );
		if ( '' !== $f ) { return Sidcraft_RankBoost_Util::lower( $f ); }
		$a = (string) get_post_meta( $post_id, '_sidcraft_rankboost_focus_auto', true );
		return '' !== $a ? $a : Sidcraft_RankBoost_Util::auto_focus( $post_id );
	}
}
