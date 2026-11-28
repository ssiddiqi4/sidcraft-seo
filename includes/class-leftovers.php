<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.WP.AlternativeFunctions -- finding and removing the leftovers of deleted plugins works on WordPress's own tables; table names come from the database, every value is prepared, and a copy is saved first.

/**
 * Finds what plugins that are no longer installed left in the database, and removes it on request.
 *
 * Only well-known plugins are recognised (see signatures()), and only when NONE of their plugin folders is present,
 * active or not. Posts, pages and post meta are never touched, because that is your content. Before anything is
 * deleted, a copy of the removed tables and option rows is saved as an SQL file.
 */
class Sidcraft_RankBoost_Leftovers {

	const CACHE = 'sidcraft_rankboost_leftovers';

	/**
	 * Known plugins: id => name, owner folder patterns (if any of these is installed the plugin is NOT treated as gone),
	 * option-name prefixes, table-name prefixes (after the site prefix), scheduled-task prefixes.
	 */
	public static function signatures() {
		return array(
			'yoast'       => array( 'Yoast SEO', array( 'wordpress-seo', 'wordpress-seo-*', 'wp-seo-*' ), array( 'wpseo', 'yoast_', 'yoast-' ), array( 'yoast_' ), array( 'wpseo', 'yoast' ) ),
			'rankmath'    => array( 'Rank Math SEO', array( 'seo-by-rank-math*', 'rank-math*' ), array( 'rank_math', 'rank-math' ), array( 'rank_math_' ), array( 'rank_math' ) ),
			'aioseo'      => array( 'All in One SEO', array( 'all-in-one-seo-pack*' ), array( 'aioseo', 'aioseop' ), array( 'aioseo_' ), array( 'aioseo' ) ),
			'woocommerce' => array( 'WooCommerce', array( 'woocommerce', 'woocommerce-*', 'wc-*' ), array( 'woocommerce_', 'wc_' ), array( 'woocommerce_', 'wc_' ), array( 'woocommerce_', 'wc_' ) ),
			'elementor'   => array( 'Elementor', array( 'elementor', 'elementor-*' ), array( 'elementor_', 'elementor-' ), array(), array( 'elementor' ) ),
			'jetpack'     => array( 'Jetpack', array( 'jetpack', 'jetpack-*' ), array( 'jetpack_', 'jetpack-' ), array( 'jetpack_' ), array( 'jetpack' ) ),
			'w3tc'        => array( 'W3 Total Cache', array( 'w3-total-cache*' ), array( 'w3tc' ), array(), array( 'w3tc' ) ),
			'litespeed'   => array( 'LiteSpeed Cache', array( 'litespeed-cache*' ), array( 'litespeed.' ), array( 'litespeed_' ), array( 'litespeed' ) ),
			'wordfence'   => array( 'Wordfence', array( 'wordfence*' ), array( 'wordfence_', 'wf_' ), array( 'wf' ), array( 'wordfence' ) ),
			'wpforms'     => array( 'WPForms', array( 'wpforms*' ), array( 'wpforms' ), array( 'wpforms_' ), array( 'wpforms' ) ),
			'cf7'         => array( 'Contact Form 7', array( 'contact-form-7*' ), array( 'wpcf7' ), array(), array() ),
			'akismet'     => array( 'Akismet', array( 'akismet*' ), array( 'akismet_' ), array(), array( 'akismet' ) ),
			'wpmailsmtp'  => array( 'WP Mail SMTP', array( 'wp-mail-smtp*' ), array( 'wp_mail_smtp', 'wp-mail-smtp' ), array( 'wpmailsmtp_' ), array( 'wp_mail_smtp' ) ),
			'redirection' => array( 'Redirection', array( 'redirection*' ), array( 'redirection_' ), array( 'redirection_' ), array( 'redirection' ) ),
			'updraft'     => array( 'UpdraftPlus', array( 'updraftplus*' ), array( 'updraft_', 'updraftplus' ), array(), array( 'updraft' ) ),
			'smush'       => array( 'Smush', array( 'wp-smush*' ), array( 'wp-smush', 'wp_smush', 'dir_smush' ), array( 'smush_dir_images' ), array( 'wp_smush' ) ),
			'wpml'        => array( 'WPML', array( 'sitepress-multilingual-cms*', 'wpml-*', 'wp-seo-multilingual*' ), array( 'icl_', 'wpml' ), array( 'icl_' ), array( 'wpml' ) ),
			'polylang'    => array( 'Polylang', array( 'polylang*' ), array( 'polylang' ), array(), array( 'polylang' ) ),
			'mc4wp'       => array( 'Mailchimp for WordPress', array( 'mailchimp-for-wp*' ), array( 'mc4wp' ), array(), array( 'mc4wp' ) ),
			'mailpoet'    => array( 'MailPoet', array( 'mailpoet*' ), array( 'mailpoet' ), array( 'mailpoet_' ), array( 'mailpoet' ) ),
			'nextgen'     => array( 'NextGEN Gallery', array( 'nextgen-gallery*' ), array( 'ngg_', 'ngg-' ), array( 'ngg_' ), array() ),
			'revslider'   => array( 'Slider Revolution', array( 'revslider*', 'slider-revolution*' ), array( 'revslider' ), array( 'revslider_' ), array( 'revslider' ) ),
			'ninjaforms'  => array( 'Ninja Forms', array( 'ninja-forms*' ), array( 'ninja_forms', 'nf_' ), array( 'nf3_' ), array( 'ninja_forms' ) ),
			'gravity'     => array( 'Gravity Forms', array( 'gravityforms*' ), array( 'gravityforms', 'rg_' ), array( 'gf_', 'rg_' ), array( 'gravityforms' ) ),
			'edd'         => array( 'Easy Digital Downloads', array( 'easy-digital-downloads*' ), array( 'edd_' ), array( 'edd_' ), array( 'edd_' ) ),
			'tablepress'  => array( 'TablePress', array( 'tablepress*' ), array( 'tablepress' ), array(), array() ),
			'wpfc'        => array( 'WP Fastest Cache', array( 'wp-fastest-cache*' ), array( 'WpFastestCache' ), array(), array() ),
			'rsssl'       => array( 'Really Simple SSL', array( 'really-simple-ssl*' ), array( 'rsssl_', 'rlrsssl' ), array(), array( 'rsssl' ) ),
			'itsec'       => array( 'Solid Security (iThemes Security)', array( 'ithemes-security*', 'solid-security*' ), array( 'itsec' ), array( 'itsec_' ), array( 'itsec' ) ),
			'aiowps'      => array( 'All In One WP Security', array( 'all-in-one-wp-security*' ), array( 'aio_wp_security', 'aiowps' ), array( 'aiowps_' ), array( 'aiowps' ) ),
			'buddypress'  => array( 'BuddyPress', array( 'buddypress*', 'bp-*' ), array( 'bp-', 'bp_' ), array( 'bp_' ), array( 'bp_' ) ),
			'bbpress'     => array( 'bbPress', array( 'bbpress*' ), array( '_bbp_' ), array(), array() ),
			'duplicate'   => array( 'Yoast Duplicate Post', array( 'duplicate-post*' ), array( 'duplicate_post' ), array(), array() ),
			'classicedit' => array( 'Classic Editor', array( 'classic-editor*' ), array( 'classic-editor-' ), array(), array() ),
			'sitekit'     => array( 'Site Kit by Google', array( 'google-site-kit*' ), array( 'googlesitekit' ), array(), array( 'googlesitekit' ) ),
			'monsterins'  => array( 'MonsterInsights', array( 'google-analytics-for-wordpress*', 'google-analytics-premium*', 'monsterinsights*' ), array( 'monsterinsights' ), array(), array( 'monsterinsights' ) ),
			'wpoptimize'  => array( 'WP-Optimize', array( 'wp-optimize*' ), array( 'wpo_', 'wp-optimize' ), array( 'wpo_' ), array( 'wpo_', 'wp_optimize' ) ),
			'autoptimize' => array( 'Autoptimize', array( 'autoptimize*' ), array( 'autoptimize_' ), array(), array( 'ao_' ) ),
			'wprocket'    => array( 'WP Rocket', array( 'wp-rocket*' ), array( 'wp_rocket', 'rocket_' ), array( 'wpr_' ), array( 'rocket_' ) ),
			'complianz'   => array( 'Complianz', array( 'complianz*' ), array( 'cmplz', 'complianz' ), array( 'cmplz_' ), array( 'cmplz' ) ),
			'sucuri'      => array( 'Sucuri Security', array( 'sucuri-scanner*' ), array( 'sucuriscan' ), array(), array( 'sucuriscan' ) ),
			'translatepr' => array( 'TranslatePress', array( 'translatepress*' ), array( 'trp_' ), array( 'trp_' ), array() ),
			'tec'         => array( 'The Events Calendar', array( 'the-events-calendar*', 'events-calendar-pro*' ), array( 'tribe_', 'tribe-', 'tec_' ), array( 'tec_' ), array( 'tribe', 'tec_' ) ),
			'learndash'   => array( 'LearnDash', array( 'sfwd-lms*', 'learndash*' ), array( 'learndash' ), array( 'learndash_' ), array( 'learndash' ) ),
			'um'          => array( 'Ultimate Member', array( 'ultimate-member*' ), array( 'um_' ), array( 'um_' ), array( 'um_' ) ),
			'formidable'  => array( 'Formidable Forms', array( 'formidable*' ), array( 'frm_', 'frmpro' ), array( 'frm_' ), array( 'frm' ) ),
			'wpstats'     => array( 'WP Statistics', array( 'wp-statistics*' ), array( 'wp_statistics' ), array( 'statistics_' ), array( 'wp_statistics' ) ),
			'blc'         => array( 'Broken Link Checker', array( 'broken-link-checker*' ), array( 'wsblc', 'blc_' ), array( 'blc_' ), array( 'blc_' ) ),
			'shortpixel'  => array( 'ShortPixel Image Optimizer', array( 'shortpixel-image-optimiser*' ), array( 'wp-short-pixel', 'shortpixel' ), array( 'shortpixel_' ), array( 'shortpixel' ) ),
			'loginizer'   => array( 'Loginizer', array( 'loginizer*' ), array( 'loginizer' ), array( 'loginizer_' ), array( 'loginizer' ) ),
		);
	}

