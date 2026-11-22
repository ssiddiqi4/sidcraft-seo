<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Settings and small helpers for the Speed features (page cache, minify, database clean-up, debugging). Everything here is opt-in. */
class Sidcraft_RankBoost_Speed {

	const OPTION = 'sidcraft_rankboost_speed';

	public static function defaults() {
		return array(
			'cache'          => 0,
			'cache_hours'    => 24,
			'cache_exclude'  => '',
			'cache_preload'  => 1,
			'min_html'       => 0,
			'min_css'        => 0,
			'min_js'         => 0,
			'min_exclude'    => '',
			'db_keep_rev'    => 5,
			'db_days'        => 0,
			'cache_days'     => 0,
			'profile'        => 0,
			'slow_ms'        => 2000,
			'no_emoji'       => 0,
			'no_embeds'      => 0,
			'no_dashicons'   => 0,
			'heartbeat'      => 'default',
			'revisions_max'  => 0,
		);
	}

	public static function all() {
		$o = get_option( self::OPTION, array() );
		if ( is_array( $o ) && isset( $o['db_auto'] ) && ! isset( $o['db_days'] ) ) { $o['db_days'] = 'weekly' === $o['db_auto'] ? 7 : ( 'monthly' === $o['db_auto'] ? 30 : 0 ); }
		return wp_parse_args( is_array( $o ) ? $o : array(), self::defaults() );
	}

	/** Which settings each screen's form owns, so saving one screen never resets another. */
	public static function groups() {
		return array(
			'cache' => array( 'cache', 'cache_hours', 'cache_days', 'cache_exclude', 'cache_preload', 'min_html', 'min_css', 'min_js', 'min_exclude', 'no_emoji', 'no_embeds', 'no_dashicons', 'heartbeat', 'revisions_max' ),
			'db'    => array( 'db_keep_rev', 'db_days' ),
			'debug' => array( 'profile', 'slow_ms' ),
		);
	}

	public static function save( $in, $group ) {
		$g = self::groups(); if ( ! isset( $g[ $group ] ) ) { return; }
		$o = get_option( self::OPTION, array() ); $o = wp_parse_args( is_array( $o ) ? $o : array(), self::defaults() );
		foreach ( $g[ $group ] as $k ) {
			$v = isset( $in[ $k ] ) ? $in[ $k ] : null;
			switch ( $k ) {
				case 'cache_hours':   $o[ $k ] = max( 1, min( 720, (int) $v ) ); break;
				case 'db_keep_rev':   $o[ $k ] = max( 0, min( 100, (int) $v ) ); break;
				case 'slow_ms':       $o[ $k ] = max( 200, min( 60000, (int) $v ) ); break;
				case 'revisions_max': $o[ $k ] = max( 0, min( 100, (int) $v ) ); break;
				case 'db_days':
				case 'cache_days':    $o[ $k ] = max( 0, min( 180, (int) $v ) ); break;
				case 'heartbeat':     $o[ $k ] = in_array( $v, array( 'default', 'slow', 'off' ), true ) ? $v : 'default'; break;
				case 'cache_exclude':
				case 'min_exclude':   $o[ $k ] = implode( "\n", array_slice( self::lines( is_string( $v ) ? sanitize_textarea_field( $v ) : '' ), 0, 100 ) ); break;
				default:              $o[ $k ] = empty( $v ) ? 0 : 1;
			}
		}
		update_option( self::OPTION, $o, true );
		Sidcraft_RankBoost_Cache::clear_all(); Sidcraft_RankBoost_Minify::clear(); self::reschedule();
	}

	/** Timestamp of 12:01 AM in the site's time zone, $days days after the day of $from (0 = the next 12:01 AM). */
	public static function at_0001( $days = 0, $from = null ) {
		$tz = wp_timezone(); $now = new DateTimeImmutable( '@' . ( null === $from ? time() : (int) $from ) ); $now = $now->setTimezone( $tz );
		$d = $now->setTime( 0, 1, 0 );
		if ( $days > 0 ) { return $d->modify( '+' . (int) $days . ' days' )->getTimestamp(); }
		return ( $d->getTimestamp() > time() ? $d : $d->modify( '+1 day' ) )->getTimestamp();
	}

	/** (Re)plan the two maintenance jobs from the saved day counts. */
	public static function reschedule() {
		$o = self::all();
		foreach ( array( Sidcraft_RankBoost_DbClean::HOOK => (int) $o['db_days'], Sidcraft_RankBoost_Cache::CLEAR_HOOK => (int) $o['cache_days'] ) as $hook => $days ) {
			wp_clear_scheduled_hook( $hook );
			if ( $days > 0 ) { wp_schedule_single_event( self::at_0001( 0 ), $hook ); }
		}
	}

