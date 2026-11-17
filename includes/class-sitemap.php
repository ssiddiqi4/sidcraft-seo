<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Improves the sitemap WordPress already builds (/wp-sitemap.xml): last-modified dates, no noindex URLs, and friendly aliases. */
class Sidcraft_AutoSEO_Sitemap {

	public static function init() {
		if ( ! Sidcraft_AutoSEO_Settings::on( 'sitemap' ) ) { return; }
		add_filter( 'wp_sitemaps_enabled', '__return_true' );
		add_filter( 'wp_sitemaps_posts_entry', array( __CLASS__, 'entry' ), 10, 2 );
		add_filter( 'wp_sitemaps_posts_query_args', array( __CLASS__, 'query' ), 10, 2 );
		add_filter( 'wp_sitemaps_add_provider', array( __CLASS__, 'provider' ), 10, 2 );
		add_filter( 'wp_sitemaps_taxonomies', array( __CLASS__, 'taxonomies' ) );
		add_filter( 'wp_sitemaps_taxonomies_query_args', array( __CLASS__, 'term_query' ) );
		add_action( 'template_redirect', array( __CLASS__, 'aliases' ), 1 );
		add_filter( 'robots_txt', array( __CLASS__, 'robots' ), 20, 2 );
	}

	public static function entry( $entry, $post ) {
		$entry['lastmod'] = get_post_modified_time( 'c', true, $post );
		return $entry;
	}

	public static function query( $args, $type ) {
		$args['meta_query']   = isset( $args['meta_query'] ) ? $args['meta_query'] : array();
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- hides noindex pages from the sitemap.
		$args['meta_query'][] = array( 'key' => '_sidcraft_autoseo_noindex', 'compare' => 'NOT EXISTS' );
		// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		return $args;
	}

	public static function provider( $provider, $name ) {
		// Hide the "users" sitemap on single-author sites: those pages are thin and redirected.
		if ( 'users' === $name && Sidcraft_AutoSEO_Settings::on( 'redirect_author' ) && (int) count_users()['total_users'] <= 1 ) { return false; }
		return $provider;
	}

	public static function taxonomies( $taxes ) {
		unset( $taxes['post_format'] );
		return $taxes;
	}

	public static function term_query( $args ) {
		$args['hide_empty'] = true;
		return $args;
	}

	public static function aliases() {
		$path = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH ) : '';
		$home = rtrim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
		$rel  = '/' . ltrim( substr( (string) $path, strlen( $home ) ), '/' );
		if ( in_array( $rel, array( '/sitemap.xml', '/sitemap_index.xml', '/sitemap-index.xml' ), true ) ) {
			wp_safe_redirect( home_url( '/wp-sitemap.xml' ), 301 );
			exit;
		}
	}

	public static function robots( $output, $public ) {
		if ( ! $public ) { return $output; }
		if ( false === stripos( $output, 'Sitemap:' ) ) {
			$output .= "\nSitemap: " . home_url( '/wp-sitemap.xml' ) . "\n";
		}
		return $output;
	}
}
