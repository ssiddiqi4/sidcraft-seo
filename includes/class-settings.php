<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** One option array with safe defaults. Everything is on by default so nothing needs configuring. */
class Sidcraft_AutoSEO_Settings {

	const OPTION = 'sidcraft_autoseo_settings';

	public static function defaults() {
		return array(
			'title_sep'          => '|',
			'title_suffix'       => 1,     // add " | Site name" when it fits in 60 characters.
			'default_image'      => '',
			'social_cards'       => 1,
			'schema'             => 1,
			'sitemap'            => 1,
			'auto_redirects'     => 1,     // 301 when a slug changes, and fix near-miss 404s.
			'fuzzy_404'          => 1,
			'auto_alt'           => 1,
			'related_links'      => 1,     // posts with no internal links get a short "You may also like" list.
			'related_pages'      => 0,     // also add the list to pages that link nowhere (switched on by the one-click fix).
			'related_style'      => 'collapsed', // collapsed (folded, click to open) or open.
			'check_external'     => 0,     // broken-link scan also checks links to other websites (sends their addresses a request).
			'auto_excerpt'       => 1,     // write a short excerpt when a post has none.
			'indexnow'           => 1,
			'hygiene'            => 1,     // remove generator tags, attachment pages, thin archives.
			'noindex_thin_tags'  => 1,
			'redirect_author'    => 1,     // single-author sites: author archive -> home.
			'daily_audit'        => 1,
			'audit_limit'        => 500,   // how many of the newest posts are audited and scanned (all pages are included). 0 = every post.
			'remove_data'        => 0,
			'twitter_site'       => '',
		);
	}

	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_seed' ) );
		add_action( 'update_option_' . self::OPTION, function () { delete_transient( 'sidcraft_autoseo_scope' ); delete_transient( 'sidcraft_autoseo_summary' ); } );
	}

	public static function maybe_seed() {
		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, self::defaults(), '', false );
		}
	}

	public static function all() {
		$saved = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
	}

	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	public static function on( $key ) {
		return (bool) self::get( $key );
	}

	public static function sanitize( $in ) {
		$d   = self::defaults();
		$out = array();
		foreach ( $d as $k => $def ) {
			if ( 'related_style' === $k ) {
				$out[ $k ] = ( isset( $in[ $k ] ) && 'open' === $in[ $k ] ) ? 'open' : 'collapsed';
			} elseif ( in_array( $k, array( 'title_sep', 'twitter_site' ), true ) ) {
				$out[ $k ] = isset( $in[ $k ] ) ? sanitize_text_field( $in[ $k ] ) : $def;
			} elseif ( 'audit_limit' === $k ) {
				$out[ $k ] = isset( $in[ $k ] ) ? max( 0, min( 1000000, (int) $in[ $k ] ) ) : (int) $def;
			} elseif ( 'default_image' === $k ) {
				$out[ $k ] = isset( $in[ $k ] ) ? esc_url_raw( $in[ $k ] ) : '';
			} else {
				$out[ $k ] = empty( $in[ $k ] ) ? 0 : 1;
			}
		}
		return $out;
	}
}
