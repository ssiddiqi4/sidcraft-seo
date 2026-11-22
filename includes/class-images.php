<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Alt text for every image, written automatically. */
class Sidcraft_RankBoost_Images {

	public static function init() {
		if ( ! Sidcraft_RankBoost_Settings::on( 'auto_alt' ) ) { return; }
		add_action( 'add_attachment', array( __CLASS__, 'on_upload' ) );
		add_filter( 'wp_get_attachment_image_attributes', array( __CLASS__, 'attrs' ), 20, 2 );
		add_filter( 'the_content', array( __CLASS__, 'content' ), 99 );
	}

	/** "my-trip_to-boston-1024x768.jpg" -> "My trip to Boston". Camera-roll names fall back to $fallback. */
	public static function alt_from_name( $url_or_name, $fallback = '' ) {
		$base = pathinfo( wp_parse_url( (string) $url_or_name, PHP_URL_PATH ) ?: (string) $url_or_name, PATHINFO_FILENAME );
		$base = preg_replace( '/-\d{2,4}x\d{2,4}$/', '', $base );
		$base = preg_replace( '/-(scaled|e\d{10,})$/', '', $base );
		$base = trim( preg_replace( '/[\-_\.\s]+/', ' ', $base ) );
		if ( '' === $base || preg_match( '/^(img|dsc|dscn|pxl|image|photo|screenshot|whatsapp|picture|pic|untitled|capture|scan|mvimg)[\s\d]*/i', $base ) || preg_match( '/^[\d\s]+$/', $base ) || strlen( $base ) < 3 ) {
			return Sidcraft_RankBoost_Util::clean( $fallback );
		}
		return ucfirst( Sidcraft_RankBoost_Util::clean( $base ) );
	}

	public static function on_upload( $id ) {
		if ( ! wp_attachment_is_image( $id ) || '' !== trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ) ) { return; }
		$post = get_post( $id );
		$parent_title = $post && $post->post_parent ? get_the_title( $post->post_parent ) : '';
		$alt = self::alt_from_name( get_attached_file( $id ), $parent_title ?: ( $post ? $post->post_title : '' ) );
		if ( '' !== $alt ) { update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( $alt ) ); }
	}

	public static function attrs( $attr, $attachment ) {
		if ( isset( $attr['alt'] ) && '' !== trim( $attr['alt'] ) ) { return $attr; }
		$parent = $attachment->post_parent ? get_the_title( $attachment->post_parent ) : '';
		$alt = self::alt_from_name( $attachment->guid, $attachment->post_title ?: $parent );
		if ( '' !== $alt ) { $attr['alt'] = $alt; }
		return $attr;
	}

	/** Add alt to <img> tags in post content that have none. Decorative images (empty alt with role/aria-hidden) are left alone. */
	public static function content( $html ) {
		if ( false === stripos( $html, '<img' ) ) { return $html; }
		$post_title = is_singular() ? get_the_title() : '';
		return preg_replace_callback( '/<img\b[^>]*>/i', function ( $m ) use ( $post_title ) {
			$tag = $m[0];
			if ( preg_match( '/\brole\s*=\s*["\']presentation["\']|aria-hidden\s*=\s*["\']true["\']/i', $tag ) ) { return $tag; }
			$has_alt = false;
			if ( preg_match( '/\balt\s*=\s*"([^"]*)"/i', $tag, $a ) || preg_match( "/\balt\s*=\s*'([^']*)'/i", $tag, $a ) ) {
				if ( '' !== trim( $a[1] ) ) { return $tag; }
				$has_alt = true;
			}
			$src = preg_match( '/\bsrc\s*=\s*["\']([^"\']+)["\']/i', $tag, $s ) ? $s[1] : '';
			$alt = self::alt_from_name( $src, $post_title );
			if ( '' === $alt ) { return $tag; }   // nothing sensible to say: leave the tag exactly as it was.
			if ( $has_alt ) { $tag = preg_replace( '/\balt\s*=\s*(?:"[^"]*"|\'[^\']*\')/i', '', $tag, 1 ); }
			return preg_replace( '/<img\b/i', '<img alt="' . esc_attr( $alt ) . '"', $tag, 1 );
		}, $html );
	}

	/** Fix the media library in batches. Returns how many images were fixed. */
	public static function fix_library( $limit = 100 ) {
		$ids = get_posts( array(
			'post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => 'image', 'posts_per_page' => (int) $limit, 'fields' => 'ids', 'no_found_rows' => true,
			'meta_query' => // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- finds images without alt text.
			 array( 'relation' => 'OR', array( 'key' => '_wp_attachment_image_alt', 'compare' => 'NOT EXISTS' ), array( 'key' => '_wp_attachment_image_alt', 'value' => '' ) ),
		) );
		$n = 0;
		foreach ( $ids as $id ) {
			$before = (string) get_post_meta( $id, '_wp_attachment_image_alt', true );
			self::on_upload( $id );
			if ( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) !== $before ) { $n++; }
		}
		return $n;
	}
}