	/** After a job ran, plan the next one N days on at 12:01 AM. */
	public static function plan_next( $hook, $days ) {
		wp_clear_scheduled_hook( $hook );
		if ( $days > 0 ) { wp_schedule_single_event( self::at_0001( $days ), $hook ); }
	}

	/** Safety net: if a job is enabled but nothing is planned (for example after a restore), plan it. */
	public static function heal() {
		$o = self::all();
		foreach ( array( Sidcraft_RankBoost_DbClean::HOOK => (int) $o['db_days'], Sidcraft_RankBoost_Cache::CLEAR_HOOK => (int) $o['cache_days'] ) as $hook => $days ) {
			if ( $days > 0 && ! wp_next_scheduled( $hook ) ) { wp_schedule_single_event( self::at_0001( 0 ), $hook ); }
		}
	}

	/** Options for a "every N days" drop-down. */
	public static function day_options() {
		$o = array( 0 => __( 'Never', 'sidcraft-rankboost' ), 1 => __( 'Every day at 12:01 AM', 'sidcraft-rankboost' ) );
		for ( $i = 2; $i <= 180; $i++ ) { /* translators: %d: number of days */ $o[ $i ] = sprintf( __( 'Every %d days at 12:01 AM', 'sidcraft-rankboost' ), $i ); }
		return $o;
	}

	/** Non-empty trimmed lines of a text box. */
	public static function lines( $text ) {
		$out = array();
		foreach ( preg_split( '/\R/', (string) $text ) as $l ) { $l = trim( $l ); if ( '' !== $l ) { $out[] = $l; } }
		return $out;
	}

	public static function init() {
		$o = self::all();
		add_action( 'init', array( __CLASS__, 'heal' ), 30 );
		if ( $o['no_emoji'] ) {
			remove_action( 'wp_head', 'print_emoji_detection_script', 7 ); remove_action( 'wp_print_styles', 'print_emoji_styles' );
			remove_action( 'admin_print_scripts', 'print_emoji_detection_script' ); remove_action( 'admin_print_styles', 'print_emoji_styles' );
			remove_filter( 'the_content_feed', 'wp_staticize_emoji' ); remove_filter( 'comment_text_rss', 'wp_staticize_emoji' ); remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
			add_filter( 'emoji_svg_url', '__return_false' );
		}
		if ( $o['no_embeds'] ) {
			add_action( 'wp_footer', function () { wp_dequeue_script( 'wp-embed' ); } );
			remove_action( 'wp_head', 'wp_oembed_add_discovery_links' ); remove_action( 'wp_head', 'wp_oembed_add_host_js' );
		}
		if ( $o['no_dashicons'] ) {
			add_action( 'wp_enqueue_scripts', function () { if ( ! is_user_logged_in() ) { wp_dequeue_style( 'dashicons' ); wp_deregister_style( 'dashicons' ); } }, 100 );
		}
		if ( 'off' === $o['heartbeat'] ) { add_action( 'init', function () { if ( ! is_admin() || ! self::editing_screen() ) { wp_deregister_script( 'heartbeat' ); } }, 1 ); }
		if ( 'slow' === $o['heartbeat'] ) { add_filter( 'heartbeat_settings', function ( $s ) { $s['interval'] = 120; return $s; } ); }
		if ( $o['revisions_max'] > 0 ) { add_filter( 'wp_revisions_to_keep', function () { return (int) self::all()['revisions_max']; } ); }
	}

	/** True on the post editing screens, where the heartbeat keeps autosave and post locking working. */
	private static function editing_screen() {
		global $pagenow;
		return in_array( $pagenow, array( 'post.php', 'post-new.php' ), true );
	}

	/** Delete a folder and everything in it. Returns the number of files removed. */
	public static function rm_dir( $dir ) {
		$n = 0; $dir = untrailingslashit( $dir );
		if ( ! is_dir( $dir ) ) { return 0; }
		foreach ( scandir( $dir ) as $f ) {
			if ( '.' === $f || '..' === $f ) { continue; }
			$p = $dir . '/' . $f;
			if ( is_dir( $p ) && ! is_link( $p ) ) { $n += self::rm_dir( $p ); } else { wp_delete_file( $p ); $n++; }
		}
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) { require_once ABSPATH . 'wp-admin/includes/file.php'; WP_Filesystem(); }
		if ( $wp_filesystem ) { $wp_filesystem->rmdir( $dir ); }
		return $n;
	}

	public static function size( $bytes ) { return size_format( max( 0, (int) $bytes ), 1 ); }

	/** Total size and number of files in a folder. */
	public static function dir_stats( $dir ) {
		$n = 0; $b = 0;
		if ( ! is_dir( $dir ) ) { return array( 0, 0 ); }
		foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) ) as $f ) { if ( $f->isFile() && 'index.php' !== $f->getFilename() ) { $n++; $b += $f->getSize(); } }
		return array( $n, $b );
	}
}
