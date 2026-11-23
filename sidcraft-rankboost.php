<?php
/**
 * Plugin Name:       Sidcraft RankBoost
 * Description:       Hands-off SEO for WordPress pages and posts: automatic titles and descriptions, social cards, structured data, sitemap, redirects, image alt text, instant search-engine notification and a daily self-audit, speed tools and private visitor statistics. Works out of the box with no setup.
 * Version:           2.0.2
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Suhaib Siddiqi
 * Author URI:        https://profiles.wordpress.org/ssiddiqi4/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       sidcraft-rankboost
 *
 * @package Sidcraft_RankBoost
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SIDCRAFT_RANKBOOST_VERSION', '2.0.2' );
define( 'SIDCRAFT_RANKBOOST_FILE', __FILE__ );
define( 'SIDCRAFT_RANKBOOST_DIR', plugin_dir_path( __FILE__ ) );

// Visitor Stats module (formerly the separate Sidcraft Visitor Stats plugin).
define( 'SIDCRAFT_VS_VERSION', SIDCRAFT_RANKBOOST_VERSION );
define( 'SIDCRAFT_VS_FILE', __FILE__ );
define( 'SIDCRAFT_VS_URL', plugin_dir_url( __FILE__ ) . 'includes/visitors/' );

require_once SIDCRAFT_RANKBOOST_DIR . 'includes/class-util.php';
require_once SIDCRAFT_RANKBOOST_DIR . 'includes/class-settings.php';
require_once SIDCRAFT_RANKBOOST_DIR . 'includes/class-meta.php';
require_once SIDCRAFT_RANKBOOST_DIR . 'includes/class-schema.php';
require_once SIDCRAFT_RANKBOOST_DIR . 'includes/class-sitemap.php';
require_once SIDCRAFT_RANKBOOST_DIR . 'includes/class-redirects.php';
require_once SIDCRAFT_RANKBOOST_DIR . 'includes/class-images.php';
require_once SIDCRAFT_RANKBOOST_DIR . 'includes/class-hygiene.php';
require_once SIDCRAFT_RANKBOOST_DIR . 'includes/class-related.php';
require_once SIDCRAFT_RANKBOOST_DIR . 'includes/class-indexnow.php';
require_once SIDCRAFT_RANKBOOST_DIR . 'includes/class-audit.php';
require_once SIDCRAFT_RANKBOOST_DIR . 'includes/class-fixer.php';
require_once SIDCRAFT_RANKBOOST_DIR . 'includes/class-links.php';
require_once SIDCRAFT_RANKBOOST_DIR . 'includes/class-suggest.php';
require_once SIDCRAFT_RANKBOOST_DIR . 'includes/class-editor.php';
require_once SIDCRAFT_RANKBOOST_DIR . 'includes/class-minify.php';
require_once SIDCRAFT_RANKBOOST_DIR . 'includes/class-speed.php';
require_once SIDCRAFT_RANKBOOST_DIR . 'includes/class-cache.php';
require_once SIDCRAFT_RANKBOOST_DIR . 'includes/class-dbclean.php';
require_once SIDCRAFT_RANKBOOST_DIR . 'includes/class-perf.php';
require_once SIDCRAFT_RANKBOOST_DIR . 'includes/class-speed-admin.php';
require_once SIDCRAFT_RANKBOOST_DIR . 'includes/class-admin.php';
require_once SIDCRAFT_RANKBOOST_DIR . 'includes/visitors/class-settings.php';
require_once SIDCRAFT_RANKBOOST_DIR . 'includes/visitors/class-db.php';
require_once SIDCRAFT_RANKBOOST_DIR . 'includes/visitors/class-ua.php';
require_once SIDCRAFT_RANKBOOST_DIR . 'includes/visitors/class-tracker.php';
require_once SIDCRAFT_RANKBOOST_DIR . 'includes/visitors/class-admin.php';

// Serve a saved copy of the page (when the page cache is on) before WordPress does any more work.
Sidcraft_RankBoost_Cache::maybe_serve();

register_activation_hook( __FILE__, array( 'Sidcraft_RankBoost_Redirects', 'install' ) );
register_activation_hook( __FILE__, array( 'Sidcraft_RankBoost_Audit', 'schedule' ) );
register_activation_hook( __FILE__, array( 'Sidcraft_VS_DB', 'install' ) );
register_deactivation_hook( __FILE__, array( 'Sidcraft_RankBoost_Audit', 'unschedule' ) );
register_deactivation_hook( __FILE__, function () { wp_clear_scheduled_hook( 'sidcraft_vs_purge' ); } );
register_deactivation_hook( __FILE__, function () { wp_clear_scheduled_hook( Sidcraft_RankBoost_DbClean::HOOK ); wp_clear_scheduled_hook( Sidcraft_RankBoost_Cache::CLEAR_HOOK ); wp_clear_scheduled_hook( Sidcraft_RankBoost_Cache::HOOK, array( 0 ) ); Sidcraft_RankBoost_Cache::clear_all(); } );

add_action(
	'plugins_loaded',
	function () {
		Sidcraft_RankBoost_Settings::init();
		Sidcraft_RankBoost_Redirects::init();
		Sidcraft_RankBoost_Images::init();
		Sidcraft_RankBoost_Hygiene::init();
		Sidcraft_RankBoost_Related::init();
		Sidcraft_RankBoost_IndexNow::init();
		Sidcraft_RankBoost_Audit::init();
		Sidcraft_RankBoost_Links::init();
		Sidcraft_RankBoost_Speed::init();
		Sidcraft_RankBoost_Cache::init();
		Sidcraft_RankBoost_Minify::init();
		Sidcraft_RankBoost_DbClean::init();
		Sidcraft_RankBoost_Perf::init();
		Sidcraft_VS_DB::maybe_upgrade();
		Sidcraft_VS_Tracker::init();
		if ( is_admin() ) {
			Sidcraft_RankBoost_Admin::init();
			Sidcraft_RankBoost_Speed_Admin::init();
			Sidcraft_VS_Admin::init();
			if ( ! Sidcraft_RankBoost_Util::other_seo_plugin() ) { Sidcraft_RankBoost_Editor::init(); }
		}
		// Output modules step aside when another SEO plugin is already doing the same job.
		if ( ! Sidcraft_RankBoost_Util::other_seo_plugin() ) {
			Sidcraft_RankBoost_Meta::init();
			Sidcraft_RankBoost_Schema::init();
			Sidcraft_RankBoost_Sitemap::init();
		}
	},
	20
);