	/** Is this table WordPress core or one of Search Boost Suite's own? $suffix is the name after the site prefix. */
	private static function is_core_table( $suffix ) {
		$suffix = preg_replace( '/^\d+_/', '', $suffix ); // Multisite: "2_posts".
		if ( in_array( $suffix, array( 'posts', 'postmeta', 'comments', 'commentmeta', 'terms', 'termmeta', 'term_taxonomy', 'term_relationships', 'options', 'links', 'users', 'usermeta', 'blogs', 'blogmeta', 'site', 'sitemeta', 'signups', 'registration_log', 'blog_versions' ), true ) ) { return true; }
		return 0 === strpos( $suffix, 'sidcraft_' ) || 0 === strpos( $suffix, 'svs_' );
	}

	/** Folder names of everything installed (active or not), lower case. */
	public static function installed() {
		if ( ! function_exists( 'get_plugins' ) ) { require_once ABSPATH . 'wp-admin/includes/plugin.php'; }
		$out = array();
		foreach ( array_keys( get_plugins() ) as $f ) { $out[] = strtolower( false !== strpos( $f, '/' ) ? dirname( $f ) : basename( $f, '.php' ) ); }
		foreach ( array_keys( get_mu_plugins() ) as $f ) { $out[] = strtolower( basename( $f, '.php' ) ); }
		return array_values( array_unique( $out ) );
	}

