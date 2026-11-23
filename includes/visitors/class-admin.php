<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** The Visitor Stats screen, the settings screen, the dashboard widget, the CSV export. */
class Sidcraft_VS_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'wp_ajax_sidcraft_vs_data', array( __CLASS__, 'ajax_data' ) );
		add_action( 'admin_post_sidcraft_vs_save', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_sidcraft_vs_export', array( __CLASS__, 'export' ) );
		add_action( 'admin_post_sidcraft_vs_clear', array( __CLASS__, 'clear' ) );
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'widget_setup' ) );
	}

	private static $hooks = array();

	public static function menu() {
		self::$hooks[] = add_submenu_page( 'sidcraft-rankboost', __( 'Visitor Stats', 'sidcraft-rankboost' ), __( 'Visitor Stats', 'sidcraft-rankboost' ), 'manage_options', 'sidcraft-vs', array( __CLASS__, 'page' ) );
		self::$hooks[] = add_submenu_page( 'sidcraft-rankboost', __( 'Visitor Stats settings', 'sidcraft-rankboost' ), __( 'Visitor Stats Settings', 'sidcraft-rankboost' ), 'manage_options', 'sidcraft-vs-settings', array( __CLASS__, 'settings_page' ) );
	}

	public static function assets( $hook ) {
		if ( ! in_array( $hook, self::$hooks, true ) ) { return; }
		$is_settings = isset( self::$hooks[1] ) && self::$hooks[1] === $hook;
		wp_enqueue_style( 'sidcraft-vs-admin', SIDCRAFT_VS_URL . 'assets/css/admin.css', array(), SIDCRAFT_VS_VERSION );
		if ( $is_settings ) {
			wp_enqueue_script( 'sidcraft-vs-settings', SIDCRAFT_VS_URL . 'assets/js/settings.js', array(), SIDCRAFT_VS_VERSION, true );
			wp_localize_script( 'sidcraft-vs-settings', 'sidcraftVSSettings', array( 'added' => __( 'Added', 'sidcraft-rankboost' ), 'already' => __( 'Already in the list', 'sidcraft-rankboost' ) ) );
			return;
		}
		wp_enqueue_script( 'sidcraft-vs-admin', SIDCRAFT_VS_URL . 'assets/js/admin.js', array(), SIDCRAFT_VS_VERSION, true );
		wp_localize_script( 'sidcraft-vs-admin', 'sidcraftVSAdmin', array(
			'ajax' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'sidcraft_vs_data' ), 'today' => wp_date( 'Y-m-d' ),
			'export' => admin_url( 'admin-post.php' ), 'exportNonce' => wp_create_nonce( 'sidcraft_vs_export' ),
			'i18n' => array(
				'visitors' => __( 'Visitors', 'sidcraft-rankboost' ), 'views' => __( 'Page views', 'sidcraft-rankboost' ), 'pps' => __( 'Pages per visit', 'sidcraft-rankboost' ),
				'bounce' => __( 'Bounce rate', 'sidcraft-rankboost' ), 'duration' => __( 'Time on page', 'sidcraft-rankboost' ), 'online' => __( 'online now', 'sidcraft-rankboost' ),
				'today' => __( 'Today', 'sidcraft-rankboost' ), 'yesterday' => __( 'Yesterday', 'sidcraft-rankboost' ), 'd7' => __( '7 days', 'sidcraft-rankboost' ), 'd30' => __( '30 days', 'sidcraft-rankboost' ), 'd90' => __( '90 days', 'sidcraft-rankboost' ), 'month' => __( 'This month', 'sidcraft-rankboost' ),
				'apply' => __( 'Apply', 'sidcraft-rankboost' ), 'none' => __( 'No data for this period yet.', 'sidcraft-rankboost' ), 'pages' => __( 'Top pages', 'sidcraft-rankboost' ), 'sources' => __( 'Traffic sources', 'sidcraft-rankboost' ),
				'referrers' => __( 'Referring websites', 'sidcraft-rankboost' ), 'campaigns' => __( 'Campaigns', 'sidcraft-rankboost' ), 'countries' => __( 'Countries', 'sidcraft-rankboost' ), 'browsers' => __( 'Browsers', 'sidcraft-rankboost' ),
				'os' => __( 'Operating systems', 'sidcraft-rankboost' ), 'devices' => __( 'Devices', 'sidcraft-rankboost' ), 'hours' => __( 'Busiest hours (site time)', 'sidcraft-rankboost' ), 'notfound' => __( 'Pages not found (404)', 'sidcraft-rankboost' ),
				'retry' => __( 'Try again', 'sidcraft-rankboost' ), 'reload' => __( 'Reload this page', 'sidcraft-rankboost' ), 'vs' => __( 'vs previous period', 'sidcraft-rankboost' ), 'export' => __( 'Download CSV', 'sidcraft-rankboost' ), 'bots' => __( 'robot visits ignored', 'sidcraft-rankboost' ), 'error' => __( 'The statistics could not be loaded.', 'sidcraft-rankboost' ),
				'unknown' => __( 'Unknown', 'sidcraft-rankboost' ), 'direct' => __( 'Direct', 'sidcraft-rankboost' ), 'search' => __( 'Search engines', 'sidcraft-rankboost' ), 'social' => __( 'Social media', 'sidcraft-rankboost' ), 'referral' => __( 'Other websites', 'sidcraft-rankboost' ), 'campaign' => __( 'Campaigns', 'sidcraft-rankboost' ),
				'mobile' => __( 'Mobile', 'sidcraft-rankboost' ), 'desktop' => __( 'Desktop', 'sidcraft-rankboost' ), 'tablet' => __( 'Tablet', 'sidcraft-rankboost' ),
				'colviews' => __( 'Views', 'sidcraft-rankboost' ), 'colvisitors' => __( 'Visitors', 'sidcraft-rankboost' ),
			),
		) );
	}

	/** Validate a Y-m-d date; fall back to a default. */
	private static function date( $v, $fallback ) {
		$v = is_string( $v ) ? sanitize_text_field( $v ) : '';
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) && false !== strtotime( $v ) ? $v : $fallback;
	}

	private static function range() {
		// phpcs:disable WordPress.Security.NonceVerification -- called only after the nonce or capability check of the caller.
		$today = wp_date( 'Y-m-d' );
		$to    = self::date( isset( $_REQUEST['to'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['to'] ) ) : '', $today );
		$from  = self::date( isset( $_REQUEST['from'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['from'] ) ) : '', wp_date( 'Y-m-d', time() - 6 * DAY_IN_SECONDS ) );
		// phpcs:enable
		if ( $from > $to ) { $tmp = $from; $from = $to; $to = $tmp; }
		if ( ( strtotime( $to ) - strtotime( $from ) ) > 400 * DAY_IN_SECONDS ) { $from = gmdate( 'Y-m-d', strtotime( $to ) - 400 * DAY_IN_SECONDS ); }
		return array( $from, $to );
	}

	public static function ajax_data() {
		check_ajax_referer( 'sidcraft_vs_data', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( null, 403 ); }
		// This report runs about a dozen queries: give it room, so a busy server does not cut it off halfway.
		if ( function_exists( 'wp_raise_memory_limit' ) ) { wp_raise_memory_limit( 'admin' ); }
		list( $from, $to ) = self::range();
		$days = (int) round( ( strtotime( $to ) - strtotime( $from ) ) / DAY_IN_SECONDS ) + 1;
		$pf = gmdate( 'Y-m-d', strtotime( $from ) - $days * DAY_IN_SECONDS ); $pt = gmdate( 'Y-m-d', strtotime( $from ) - DAY_IN_SECONDS );
		$o = Sidcraft_VS_Settings::all();
		$key = 'svs_report_' . md5( $from . '|' . $to . '|' . (int) $o['track_bots'] );
		$data = get_transient( $key );
		if ( ! is_array( $data ) ) {
			$data = array(
				'from' => $from, 'to' => $to, 'summary' => Sidcraft_VS_DB::summary( $from, $to ), 'prev' => Sidcraft_VS_DB::summary( $pf, $pt ),
				'series' => Sidcraft_VS_DB::series( $from, $to ), 'hours' => Sidcraft_VS_DB::hours( $from, $to ),
				'pages' => Sidcraft_VS_DB::top( 'path', $from, $to, 10 ), 'sources' => Sidcraft_VS_DB::top( 'source', $from, $to, 6 ), 'referrers' => Sidcraft_VS_DB::top( 'ref_host', $from, $to, 10 ),
				'campaigns' => Sidcraft_VS_DB::top( 'campaign', $from, $to, 8 ), 'countries' => Sidcraft_VS_DB::top( 'country', $from, $to, 10 ), 'browsers' => Sidcraft_VS_DB::top( 'browser', $from, $to, 8 ),
				'os' => Sidcraft_VS_DB::top( 'os', $from, $to, 8 ), 'devices' => Sidcraft_VS_DB::top( 'device', $from, $to, 4 ), 'notfound' => Sidcraft_VS_DB::not_found( $from, $to, 8 ),
				'bots' => $o['track_bots'] ? Sidcraft_VS_DB::bots( $from, $to ) : null,
			);
			set_transient( $key, $data, $to >= wp_date( 'Y-m-d' ) ? 2 * MINUTE_IN_SECONDS : 15 * MINUTE_IN_SECONDS ); // Past ranges never change, so keep them longer.
		}
		$data['online'] = Sidcraft_VS_DB::online();
		wp_send_json_success( $data );
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$o = Sidcraft_VS_Settings::all();
		echo '<div class="wrap sidcraft-vs"><h1 class="wp-heading-inline">' . esc_html__( 'Visitor Stats', 'sidcraft-rankboost' ) . '</h1> <a class="page-title-action" href="' . esc_url( admin_url( 'admin.php?page=sidcraft-vs-settings' ) ) . '">' . esc_html__( 'Settings', 'sidcraft-rankboost' ) . '</a><hr class="wp-header-end">';
		if ( ! $o['enabled'] ) { echo '<div class="notice notice-warning"><p>' . esc_html__( 'Tracking is switched off. Turn it on in Settings.', 'sidcraft-rankboost' ) . '</p></div>'; }
		echo '<div id="svs-app" class="svs-app"><p>' . esc_html__( 'Loading…', 'sidcraft-rankboost' ) . '</p></div></div>';
	}

	/* ---------------------------------------------------------- settings */

	public static function settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$o = Sidcraft_VS_Settings::all(); $roles = wp_roles()->get_names(); $my_ip = Sidcraft_VS_Tracker::ip();
		// phpcs:disable WordPress.Security.NonceVerification -- only shows a notice.
		$msg = isset( $_GET['done'] ) ? sanitize_key( wp_unslash( $_GET['done'] ) ) : '';
		// phpcs:enable
		echo '<div class="wrap sidcraft-vs"><h1>' . esc_html__( 'Visitor Stats settings', 'sidcraft-rankboost' ) . '</h1>';
		if ( 'saved' === $msg ) { echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'sidcraft-rankboost' ) . '</p></div>'; }
		if ( 'cleared' === $msg ) { echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'All statistics were deleted.', 'sidcraft-rankboost' ) . '</p></div>'; }
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="sidcraft_vs_save">'; wp_nonce_field( 'sidcraft_vs_save' );
		echo '<table class="form-table" role="presentation">';
		self::row( __( 'Tracking', 'sidcraft-rankboost' ), '<label><input type="checkbox" name="enabled" value="1"' . checked( $o['enabled'], 1, false ) . '> ' . esc_html__( 'Record visits', 'sidcraft-rankboost' ) . '</label>' );
		$rl = ''; foreach ( $roles as $slug => $name ) { $rl .= '<label class="svs-check"><input type="checkbox" name="exclude_roles[]" value="' . esc_attr( $slug ) . '"' . checked( in_array( $slug, (array) $o['exclude_roles'], true ), true, false ) . '> ' . esc_html( translate_user_role( $name ) ) . '</label> '; }
		self::row( __( 'Do not count these users', 'sidcraft-rankboost' ), $rl . '<p class="description">' . esc_html__( 'Logged-in people with these roles are never counted (their pages do not even load the tracking script).', 'sidcraft-rankboost' ) . '</p>' );
		self::row( __( 'Do not count these IP addresses', 'sidcraft-rankboost' ), '<textarea name="exclude_ips" id="svs-ips" rows="4" class="large-text code" placeholder="203.0.113.5">' . esc_textarea( $o['exclude_ips'] ) . '</textarea><p><button type="button" class="button" id="svs-add-ip" data-ip="' . esc_attr( $my_ip ) . '"' . ( '' === $my_ip ? ' disabled' : '' ) . '>' . esc_html__( 'Add My IP Address', 'sidcraft-rankboost' ) . '</button> <span id="svs-add-ip-msg" class="description"></span></p><p class="description">' . ( '' !== $my_ip ? esc_html( sprintf( /* translators: %s: IP address */ __( 'Your current address: %s.', 'sidcraft-rankboost' ), $my_ip ) ) . ' ' : '' ) . esc_html__( 'One per line. Remember to save. If you use Cloudflare or another proxy, choose it under "Behind a proxy?" and save first, so the right address is detected.', 'sidcraft-rankboost' ) . '</p>' );
		self::row( __( 'Respect Do Not Track', 'sidcraft-rankboost' ), '<label><input type="checkbox" name="respect_dnt" value="1"' . checked( $o['respect_dnt'], 1, false ) . '> ' . esc_html__( 'Skip visitors whose browser sends Do Not Track or Global Privacy Control', 'sidcraft-rankboost' ) . '</label>' );
		self::row( __( 'Robots', 'sidcraft-rankboost' ), '<label><input type="checkbox" name="track_bots" value="1"' . checked( $o['track_bots'], 1, false ) . '> ' . esc_html__( 'Also record robot visits (shown only as a count, never mixed into the visitor numbers)', 'sidcraft-rankboost' ) . '</label>' );
		self::row( __( 'Pages not found', 'sidcraft-rankboost' ), '<label><input type="checkbox" name="track_404" value="1"' . checked( $o['track_404'], 1, false ) . '> ' . esc_html__( 'Record 404 pages so you can see broken links', 'sidcraft-rankboost' ) . '</label>' );
		$px = ''; foreach ( array( 'none' => __( 'Direct connection (most sites)', 'sidcraft-rankboost' ), 'cloudflare' => __( 'Cloudflare (use CF-Connecting-IP, and the visitor country)', 'sidcraft-rankboost' ), 'xff' => __( 'Other proxy or load balancer (X-Forwarded-For)', 'sidcraft-rankboost' ) ) as $k => $l ) { $px .= '<option value="' . esc_attr( $k ) . '"' . selected( $o['proxy'], $k, false ) . '>' . esc_html( $l ) . '</option>'; }
		self::row( __( 'Behind a proxy?', 'sidcraft-rankboost' ), '<select name="proxy">' . $px . '</select><p class="description">' . esc_html__( 'The visitor country is read from the Cloudflare header when it is present. No IP address is ever stored.', 'sidcraft-rankboost' ) . '</p>' );
		self::row( __( 'Keep data for', 'sidcraft-rankboost' ), '<input type="number" name="retention_days" min="0" max="3650" value="' . esc_attr( $o['retention_days'] ) . '" class="small-text"> ' . esc_html__( 'days (0 = forever)', 'sidcraft-rankboost' ) . '<p class="description">' . esc_html__( 'Older rows are deleted automatically every day.', 'sidcraft-rankboost' ) . '</p>' );
		self::row( __( 'Dashboard widget', 'sidcraft-rankboost' ), '<label><input type="checkbox" name="show_widget" value="1"' . checked( $o['show_widget'], 1, false ) . '> ' . esc_html__( 'Show a summary on the WordPress dashboard', 'sidcraft-rankboost' ) . '</label>' );
		self::row( __( 'On uninstall', 'sidcraft-rankboost' ), '<label><input type="checkbox" name="delete_on_uninstall" value="1"' . checked( $o['delete_on_uninstall'], 1, false ) . '> ' . esc_html__( 'Delete all statistics and settings when the plugin is deleted', 'sidcraft-rankboost' ) . '</label>' );
		echo '</table>'; submit_button( __( 'Save settings', 'sidcraft-rankboost' ) ); echo '</form>';

		echo '<hr><h2>' . esc_html__( 'Your data', 'sidcraft-rankboost' ) . '</h2><p>' . esc_html( sprintf( /* translators: %s: number of rows */ __( '%s page views are stored.', 'sidcraft-rankboost' ), number_format_i18n( Sidcraft_VS_DB::count_rows() ) ) ) . '</p>';
		echo '<p class="description">' . esc_html__( 'How privacy works: no cookies, no IP address stored. A visitor is a one-way hash of the address and browser with a secret that changes every day, so the same person cannot be followed from one day to the next.', 'sidcraft-rankboost' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" data-sidcraft-confirm="' . esc_attr__( 'Delete ALL statistics? This cannot be undone.', 'sidcraft-rankboost' ) . '"><input type="hidden" name="action" value="sidcraft_vs_clear">'; wp_nonce_field( 'sidcraft_vs_clear' );
		echo '<button class="button button-link-delete" type="submit">' . esc_html__( 'Delete all statistics', 'sidcraft-rankboost' ) . '</button></form></div>';
	}

	private static function row( $label, $html ) {
		echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . $html . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every part of $html is escaped where it is built, in settings_page().
	}

	public static function save() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'You do not have permission to do this.', 'sidcraft-rankboost' ), 403 ); }
		check_admin_referer( 'sidcraft_vs_save' );
		$in = isset( $_POST ) ? wp_unslash( $_POST ) : array(); // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce checked above; every key is validated one by one in Sidcraft_VS_Settings::sanitize().
		update_option( Sidcraft_VS_Settings::OPTION, Sidcraft_VS_Settings::sanitize( is_array( $in ) ? $in : array() ), true );
		wp_safe_redirect( admin_url( 'admin.php?page=sidcraft-vs-settings&done=saved' ) ); exit;
	}

	public static function clear() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'You do not have permission to do this.', 'sidcraft-rankboost' ), 403 ); }
		check_admin_referer( 'sidcraft_vs_clear' );
		Sidcraft_VS_DB::delete_all();
		wp_safe_redirect( admin_url( 'admin.php?page=sidcraft-vs-settings&done=cleared' ) ); exit;
	}

	/* ------------------------------------------------------------ export */

	public static function export() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'You do not have permission to do this.', 'sidcraft-rankboost' ), 403 ); }
		check_admin_referer( 'sidcraft_vs_export', 'nonce' );
		list( $from, $to ) = self::range();
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="visitor-stats-' . $from . '-to-' . $to . '.csv"' );
		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- streaming a download to the browser, not touching the file system.
		fputcsv( $out, array( 'time_utc', 'path', 'title', 'status', 'source', 'referrer', 'campaign', 'browser', 'os', 'device', 'country', 'seconds_on_page' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv
		foreach ( Sidcraft_VS_DB::export_rows( $from, $to ) as $r ) {
			foreach ( array( 'path', 'title', 'ref_host', 'campaign' ) as $k ) { if ( isset( $r[ $k ] ) && preg_match( '/^[=+\-@\t\r]/', (string) $r[ $k ] ) ) { $r[ $k ] = "'" . $r[ $k ]; } } // spreadsheet formula safety
			fputcsv( $out, array( $r['ts'], $r['path'], $r['title'], $r['status'], $r['source'], $r['ref_host'], $r['campaign'], $r['browser'], $r['os'], $r['device'], $r['country'], $r['duration'] ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/* ----------------------------------------------------------- widget */

	public static function widget_setup() {
		if ( ! current_user_can( 'manage_options' ) || ! Sidcraft_VS_Settings::all()['show_widget'] ) { return; }
		wp_add_dashboard_widget( 'sidcraft_vs_widget', __( 'Visitors – last 7 days', 'sidcraft-rankboost' ), array( __CLASS__, 'widget' ) );
	}

	public static function widget() {
		$to = wp_date( 'Y-m-d' ); $from = wp_date( 'Y-m-d', time() - 6 * DAY_IN_SECONDS );
		// Cached for two minutes so opening the dashboard does not run the statistics queries every time.
		$w = get_transient( 'sidcraft_vs_widget' );
		if ( ! is_array( $w ) || ( $w['to'] ?? '' ) !== $to ) {
			$ser = Sidcraft_VS_DB::series( $from, $to ); $tv = 0; $tp = 0; foreach ( $ser as $r ) { $tv += $r['v']; $tp += $r['p']; }
			$w = array( 'to' => $to, 'series' => $ser, 'visitors' => $tv, 'views' => $tp, 'online' => Sidcraft_VS_DB::online() );
			set_transient( 'sidcraft_vs_widget', $w, 2 * MINUTE_IN_SECONDS );
		}
		$series = $w['series']; $s = array( 'visitors' => $w['visitors'], 'views' => $w['views'] ); $max = 1; foreach ( $series as $r ) { $max = max( $max, $r['p'] ); }
		echo '<div class="svs-widget"><p><strong style="font-size:22px">' . esc_html( number_format_i18n( $s['visitors'] ) ) . '</strong> ' . esc_html__( 'visitors', 'sidcraft-rankboost' ) . ' &nbsp; <strong style="font-size:22px">' . esc_html( number_format_i18n( $s['views'] ) ) . '</strong> ' . esc_html__( 'page views', 'sidcraft-rankboost' ) . ' &nbsp; <span style="color:#2271b1">' . esc_html( sprintf( /* translators: %d: visitors */ __( '%d online now', 'sidcraft-rankboost' ), $w['online'] ) ) . '</span></p>';
		echo '<div style="display:flex;align-items:flex-end;gap:4px;height:60px">';
		foreach ( $series as $r ) { echo '<div title="' . esc_attr( $r['d'] . ': ' . $r['p'] ) . '" style="flex:1;background:#2271b1;opacity:.85;border-radius:2px 2px 0 0;height:' . esc_attr( max( 2, (int) round( 100 * $r['p'] / $max ) ) ) . '%"></div>'; }
		echo '</div><p><a href="' . esc_url( admin_url( 'admin.php?page=sidcraft-vs' ) ) . '">' . esc_html__( 'Open the full statistics →', 'sidcraft-rankboost' ) . '</a></p></div>';
	}
}
