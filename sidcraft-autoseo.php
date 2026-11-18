<?php
/**
 * Plugin Name:       Sidcraft AutoSEO
 * Description:       Hands-off SEO for WordPress pages and posts: automatic titles and descriptions, social cards, structured data, sitemap, redirects, image alt text, instant search-engine notification and a daily self-audit. Works out of the box with no setup.
 * Version:           1.8.3
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Suhaib Siddiqi
 * Author URI:        https://profiles.wordpress.org/ssiddiqi4/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       sidcraft-autoseo
 *
 * @package Sidcraft_AutoSEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SIDCRAFT_AUTOSEO_VERSION', '1.8.3' );
define( 'SIDCRAFT_AUTOSEO_FILE', __FILE__ );
define( 'SIDCRAFT_AUTOSEO_DIR', plugin_dir_path( __FILE__ ) );

require_once SIDCRAFT_AUTOSEO_DIR . 'includes/class-util.php';
require_once SIDCRAFT_AUTOSEO_DIR . 'includes/class-settings.php';
require_once SIDCRAFT_AUTOSEO_DIR . 'includes/class-meta.php';
require_once SIDCRAFT_AUTOSEO_DIR . 'includes/class-schema.php';
require_once SIDCRAFT_AUTOSEO_DIR . 'includes/class-sitemap.php';
require_once SIDCRAFT_AUTOSEO_DIR . 'includes/class-redirects.php';
require_once SIDCRAFT_AUTOSEO_DIR . 'includes/class-images.php';
require_once SIDCRAFT_AUTOSEO_DIR . 'includes/class-hygiene.php';
require_once SIDCRAFT_AUTOSEO_DIR . 'includes/class-related.php';
require_once SIDCRAFT_AUTOSEO_DIR . 'includes/class-indexnow.php';
require_once SIDCRAFT_AUTOSEO_DIR . 'includes/class-audit.php';
require_once SIDCRAFT_AUTOSEO_DIR . 'includes/class-fixer.php';
require_once SIDCRAFT_AUTOSEO_DIR . 'includes/class-links.php';
require_once SIDCRAFT_AUTOSEO_DIR . 'includes/class-suggest.php';
require_once SIDCRAFT_AUTOSEO_DIR . 'includes/class-editor.php';
require_once SIDCRAFT_AUTOSEO_DIR . 'includes/class-minify.php';
require_once SIDCRAFT_AUTOSEO_DIR . 'includes/class-speed.php';
require_once SIDCRAFT_AUTOSEO_DIR . 'includes/class-cache.php';
require_once SIDCRAFT_AUTOSEO_DIR . 'includes/class-dbclean.php';
require_once SIDCRAFT_AUTOSEO_DIR . 'includes/class-perf.php';
require_once SIDCRAFT_AUTOSEO_DIR . 'includes/class-speed-admin.php';
require_once SIDCRAFT_AUTOSEO_DIR . 'includes/class-admin.php';

// Serve a saved copy of the page (when the page cache is on) before WordPress does any more work.
Sidcraft_AutoSEO_Cache::maybe_serve();

register_activation_hook( __FILE__, array( 'Sidcraft_AutoSEO_Redirects', 'install' ) );
register_activation_hook( __FILE__, array( 'Sidcraft_AutoSEO_Audit', 'schedule' ) );
register_deactivation_hook( __FILE__, array( 'Sidcraft_AutoSEO_Audit', 'unschedule' ) );
register_deactivation_hook( __FILE__, function () { wp_clear_scheduled_hook( Sidcraft_AutoSEO_DbClean::HOOK ); wp_clear_scheduled_hook( Sidcraft_AutoSEO_Cache::CLEAR_HOOK ); wp_clear_scheduled_hook( Sidcraft_AutoSEO_Cache::HOOK, array( 0 ) ); Sidcraft_AutoSEO_Cache::clear_all(); } );

add_action(
	'plugins_loaded',
	function () {
		Sidcraft_AutoSEO_Settings::init();
		Sidcraft_AutoSEO_Redirects::init();
		Sidcraft_AutoSEO_Images::init();
		Sidcraft_AutoSEO_Hygiene::init();
		Sidcraft_AutoSEO_Related::init();
		Sidcraft_AutoSEO_IndexNow::init();
		Sidcraft_AutoSEO_Audit::init();
		Sidcraft_AutoSEO_Links::init();
		Sidcraft_AutoSEO_Speed::init();
		Sidcraft_AutoSEO_Cache::init();
		Sidcraft_AutoSEO_Minify::init();
		Sidcraft_AutoSEO_DbClean::init();
		Sidcraft_AutoSEO_Perf::init();
		if ( is_admin() ) {
			Sidcraft_AutoSEO_Admin::init();
			Sidcraft_AutoSEO_Speed_Admin::init();
			if ( ! Sidcraft_AutoSEO_Util::other_seo_plugin() ) { Sidcraft_AutoSEO_Editor::init(); }
		}
		// Output modules step aside when another SEO plugin is already doing the same job.
		if ( ! Sidcraft_AutoSEO_Util::other_seo_plugin() ) {
			Sidcraft_AutoSEO_Meta::init();
			Sidcraft_AutoSEO_Schema::init();
			Sidcraft_AutoSEO_Sitemap::init();
		}
	},
	20
);
