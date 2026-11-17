<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Small helpers shared by every module. */
class Sidcraft_AutoSEO_Util {


	/* ---------- multibyte-safe string helpers (work even when mbstring is missing) ---------- */

	public static function len( $s ) { return function_exists( 'mb_strlen' ) ? mb_strlen( (string) $s ) : strlen( (string) $s ); }
	public static function sub( $s, $start, $len = null ) { return function_exists( 'mb_substr' ) ? mb_substr( (string) $s, $start, $len ) : substr( (string) $s, $start, $len ); }
	public static function pos( $h, $n ) { return function_exists( 'mb_strpos' ) ? mb_strpos( (string) $h, (string) $n ) : strpos( (string) $h, (string) $n ); }
	public static function ipos( $h, $n ) { return function_exists( 'mb_stripos' ) ? mb_stripos( (string) $h, (string) $n ) : stripos( (string) $h, (string) $n ); }
	public static function rpos( $h, $n ) { return function_exists( 'mb_strrpos' ) ? mb_strrpos( (string) $h, (string) $n ) : strrpos( (string) $h, (string) $n ); }
	public static function lower( $s ) { return function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $s ) : strtolower( (string) $s ); }

	/** Name of another active SEO plugin, or '' when there is none. */
	public static function other_seo_plugin() {
		$map = array(
			'WPSEO_VERSION'                => 'Yoast SEO',
			'RANK_MATH_VERSION'            => 'Rank Math',
			'AIOSEO_VERSION'               => 'All in One SEO',
			'SEOPRESS_VERSION'             => 'SEOPress',
			'THE_SEO_FRAMEWORK_VERSION'    => 'The SEO Framework',
		);
		foreach ( $map as $const => $name ) {
			if ( defined( $const ) ) { return $name; }
		}
		return '';
	}

	/** Public post types that should get SEO treatment. */
	public static function post_types() {
		$types = get_post_types( array( 'public' => true ), 'names' );
		unset( $types['attachment'] );
		return array_values( apply_filters( 'sidcraft_autoseo_post_types', $types ) );
	}

	/** Remove tags but keep a space where block elements and line breaks were, so words do not run together. */
	public static function strip_html( $html ) {
		$html = preg_replace( '#<(script|style)\b.*?</\1>#is', '', (string) $html );
		$html = preg_replace( '#<\s*/?\s*(br|p|div|li|ul|ol|h[1-6]|tr|td|th|blockquote|figure|section|article)\b[^>]*>#i', ' ', $html );
		return wp_strip_all_tags( $html );
	}

	/** Collapse whitespace and decode entities. */
	public static function clean( $text ) {
		$text = html_entity_decode( (string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = preg_replace( '/\s+/u', ' ', $text );
		return trim( (string) $text );
	}

	/** Cut to $max characters on a word boundary, adding an ellipsis only when cut. */
	public static function truncate( $text, $max ) {
		$text = self::clean( $text );
		if ( self::len( $text ) <= $max ) { return $text; }
		$cut  = self::sub( $text, 0, $max - 1 );
		$last = self::rpos( $cut, ' ' );
		if ( false !== $last && $last > $max * 0.6 ) { $cut = self::sub( $cut, 0, $last ); }
		return rtrim( $cut, " ,;:-–—" ) . '…';
	}

	/**
	 * Plain text of a post, including text written in the Sidcraft Page Builder and Elementor.
	 *
	 * @param WP_Post|int $post Post.
	 */
	public static function post_text( $post ) {
		$post = get_post( $post );
		if ( ! $post ) { return ''; }
		static $cache = array();
		$key = $post->ID . ':' . $post->post_modified_gmt;
		if ( isset( $cache[ $key ] ) ) { return $cache[ $key ]; }

		$text = '';
		$doc  = get_post_meta( $post->ID, '_sidsyn_document_data', true );
		if ( is_string( $doc ) && '' !== $doc ) {
			$data = json_decode( $doc, true );
			if ( is_array( $data ) ) {
				$bits = array();
				self::collect_text( $data, $bits );
				$text = implode( "\n", $bits );
			}
		}
		if ( '' === trim( $text ) ) {
			$text = $post->post_content;
		}
		$text = strip_shortcodes( $text );
		$text = self::strip_html( $text );
		$text = self::clean( $text );
		return $cache[ $key ] = $text;
	}


	/**
	 * The opening prose of a post: the first paragraph-like text, not headings or labels.
	 * Used for descriptions so they read like sentences.
	 */
	public static function lead_text( $post ) {
		$post = get_post( $post );
		$doc  = get_post_meta( $post->ID, '_sidsyn_document_data', true );
		if ( is_string( $doc ) && '' !== $doc ) {
			$data = json_decode( $doc, true );
			if ( is_array( $data ) ) {
				$paras = array();
				self::collect_paragraphs( $data, $paras );
				$joined = self::clean( implode( ' ', $paras ) );
				if ( self::len( $joined ) >= 60 ) { return $joined; }
			}
		} elseif ( preg_match_all( '#<p[^>]*>(.*?)</p>#is', strip_shortcodes( $post->post_content ), $m ) ) {
			$paras = array();
			foreach ( $m[1] as $p ) {
				$t = self::clean( self::strip_html( $p ) );
				if ( self::len( $t ) >= 40 ) { $paras[] = $t; }
				if ( self::len( implode( ' ', $paras ) ) > 400 ) { break; }
			}
			if ( $paras ) { return implode( ' ', $paras ); }
		}
		return self::post_text( $post );
	}

	private static function collect_paragraphs( $node, &$paras ) {
		if ( is_array( $node ) && isset( $node['type'], $node['settings'] ) && in_array( $node['type'], array( 'text', 'html', 'tinymce_text_editor', 'text_editor' ), true ) && is_array( $node['settings'] ) ) {
			foreach ( array( 'text', 'html', 'content' ) as $k ) {
				if ( ! empty( $node['settings'][ $k ] ) && is_string( $node['settings'][ $k ] ) ) {
					$t = self::clean( self::strip_html( $node['settings'][ $k ] ) );
					if ( self::len( $t ) >= 40 && ! preg_match( '/^\[[^\]]+\]$/', $t ) ) { $paras[] = $t; }
					break;
				}
			}
		}
		foreach ( (array) $node as $v ) {
			if ( is_array( $v ) ) { self::collect_paragraphs( $v, $paras ); }
			if ( self::len( implode( ' ', $paras ) ) > 500 ) { return; }
		}
	}

	private static function collect_text( $node, &$bits ) {
		foreach ( (array) $node as $k => $v ) {
			if ( is_array( $v ) ) {
				self::collect_text( $v, $bits );
			} elseif ( is_string( $v ) && in_array( $k, array( 'text', 'html', 'quote', 'description', 'content', 'caption', 'label' ), true ) && strlen( $v ) > 2 ) {
				$bits[] = self::strip_html( $v );
			}
		}
	}

	/** Headings found in the post, as list of array(level, text). */
	public static function headings( $post ) {
		$post = get_post( $post );
		$html = '';
		$doc  = get_post_meta( $post->ID, '_sidsyn_document_data', true );
		$out  = array();
		if ( is_string( $doc ) && '' !== $doc ) {
			$data = json_decode( $doc, true );
			if ( is_array( $data ) ) {
				self::collect_headings( $data, $out );
				return $out;
			}
		}
		if ( preg_match_all( '#<h([1-6])[^>]*>(.*?)</h\1>#is', $post->post_content, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $h ) { $out[] = array( (int) $h[1], self::clean( wp_strip_all_tags( $h[2] ) ) ); }
		}
		return $out;
	}

	private static function collect_headings( $node, &$out ) {
		if ( is_array( $node ) && isset( $node['type'], $node['settings'] ) && 'heading' === $node['type'] && is_array( $node['settings'] ) ) {
			$tag = isset( $node['settings']['tag'] ) ? $node['settings']['tag'] : 'h2';
			if ( preg_match( '/^h([1-6])$/i', (string) $tag, $m ) ) {
				$out[] = array( (int) $m[1], self::clean( wp_strip_all_tags( (string) ( $node['settings']['text'] ?? '' ) ) ) );
			}
		}
		foreach ( (array) $node as $v ) {
			if ( is_array( $v ) ) { self::collect_headings( $v, $out ); }
		}
	}

	/** All image URLs referenced in the post (HTML and Sidcraft data). */
	public static function post_images( $post ) {
		$post = get_post( $post );
		$urls = array();
		$src  = $post->post_content . ' ' . (string) get_post_meta( $post->ID, '_sidsyn_document_data', true );
		if ( preg_match_all( '#https?:\\\\?/\\\\?/[^"\'\s<>\\\\]+?\.(?:jpe?g|png|webp|gif|avif)#i', $src, $m ) ) {
			foreach ( $m[0] as $u ) { $urls[] = str_replace( '\\/', '/', $u ); }
		}
		return array_values( array_unique( $urls ) );
	}

	/** Absolute URL of the best social image for a post. */
	public static function post_image( $post ) {
		$post = get_post( $post );
		if ( $post && has_post_thumbnail( $post ) ) {
			$src = wp_get_attachment_image_src( get_post_thumbnail_id( $post ), 'full' );
			if ( $src ) { return array( 'url' => $src[0], 'w' => (int) $src[1], 'h' => (int) $src[2] ); }
		}
		if ( $post ) {
			$imgs = self::post_images( $post );
			if ( $imgs ) { return array( 'url' => $imgs[0], 'w' => 0, 'h' => 0 ); }
		}
		$default = Sidcraft_AutoSEO_Settings::get( 'default_image' );
		if ( $default ) { return array( 'url' => $default, 'w' => 0, 'h' => 0 ); }
		$icon = get_site_icon_url( 512 );
		return $icon ? array( 'url' => $icon, 'w' => 512, 'h' => 512 ) : array();
	}

	public static function stopwords() {
		return array( 'a','an','and','are','as','at','be','by','for','from','how','in','is','it','its','of','on','or','that','the','this','to','was','what','when','where','which','who','will','with','your','you','our','we','us','about','into','than','then','their','them','these','those','have','has','had','can','not','but','all','any','more','most','also','here','there','new','get' );
	}

	/** Best single focus phrase for a post, found without any human input. */
	public static function auto_focus( $post ) {
		$post  = get_post( $post );
		$title = self::lower( self::clean( $post->post_title ) );
		$words = preg_split( '/[^\p{L}\p{N}\']+/u', $title, -1, PREG_SPLIT_NO_EMPTY );
		$stop  = self::stopwords();
		$core  = array_values( array_filter( $words, function ( $w ) use ( $stop ) { return self::len( $w ) > 2 && ! in_array( $w, $stop, true ); } ) );
		if ( ! $core ) { return ''; }
		$body = self::lower( self::post_text( $post ) );
		// Prefer the 2-3 word run from the title that the body repeats most.
		$best = '';
		$bestn = -1;
		$n = count( $core );
		for ( $len = min( 3, $n ); $len >= 1; $len-- ) {
			for ( $i = 0; $i + $len <= $n; $i++ ) {
				$phrase = implode( ' ', array_slice( $core, $i, $len ) );
				$count  = substr_count( $body, $phrase );
				$score  = $count * $len;
				if ( $score > $bestn ) { $bestn = $score; $best = $phrase; }
			}
		}
		return $best;
	}

	/** Syllable-free readability estimate: average words per sentence. */
	public static function avg_sentence_words( $text ) {
		$sentences = preg_split( '/(?<=[.!?])\s+/u', trim( $text ), -1, PREG_SPLIT_NO_EMPTY );
		if ( ! $sentences ) { return 0; }
		$words = str_word_count( $text );
		return $words / max( 1, count( $sentences ) );
	}

	public static function word_count( $text ) {
		return count( preg_split( '/\s+/u', trim( $text ), -1, PREG_SPLIT_NO_EMPTY ) );
	}
}