	/** Is any of the plugin's folders installed? */
	private static function owned( array $patterns, array $installed ) {
		foreach ( $patterns as $p ) {
			foreach ( $installed as $i ) { if ( fnmatch( $p, $i ) ) { return true; } }
		}
		return false;
	}

	private static function starts( $name, array $prefixes ) {
		foreach ( $prefixes as $p ) { if ( '' !== $p && 0 === stripos( $name, $p ) ) { return true; } }
		return false;
	}

	/** Strip the transient wrappers. Returns array( base name, is transient ). */
	private static function unwrap( $name ) {
		foreach ( array( '_site_transient_timeout_', '_transient_timeout_', '_site_transient_', '_transient_' ) as $w ) {
			if ( 0 === strpos( $name, $w ) ) { return array( substr( $name, strlen( $w ) ), true ); }
		}
		return array( $name, false );
	}

	/** Scan the database. Returns the plugins that are gone but left data, and tables nobody recognises. */
	public static function scan() {
		global $wpdb;
		$installed = self::installed(); $sigs = self::signatures(); $gone = array();
		foreach ( $sigs as $id => $s ) { if ( ! self::owned( $s[1], $installed ) ) { $gone[ $id ] = $s; } }
		$res = array( 'time' => time(), 'plugins' => array(), 'unknown' => array(), 'known' => count( $sigs ) );

		// Options and transients: one pass over the names.
		$bucket = array();
		if ( $gone ) {
			$rows = $wpdb->get_results( "SELECT option_name AS n, LENGTH(option_value) AS b FROM {$wpdb->options} LIMIT 200000", ARRAY_A );
			foreach ( (array) $rows as $r ) {
				list( $base, $tr ) = self::unwrap( $r['n'] );
				foreach ( $gone as $id => $s ) {
					if ( ! self::starts( $base, $s[2] ) ) { continue; }
					if ( ! isset( $bucket[ $id ] ) ) { $bucket[ $id ] = array( 'o' => 0, 'ob' => 0, 't' => 0, 'sample' => array() ); }
					if ( $tr ) { $bucket[ $id ]['t']++; } else { $bucket[ $id ]['o']++; $bucket[ $id ]['ob'] += (int) $r['b']; if ( count( $bucket[ $id ]['sample'] ) < 5 ) { $bucket[ $id ]['sample'][] = $r['n']; } }
					break;
				}
			}
		}
		// Scheduled tasks.
		$cron = array();
		$arr  = _get_cron_array();
		if ( is_array( $arr ) && $gone ) {
			foreach ( $arr as $events ) {
				foreach ( (array) $events as $hook => $e ) {
					foreach ( $gone as $id => $s ) { if ( self::starts( $hook, $s[4] ) ) { $cron[ $id ][ $hook ] = true; break; } }
				}
			}
		}
		// Tables.
		$tbl = array(); $unknown = array();
		$sizes = array();
		foreach ( Sidcraft_RankBoost_DbClean::tables() as $r ) { $sizes[ $r['name'] ] = (int) $r['data'] + (int) $r['idx']; }
		foreach ( Sidcraft_RankBoost_DbBackup::tables() as $t ) {
			$suffix = substr( $t, strlen( $wpdb->prefix ) );
			if ( self::is_core_table( $suffix ) ) { continue; }
			$suffix = preg_replace( '/^\d+_/', '', $suffix );
			$owner = '';
			foreach ( $sigs as $id => $s ) { if ( self::starts( $suffix, $s[3] ) ) { $owner = $id; break; } }
			$row = array( 'name' => $t, 'rows' => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $t ) ), 'bytes' => isset( $sizes[ $t ] ) ? $sizes[ $t ] : 0 );
			if ( '' !== $owner ) {
				if ( isset( $gone[ $owner ] ) ) { $tbl[ $owner ][] = $row; }
				continue; // Belongs to a plugin that is installed.
			}
			// Not a known plugin: if the name starts with an installed plugin's folder name it is theirs, otherwise report it as unrecognised.
			$mine = false;
			foreach ( $installed as $i ) {
				$norm = str_replace( '-', '_', $i ); $first = strtok( $norm, '_' );
				if ( 0 === strpos( $suffix, $norm ) || ( strlen( $first ) >= 4 && 0 === strpos( $suffix, $first ) ) ) { $mine = true; break; }
			}
			if ( ! $mine ) { $unknown[] = $row; }
		}
		foreach ( $gone as $id => $s ) {
			if ( ! isset( $bucket[ $id ] ) && empty( $cron[ $id ] ) && empty( $tbl[ $id ] ) ) { continue; }
			$b = isset( $bucket[ $id ] ) ? $bucket[ $id ] : array( 'o' => 0, 'ob' => 0, 't' => 0, 'sample' => array() );
			$res['plugins'][ $id ] = array( 'name' => $s[0], 'options' => $b['o'], 'bytes' => $b['ob'], 'transients' => $b['t'], 'sample' => $b['sample'], 'cron' => isset( $cron[ $id ] ) ? count( $cron[ $id ] ) : 0, 'tables' => isset( $tbl[ $id ] ) ? $tbl[ $id ] : array() );
		}
		$res['unknown'] = $unknown;
		set_transient( self::CACHE, $res, HOUR_IN_SECONDS );
		return $res;
	}

	public static function last() { $c = get_transient( self::CACHE ); return is_array( $c ) ? $c : null; }

	/**
	 * Remove the chosen leftovers. Everything is checked again against a fresh scan, a copy is saved first,
	 * and nothing is deleted if the copy cannot be written.
	 *
	 * @param string[] $ids    Plugin ids from signatures().
	 * @param string[] $tables Table names the person ticked (only tables of the chosen plugins are accepted).
	 * @return array|WP_Error  Counts and the saved file name.
	 */
	public static function cleanup( array $ids, array $tables ) {
		global $wpdb;
		if ( ! Sidcraft_RankBoost_DbBackup::allowed() ) { return new WP_Error( 'denied', __( 'Not allowed.', 'sidcraft-search-boost-suite' ) ); }
		$fresh = self::scan(); $sigs = self::signatures(); $plan = array(); $drop = array();
		foreach ( $ids as $id ) {
			if ( ! isset( $fresh['plugins'][ $id ], $sigs[ $id ] ) ) { continue; }
			$plan[ $id ] = $sigs[ $id ];
			foreach ( $fresh['plugins'][ $id ]['tables'] as $t ) { if ( in_array( $t['name'], $tables, true ) ) { $drop[] = $t['name']; } }
		}
		if ( ! $plan ) { return new WP_Error( 'none', __( 'Nothing to remove. Run the scan again.', 'sidcraft-search-boost-suite' ) ); }

		// 1. Save a copy of what will be removed.
		$dir = Sidcraft_RankBoost_DbBackup::dir();
		if ( '' === $dir ) { return new WP_Error( 'dir', __( 'The safety copy could not be saved (the uploads folder is not writable), so nothing was removed.', 'sidcraft-search-boost-suite' ) ); }
		$name = 'cleanup-' . gmdate( 'Ymd-His' ) . '-' . bin2hex( random_bytes( 8 ) ) . '.sql';
		$fh   = fopen( $dir . '/' . $name, 'wb' );
		if ( ! $fh ) { return new WP_Error( 'file', __( 'The safety copy could not be saved, so nothing was removed.', 'sidcraft-search-boost-suite' ) ); }
		$bad   = false;
		$write = static function ( $s ) use ( $fh, &$bad ) { if ( false === fwrite( $fh, $s ) ) { $bad = true; } };
		$write( Sidcraft_RankBoost_DbBackup::header_text( 'Copy of leftovers removed by Sidcraft Search Boost Suite: ' . implode( ', ', array_map( static function ( $p ) { return $p[0]; }, $plan ) ) ) );
		$ident = '`' . str_replace( '`', '``', $wpdb->options ) . '`';
		foreach ( $plan as $s ) {
			foreach ( $s[2] as $p ) {
				$rows = $wpdb->get_results( $wpdb->prepare( "SELECT option_name, option_value, autoload FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( $p ) . '%' ), ARRAY_A );
				foreach ( (array) $rows as $r ) {
					$write( 'INSERT IGNORE INTO ' . $ident . ' (option_name, option_value, autoload) VALUES (' . self::lit( $r['option_name'] ) . ',' . self::lit( $r['option_value'] ) . ',' . self::lit( $r['autoload'] ) . ");\n" );
				}
			}
		}
		foreach ( $drop as $t ) { Sidcraft_RankBoost_DbBackup::dump_table( $t, $write ); }
		$write( Sidcraft_RankBoost_DbBackup::footer_text() );
		fclose( $fh );
		if ( $bad ) { wp_delete_file( $dir . '/' . $name ); return new WP_Error( 'write', __( 'The safety copy could not be written completely (disk full?), so nothing was removed.', 'sidcraft-search-boost-suite' ) ); }

		// 2. Remove.
		$n = array( 'options' => 0, 'transients' => 0, 'cron' => 0, 'tables' => 0 );
		foreach ( $plan as $s ) {
			foreach ( $s[2] as $p ) {
				$n['options'] += (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( $p ) . '%' ) );
				foreach ( array( '_transient_', '_transient_timeout_', '_site_transient_', '_site_transient_timeout_' ) as $w ) {
					$n['transients'] += (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( $w . $p ) . '%' ) );
				}
			}
			$arr   = _get_cron_array();
			$hooks = array();
			foreach ( is_array( $arr ) ? $arr : array() as $events ) {
				foreach ( (array) $events as $hook => $e ) { if ( self::starts( $hook, $s[4] ) ) { $hooks[ $hook ] = true; } }
			}
			foreach ( array_keys( $hooks ) as $hook ) { wp_unschedule_hook( $hook ); $n['cron']++; }
		}
		foreach ( array_unique( $drop ) as $t ) { $wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $t ) ); $n['tables']++; }
		wp_cache_flush();
		delete_transient( self::CACHE );
		$n['file'] = $name;
		return $n;
	}

	private static function lit( $v ) {
		return null === $v ? 'NULL' : "'" . str_replace( array( '\\', "'", "\n", "\r", "\0", "\x1a" ), array( '\\\\', "\\'", '\\n', '\\r', '\\0', '\\Z' ), (string) $v ) . "'";
	}
}
