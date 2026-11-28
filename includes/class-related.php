<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Automatic internal links: posts that link to nothing get a short "Related" list at the end. */
class Sidcraft_RankBoost_Related {

	public static function init() {
		add_filter( 'the_content', array( __CLASS__, 'append' ), 100 );
	}

	/** True when the post's own text already links to other pages of this site. */
	public static function has_internal_links( $post ) {
		$home = wp_parse_url( home_url(), PHP_URL_HOST );
		$src  = $post->post_content . ' ' . (string) get_post_meta( $post->ID, '_sidsyn_document_data', true );
		$cands = array();
		if ( preg_match_all( '#href=\\\\?["\']([^"\'\\\\]+)#i', $src, $m ) ) { $cands = array_merge( $cands, $m[1] ); }
		if ( preg_match_all( '#"(?:url|link|href)"\s*:\s*"([^"]+)"#i', $src, $m2 ) ) { $cands = array_merge( $cands, array_map( 'stripslashes', $m2[1] ) ); }
		foreach ( $cands as $href ) {
			$href = trim( $href );
			if ( '' === $href || '#' === $href[0] || 0 === strpos( $href, 'mailto:' ) || 0 === strpos( $href, 'tel:' ) || preg_match( '#\.(?:jpe?g|png|gif|webp|svg|avif)(\?.*)?$#i', $href ) ) { continue; }
			$h = wp_parse_url( $href, PHP_URL_HOST );
			if ( ! $h || $h === $home || 'www.' . $home === $h || $h === 'www.' . $home ) { return true; }
		}
		return false;
	}

	public static function related_posts( $post, $limit = 4 ) {
		$base  = array( 'post_type' => $post->post_type, 'post_status' => 'publish', 'posts_per_page' => $limit + 1, 'no_found_rows' => true, 'ignore_sticky_posts' => true, 'orderby' => 'date', 'order' => 'DESC' );
		$cats  = wp_get_post_categories( $post->ID );
		$tags  = wp_get_post_tags( $post->ID, array( 'fields' => 'ids' ) );
		$found = array();
		$take  = function ( $posts ) use ( &$found, $post, $limit ) {
			foreach ( $posts as $p ) {
				if ( (int) $p->ID !== (int) $post->ID && ! isset( $found[ $p->ID ] ) && count( $found ) < $limit ) { $found[ $p->ID ] = $p; }
			}
		};
		if ( $tags ) { $take( get_posts( array_merge( $base, array( 'tag__in' => $tags ) ) ) ); }
		if ( count( $found ) < $limit && $cats ) { $take( get_posts( array_merge( $base, array( 'category__in' => $cats ) ) ) ); }
		if ( count( $found ) < $limit ) { $take( get_posts( array_merge( $base, array( 'posts_per_page' => $limit + 1 ) ) ) ); }
		return array_values( $found );
	}

	/** The home page, and any page where the owner ticked "no related list", never gets the list. */
	public static function is_hidden( $post ) {
		if ( ! $post ) { return true; }
		if ( (int) get_option( 'page_on_front' ) === (int) $post->ID || is_front_page() ) { return true; }
		return (bool) get_post_meta( $post->ID, '_sidcraft_rankboost_no_related', true );
	}

	public static function append( $content ) {
		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) { return $content; }
		$post = get_post();
		if ( self::is_hidden( $post ) ) { return $content; }
		if ( ! $post || ! in_array( $post->post_type, Sidcraft_RankBoost_Util::post_types(), true ) ) { return $content; }
		$extra_ids = Sidcraft_RankBoost_Fixer::extra_ids( $post->ID );
		$no_links  = ! self::has_internal_links( $post );
		$auto      = $no_links && ( ( 'post' === $post->post_type && Sidcraft_RankBoost_Settings::on( 'related_links' ) ) || ( 'post' !== $post->post_type && Sidcraft_RankBoost_Settings::on( 'related_pages' ) ) );
		if ( ! $extra_ids && ! $auto ) { return $content; }
		$items = array();
		foreach ( $extra_ids as $id ) {
			$p = get_post( $id );
			if ( $p && 'publish' === $p->post_status && (int) $p->ID !== (int) $post->ID ) { $items[ $p->ID ] = $p; }
		}
		if ( $auto ) {
			foreach ( self::related_posts( $post, 4 ) as $p ) {
				if ( count( $items ) >= 6 ) { break; }
				$items[ $p->ID ] = $p;
			}
		}
		if ( ! $items ) { return $content; }
		$li = '';
		foreach ( $items as $p ) { $li .= '<li><a href="' . esc_url( get_permalink( $p ) ) . '">' . esc_html( wp_strip_all_tags( get_the_title( $p ) ) ) . '</a></li>'; }
		$title = esc_html__( 'You may also like', 'sidcraft-search-boost-suite' );
		$style = 'max-width:1100px;margin:40px auto 0;padding:0 24px';
		if ( 'open' === Sidcraft_RankBoost_Settings::get( 'related_style' ) ) {
			return $content . '<nav class="sidcraft-search-boost-suite-related" aria-label="' . esc_attr__( 'Related pages', 'sidcraft-search-boost-suite' ) . '" style="' . esc_attr( $style ) . '"><h2 style="font-size:1.3em">' . $title . '</h2><ul>' . $li . '</ul></nav>';
		}
		return $content . '<nav class="sidcraft-search-boost-suite-related" aria-label="' . esc_attr__( 'Related pages', 'sidcraft-search-boost-suite' ) . '" style="' . esc_attr( $style ) . '"><details><summary style="cursor:pointer;font-weight:600">' . $title . '</summary><ul>' . $li . '</ul></details></nav>';
	}
}
