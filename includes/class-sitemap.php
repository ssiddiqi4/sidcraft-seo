<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Improves the sitemap WordPress already builds: last-modified dates, no noindex URLs, and friendly aliases. */
class Sidcraft_RankBoost_Sitemap {

	public static function init() {
		if ( ! Sidcraft_RankBoost_Settings::on( 'sitemap' ) ) { return; }
		// Never override WordPress or another plugin's decision to switch sitemaps off
		// (for example when Settings > Reading > "Discourage search engines" is on).
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
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- hides noindex pages from the sitemap.
		$args['meta_query']   = isset( $args['meta_query'] ) ? $args['meta_query'] : array();
		$args['meta_query'][] = array( 'key' => '_sidcraft_rankboost_noindex', 'compare' => 'NOT EXISTS' );
		// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		return $args;
	}

	public static function provider( $provider, $name ) {
		// Hide the "users" sitemap on single-author sites: those pages are thin and redirected.
		if ( 'users' === $name && Sidcraft_RankBoost_Settings::on( 'redirect_author' ) && (int) count_users()['total_users'] <= 1 ) { return false; }
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

	/** The URL WordPress itself uses for the sitemap index, so subdirectory and plain-permalink installs work. */
	public static function index_url() {
		if ( function_exists( 'wp_sitemaps_enabled' ) && ! wp_sitemaps_enabled() ) { return ''; }
		$url = function_exists( 'get_sitemap_url' ) ? get_sitemap_url( 'index' ) : '';
		return $url ? $url : '';
	}

	public static function aliases() {
		$path = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH ) : '';
		$home = rtrim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
		$rel  = '/' . ltrim( substr( (string) $path, strlen( $home ) ), '/' );
		if ( in_array( $rel, array( '/sitemap.xml', '/sitemap_index.xml', '/sitemap-index.xml' ), true ) ) {
			$index = self::index_url();
			if ( $index ) {
				wp_safe_redirect( $index, 301 );
				exit;
			}
		}
	}

	public static function robots( $output, $public ) {
		if ( ! $public ) { return $output; }
		$index = self::index_url();
		if ( $index && false === stripos( $output, 'Sitemap:' ) ) {
			$output .= "\nSitemap: " . $index . "\n";
		}
		return $output;
	}
}
