<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** The "Speed & Database" tab: page cache and minify, database clean-up, performance and debugging. */
class Sidcraft_AutoSEO_Speed_Admin {

	public static function init() {
		add_action( 'admin_post_sidcraft_autoseo_speed', array( __CLASS__, 'handle' ) );
	}

	private static function url( $sec, $args = array() ) { return add_query_arg( array_merge( array( 'page' => 'sidcraft-autoseo', 'tab' => 'speed', 'sec' => $sec ), $args ), admin_url( 'admin.php' ) ); }

	private static function form_open( $do ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'; wp_nonce_field( 'sidcraft_autoseo_speed' );
		echo '<input type="hidden" name="action" value="sidcraft_autoseo_speed"><input type="hidden" name="do" value="' . esc_attr( $do ) . '">';
	}

	private static function btn( $do, $label, $class = 'button', $confirm = '' ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin:0 8px 8px 0"' . ( '' !== $confirm ? ' onsubmit="return confirm(' . esc_attr( wp_json_encode( $confirm ) ) . ')"' : '' ) . '>'; wp_nonce_field( 'sidcraft_autoseo_speed' );
		echo '<input type="hidden" name="action" value="sidcraft_autoseo_speed"><input type="hidden" name="do" value="' . esc_attr( $do ) . '"><button class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</button></form>';
	}

	private static function days_select( $name, $val ) {
		$h = '<select name="' . esc_attr( $name ) . '">';
		foreach ( Sidcraft_AutoSEO_Speed::day_options() as $k => $l ) { $h .= '<option value="' . (int) $k . '"' . selected( (int) $val, $k, false ) . '>' . esc_html( $l ) . '</option>'; }
		return $h . '</select>';
	}

	private static function next_run( $hook ) {
		$t = wp_next_scheduled( $hook );
		return $t ? '<br><span class="description">' . esc_html( sprintf( /* translators: %s: date and time */ __( 'Next run: %s', 'sidcraft-autoseo' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $t ) ) ) . '</span>' : '';
	}

	private static function check( $name, $label, $on, $help = '' ) {
		echo '<p><label><input type="checkbox" name="' . esc_attr( $name ) . '" value="1" ' . checked( (bool) $on, true, false ) . '> <strong>' . esc_html( $label ) . '</strong></label>' . ( '' !== $help ? '<br><span class="description" style="margin-left:24px;display:inline-block">' . esc_html( $help ) . '</span>' : '' ) . '</p>';
	}

	public static function render() {
		$sec = isset( $_GET['sec'] ) ? sanitize_key( wp_unslash( $_GET['sec'] ) ) : 'cache'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen switch.
		$secs = array( 'cache' => __( 'Cache & minify', 'sidcraft-autoseo' ), 'database' => __( 'Database', 'sidcraft-autoseo' ), 'debug' => __( 'Performance & debug', 'sidcraft-autoseo' ) );
		if ( ! isset( $secs[ $sec ] ) ) { $sec = 'cache'; }
		echo '<div style="margin:14px 0 18px;display:flex;flex-wrap:wrap;gap:8px">';
		foreach ( $secs as $k => $l ) { echo '<a class="button ' . ( $k === $sec ? 'button-primary' : 'button-secondary' ) . '" style="font-size:14px;padding:4px 18px;height:auto;line-height:2" href="' . esc_url( self::url( $k ) ) . '"' . ( $k === $sec ? ' aria-current="page"' : '' ) . '>' . esc_html( $l ) . '</a>'; }
		echo '</div>';
		call_user_func( array( __CLASS__, 'sec_' . $sec ) );
	}

	/* ------------------------------------------------------------- cache */

	private static function sec_cache() {
		$o = Sidcraft_AutoSEO_Speed::all(); $d = Sidcraft_AutoSEO_Cache::dir();
		list( $n, $b ) = '' === $d ? array( 0, 0 ) : Sidcraft_AutoSEO_Speed::dir_stats( $d );
		$up = wp_get_upload_dir(); list( $mn, $mb ) = empty( $up['error'] ) ? Sidcraft_AutoSEO_Speed::dir_stats( trailingslashit( $up['basedir'] ) . 'sidcraft-autoseo/min' ) : array( 0, 0 );
		echo '<p>' . esc_html__( 'Keep a ready-made copy of every page for visitors who are not logged in, and shrink your HTML, CSS and JavaScript. All of this is off until you switch it on. It never touches your original files or content.', 'sidcraft-autoseo' ) . '</p>';
		echo '<div style="background:#fff;border:1px solid #c3c4c7;border-radius:6px;padding:12px 18px;margin:12px 0"><strong>' . esc_html( $o['cache'] ? __( 'Page cache is ON', 'sidcraft-autoseo' ) : __( 'Page cache is off', 'sidcraft-autoseo' ) ) . '</strong> · ' . esc_html( sprintf( /* translators: 1: number of pages, 2: size */ __( '%1$d pages stored (%2$s)', 'sidcraft-autoseo' ), $n, size_format( $b, 1 ) ) ) . ' · ' . esc_html( sprintf( /* translators: 1: number of files, 2: size */ __( '%1$d minified files (%2$s)', 'sidcraft-autoseo' ), $mn, size_format( $mb, 1 ) ) ) . '<br>';
		self::btn( 'clear_cache', __( 'Clear page cache', 'sidcraft-autoseo' ), 'button button-primary' ); self::btn( 'preload', __( 'Preload now', 'sidcraft-autoseo' ) ); self::btn( 'clear_min', __( 'Clear minified files', 'sidcraft-autoseo' ) );
		echo '</div>';
		self::form_open( 'save_cache' );
		echo '<h2>' . esc_html__( 'Page cache', 'sidcraft-autoseo' ) . '</h2>';
		self::check( 'cache', __( 'Cache pages and posts', 'sidcraft-autoseo' ), $o['cache'], __( 'Visitors who are not logged in get a saved copy of the page instead of waiting for WordPress to build it. The cache clears itself whenever you publish, edit, comment, or change a menu, widget, theme or plugin.', 'sidcraft-autoseo' ) );
		echo '<p><label>' . esc_html__( 'Keep a page for', 'sidcraft-autoseo' ) . ' <input type="number" min="1" max="720" name="cache_hours" value="' . esc_attr( $o['cache_hours'] ) . '" style="width:70px"> ' . esc_html__( 'hours', 'sidcraft-autoseo' ) . '</label></p>';
		echo '<p><label><strong>' . esc_html__( 'Clear the cache on a schedule', 'sidcraft-autoseo' ) . '</strong> ' . self::days_select( 'cache_days', $o['cache_days'] ) . '</label><br><span class="description">' . esc_html__( 'Empties the page cache automatically. 1 = every day at 12:01 AM (your site’s time zone). The cache also clears whenever you change content.', 'sidcraft-autoseo' ) . '</span>' . self::next_run( Sidcraft_AutoSEO_Cache::CLEAR_HOOK ) . '</p>';
		self::check( 'cache_preload', __( 'Preload after clearing', 'sidcraft-autoseo' ), $o['cache_preload'], __( 'Visits your newest pages in the background so the first real visitor already gets a fast page.', 'sidcraft-autoseo' ) );
		echo '<p><label for="sbs-cx"><strong>' . esc_html__( 'Never cache addresses containing', 'sidcraft-autoseo' ) . '</strong></label><br><textarea id="sbs-cx" name="cache_exclude" rows="3" cols="50" placeholder="/cart&#10;/checkout&#10;/my-account">' . esc_textarea( $o['cache_exclude'] ) . '</textarea><br><span class="description">' . esc_html__( 'One per line. Shopping carts, checkouts, account pages and anyone logged in or with a comment cookie are already skipped. Pages with a query string (?…) are never cached.', 'sidcraft-autoseo' ) . '</span></p>';
		echo '<h2>' . esc_html__( 'Minify', 'sidcraft-autoseo' ) . '</h2>';
		self::check( 'min_html', __( 'Minify HTML', 'sidcraft-autoseo' ), $o['min_html'], __( 'Removes comments and extra spaces from the page. Scripts, <pre> and text areas are left alone.', 'sidcraft-autoseo' ) );
		self::check( 'min_css', __( 'Minify CSS files', 'sidcraft-autoseo' ), $o['min_css'], __( 'Your theme’s and plugins’ stylesheets are served as smaller copies saved in the uploads folder. Files that are already .min are left alone.', 'sidcraft-autoseo' ) );
		self::check( 'min_js', __( 'Minify JavaScript files', 'sidcraft-autoseo' ), $o['min_js'], __( 'The same for scripts. If a script misbehaves, add its name below; the original is then used.', 'sidcraft-autoseo' ) );
		echo '<p><label for="sbs-mx"><strong>' . esc_html__( 'Do not minify (script/style handle or part of the file address)', 'sidcraft-autoseo' ) . '</strong></label><br><textarea id="sbs-mx" name="min_exclude" rows="3" cols="50" placeholder="jquery&#10;/plugins/my-slider/">' . esc_textarea( $o['min_exclude'] ) . '</textarea></p>';
		echo '<h2>' . esc_html__( 'Other speed-ups', 'sidcraft-autoseo' ) . '</h2>';
		self::check( 'no_emoji', __( 'Remove the emoji script', 'sidcraft-autoseo' ), $o['no_emoji'], __( 'Saves a script and a style on every page. Emoji still show in modern browsers.', 'sidcraft-autoseo' ) );
		self::check( 'no_embeds', __( 'Remove the embed script (wp-embed)', 'sidcraft-autoseo' ), $o['no_embeds'], __( 'Only needed if other sites embed your posts as cards.', 'sidcraft-autoseo' ) );
		self::check( 'no_dashicons', __( 'Do not load Dashicons for visitors', 'sidcraft-autoseo' ), $o['no_dashicons'], __( 'The admin icon font is rarely needed on the public site. Untick if some icons disappear.', 'sidcraft-autoseo' ) );
		echo '<p><label><strong>' . esc_html__( 'Heartbeat (the background check while you edit)', 'sidcraft-autoseo' ) . '</strong> <select name="heartbeat">';
		foreach ( array( 'default' => __( 'WordPress default (every 15–60 s)', 'sidcraft-autoseo' ), 'slow' => __( 'Every 2 minutes', 'sidcraft-autoseo' ), 'off' => __( 'Off outside the editor', 'sidcraft-autoseo' ) ) as $k => $l ) { echo '<option value="' . esc_attr( $k ) . '"' . selected( $o['heartbeat'], $k, false ) . '>' . esc_html( $l ) . '</option>'; }
		echo '</select></label></p>';
		echo '<p><label><strong>' . esc_html__( 'Keep at most this many revisions per post', 'sidcraft-autoseo' ) . '</strong> <input type="number" min="0" max="100" name="revisions_max" value="' . esc_attr( $o['revisions_max'] ) . '" style="width:70px"></label> <span class="description">' . esc_html__( '0 = WordPress default (unlimited).', 'sidcraft-autoseo' ) . '</span></p>';
		echo '<p><button class="button button-primary">' . esc_html__( 'Save', 'sidcraft-autoseo' ) . '</button></p></form>';
		echo '<p class="description">' . esc_html__( 'Saving clears the cache and the minified files. If another caching plugin is active, use only one cache.', 'sidcraft-autoseo' ) . '</p>';
	}

	/* ---------------------------------------------------------- database */

	private static function sec_database() {
		global $wpdb; $o = Sidcraft_AutoSEO_Speed::all(); $c = Sidcraft_AutoSEO_DbClean::counts(); $items = Sidcraft_AutoSEO_DbClean::items();
		echo '<p>' . esc_html__( 'Tick what you want to clean and press the button. Nothing is removed until you do. Make a backup first if your site is important.', 'sidcraft-autoseo' ) . '</p>';
		self::form_open( 'db_run' );
		echo '<table class="widefat striped" style="max-width:900px"><thead><tr><th style="width:30px"></th><th>' . esc_html__( 'Item', 'sidcraft-autoseo' ) . '</th><th style="width:90px">' . esc_html__( 'Rows', 'sidcraft-autoseo' ) . '</th></tr></thead><tbody>';
		foreach ( $items as $k => $it ) {
			echo '<tr><td><input type="checkbox" name="keys[]" value="' . esc_attr( $k ) . '"' . ( $c[ $k ] > 0 ? ' checked' : ' disabled' ) . '></td><td><strong>' . esc_html( $it[0] ) . '</strong><br><span class="description">' . esc_html( $it[1] ) . '</span></td><td>' . (int) $c[ $k ] . '</td></tr>';
		}
		echo '</tbody></table><p><button class="button button-primary" onclick="return confirm(' . esc_attr( wp_json_encode( __( 'Delete the ticked items permanently?', 'sidcraft-autoseo' ) ) ) . ')">' . esc_html__( 'Clean selected', 'sidcraft-autoseo' ) . '</button></p></form>';
		self::form_open( 'save_db' );
		echo '<h2>' . esc_html__( 'Settings', 'sidcraft-autoseo' ) . '</h2><p><label>' . esc_html__( 'When cleaning revisions, keep the newest', 'sidcraft-autoseo' ) . ' <input type="number" min="0" max="100" name="db_keep_rev" value="' . esc_attr( $o['db_keep_rev'] ) . '" style="width:70px"> ' . esc_html__( 'per post', 'sidcraft-autoseo' ) . '</label></p>';
		echo '<p><label><strong>' . esc_html__( 'Maintenance schedule', 'sidcraft-autoseo' ) . '</strong> ' . self::days_select( 'db_days', $o['db_days'] ) . '</label><br><span class="description">' . esc_html__( 'Cleans everything above except embed caches, then compacts the tables. 1 = every day at 12:01 AM (your site’s time zone).', 'sidcraft-autoseo' ) . '</span>' . self::next_run( Sidcraft_AutoSEO_DbClean::HOOK ) . '</p><p><button class="button">' . esc_html__( 'Save settings', 'sidcraft-autoseo' ) . '</button></p></form>';
		echo '<h2>' . esc_html__( 'Tables', 'sidcraft-autoseo' ) . '</h2>';
		if ( Sidcraft_AutoSEO_DbClean::is_mysql() ) {
			$t = Sidcraft_AutoSEO_DbClean::tables(); $tot = 0; $free = 0;
			echo '<table class="widefat striped" style="max-width:900px"><thead><tr><th>' . esc_html__( 'Table', 'sidcraft-autoseo' ) . '</th><th>' . esc_html__( 'Rows', 'sidcraft-autoseo' ) . '</th><th>' . esc_html__( 'Size', 'sidcraft-autoseo' ) . '</th><th>' . esc_html__( 'Wasted space', 'sidcraft-autoseo' ) . '</th></tr></thead><tbody>';
			foreach ( $t as $r ) { $sz = (int) $r['data'] + (int) $r['idx']; $tot += $sz; $free += (int) $r['free']; echo '<tr><td><code>' . esc_html( $r['name'] ) . '</code></td><td>' . esc_html( number_format_i18n( (int) $r['nrows'] ) ) . '</td><td>' . esc_html( size_format( $sz, 1 ) ) . '</td><td>' . esc_html( size_format( (int) $r['free'], 1 ) ) . '</td></tr>'; }
			echo '</tbody><tfoot><tr><th>' . esc_html__( 'Total', 'sidcraft-autoseo' ) . '</th><th></th><th>' . esc_html( size_format( $tot, 1 ) ) . '</th><th>' . esc_html( size_format( $free, 1 ) ) . '</th></tr></tfoot></table><p>';
			self::btn( 'db_optimize', __( 'Optimize tables', 'sidcraft-autoseo' ) ); echo '</p>';
		} else {
			echo '<p class="description">' . esc_html__( 'Table sizes and Optimize are only shown for MySQL or MariaDB databases.', 'sidcraft-autoseo' ) . '</p>';
		}
		list( $tot, $top ) = Sidcraft_AutoSEO_DbClean::autoload();
		echo '<h2>' . esc_html__( 'Autoloaded options', 'sidcraft-autoseo' ) . '</h2><p>' . esc_html( sprintf( /* translators: %s: size */ __( 'WordPress reads %s of options on every single page load. The biggest ones:', 'sidcraft-autoseo' ), size_format( $tot, 1 ) ) ) . '</p>';
		echo '<table class="widefat striped" style="max-width:900px"><tbody>';
		foreach ( $top as $r ) { echo '<tr><td><code>' . esc_html( $r['name'] ) . '</code></td><td>' . esc_html( size_format( (int) $r['bytes'], 1 ) ) . '</td></tr>'; }
		echo '</tbody></table><p class="description">' . esc_html__( 'Names usually start with the plugin that owns them. If the plugin is gone, its leftover options can be removed with a tool such as WP-CLI; AutoSEO does not delete options for you.', 'sidcraft-autoseo' ) . '</p>';
	}

	/* ------------------------------------------------------------- debug */

	private static function sec_debug() {
		$o = Sidcraft_AutoSEO_Speed::all(); $h = Sidcraft_AutoSEO_Perf::health(); $col = array( 'ok' => '#1a7f37', 'warn' => '#bd5b00', 'bad' => '#b32d2e' );
		$bad = 0; $warn = 0; foreach ( $h as $r ) { if ( 'bad' === $r[2] ) { $bad++; } elseif ( 'warn' === $r[2] ) { $warn++; } }
		echo '<h2>' . esc_html__( 'Server and site health', 'sidcraft-autoseo' ) . '</h2><p>' . esc_html( sprintf( /* translators: 1: number of problems, 2: number of suggestions */ __( '%1$d problems and %2$d suggestions found. Start with the red ones.', 'sidcraft-autoseo' ), $bad, $warn ) ) . '</p>';
		echo '<table class="widefat striped" style="max-width:980px"><tbody>';
		foreach ( $h as $r ) { echo '<tr><td style="width:12px;color:' . esc_attr( $col[ $r[2] ] ) . '">●</td><td style="width:230px"><strong>' . esc_html( $r[0] ) . '</strong></td><td>' . esc_html( $r[1] ) . ( '' !== $r[3] ? '<br><span class="description">' . esc_html( $r[3] ) . '</span>' : '' ) . '</td></tr>'; }
		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Speed test', 'sidcraft-autoseo' ) . '</h2><p class="description">' . esc_html__( 'Loads your home page three times from the server itself and shows how long each took. The second visit shows the page cache at work.', 'sidcraft-autoseo' ) . '</p>';
		self::btn( 'speed_test', __( 'Run speed test', 'sidcraft-autoseo' ), 'button button-primary' );
		$t = get_transient( 'sidcraft_autoseo_speedtest' );
		if ( is_array( $t ) ) {
			echo '<table class="widefat striped" style="max-width:700px"><thead><tr><th></th><th>' . esc_html__( 'Time', 'sidcraft-autoseo' ) . '</th><th>' . esc_html__( 'Size', 'sidcraft-autoseo' ) . '</th><th></th></tr></thead><tbody>';
			foreach ( $t as $r ) { echo '<tr><td>' . esc_html( $r[0] ) . '</td><td>' . ( $r[1] ? esc_html( number_format_i18n( $r[1] ) . ' ms' ) : '–' ) . '</td><td>' . esc_html( $r[2] ? size_format( $r[2], 1 ) : '–' ) . '</td><td>' . esc_html( $r[3] ) . '</td></tr>'; }
			echo '</tbody></table>';
		}

		self::form_open( 'save_debug' );
		echo '<h2>' . esc_html__( 'Find slow pages', 'sidcraft-autoseo' ) . '</h2>';
		self::check( 'profile', __( 'Measure every page load', 'sidcraft-autoseo' ), $o['profile'], __( 'Shows load time, database queries and memory in the top admin bar (administrators only) and records pages slower than the limit below.', 'sidcraft-autoseo' ) );
		echo '<p><label>' . esc_html__( 'Record pages slower than', 'sidcraft-autoseo' ) . ' <input type="number" min="200" max="60000" step="100" name="slow_ms" value="' . esc_attr( $o['slow_ms'] ) . '" style="width:90px"> ms</label> <button class="button">' . esc_html__( 'Save', 'sidcraft-autoseo' ) . '</button></p></form>';
		$sl = Sidcraft_AutoSEO_Perf::slow_log();
		if ( $sl ) {
			echo '<table class="widefat striped" style="max-width:980px"><thead><tr><th>' . esc_html__( 'When', 'sidcraft-autoseo' ) . '</th><th>' . esc_html__( 'Address', 'sidcraft-autoseo' ) . '</th><th>' . esc_html__( 'Time', 'sidcraft-autoseo' ) . '</th><th>' . esc_html__( 'Queries', 'sidcraft-autoseo' ) . '</th><th>' . esc_html__( 'Memory', 'sidcraft-autoseo' ) . '</th></tr></thead><tbody>';
			foreach ( array_slice( $sl, 0, 25 ) as $r ) { echo '<tr><td>' . esc_html( human_time_diff( (int) $r['t'] ) . ' ' . __( 'ago', 'sidcraft-autoseo' ) ) . '</td><td style="word-break:break-all"><code>' . esc_html( $r['url'] ) . '</code> ' . ( 'admin' === $r['where'] ? '<em>(admin)</em>' : '' ) . '</td><td>' . esc_html( number_format_i18n( (int) $r['ms'] ) ) . ' ms</td><td>' . (int) $r['q'] . '</td><td>' . esc_html( size_format( (int) $r['mem'], 0 ) ) . '</td></tr>'; }
			echo '</tbody></table><p>'; self::btn( 'slow_clear', __( 'Clear this list', 'sidcraft-autoseo' ) ); echo '</p>';
		}

		echo '<h2>' . esc_html__( 'Error log', 'sidcraft-autoseo' ) . '</h2>';
		$lg = Sidcraft_AutoSEO_Perf::read_log();
		if ( ! $lg['exists'] ) {
			echo '<p>' . esc_html__( 'There is no error log yet. To start recording PHP errors, add these lines to wp-config.php above the line that says “That’s all, stop editing!”, then reload this page after visiting your site:', 'sidcraft-autoseo' ) . '</p><pre style="background:#f6f7f7;padding:10px;max-width:600px">define( \'WP_DEBUG\', true );' . "\n" . 'define( \'WP_DEBUG_LOG\', true );' . "\n" . 'define( \'WP_DEBUG_DISPLAY\', false );</pre><p class="description">' . esc_html__( 'Turn WP_DEBUG back off when you are done.', 'sidcraft-autoseo' ) . '</p>';
		} else {
			echo '<p>' . esc_html( sprintf( /* translators: 1: file, 2: size */ __( 'Reading the end of %1$s (%2$s). Identical errors are grouped.', 'sidcraft-autoseo' ), str_replace( ABSPATH, '', $lg['path'] ), size_format( $lg['size'], 1 ) ) ) . '</p>';
			if ( $lg['by'] ) { echo '<p><strong>' . esc_html__( 'Errors by source:', 'sidcraft-autoseo' ) . '</strong> '; $p = array(); foreach ( $lg['by'] as $k => $v ) { $p[] = esc_html( $k ) . ' (' . (int) $v . ')'; } echo wp_kses( implode( ' · ', $p ), array() ) . '</p>'; }
			if ( $lg['groups'] ) {
				$lc = array( 'fatal' => '#b32d2e', 'warning' => '#bd5b00', 'deprecated' => '#646970', 'notice' => '#646970' );
				echo '<table class="widefat striped" style="max-width:1100px"><thead><tr><th style="width:80px">' . esc_html__( 'Level', 'sidcraft-autoseo' ) . '</th><th>' . esc_html__( 'Message', 'sidcraft-autoseo' ) . '</th><th style="width:60px">' . esc_html__( 'Times', 'sidcraft-autoseo' ) . '</th><th style="width:170px">' . esc_html__( 'Last', 'sidcraft-autoseo' ) . '</th></tr></thead><tbody>';
				foreach ( $lg['groups'] as $g ) { echo '<tr><td style="color:' . esc_attr( $lc[ $g['lvl'] ] ) . ';font-weight:600">' . esc_html( $g['lvl'] ) . '</td><td style="word-break:break-word"><code style="font-size:12px">' . esc_html( mb_substr( $g['msg'], 0, 400 ) ) . '</code><br><span class="description">' . esc_html( $g['src'] ) . '</span></td><td>' . (int) $g['n'] . '</td><td>' . esc_html( $g['last'] ) . '</td></tr>'; }
				echo '</tbody></table>';
			} else { echo '<p>' . esc_html__( 'The log is empty. Nothing to fix.', 'sidcraft-autoseo' ) . '</p>'; }
			self::btn( 'log_clear', __( 'Clear the error log', 'sidcraft-autoseo' ), 'button', __( 'Empty the error log file?', 'sidcraft-autoseo' ) );
		}
	}

	/* ----------------------------------------------------------- actions */

	public static function handle() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Not allowed.', 'sidcraft-autoseo' ), 403 ); }
		check_admin_referer( 'sidcraft_autoseo_speed' );
		$do = isset( $_REQUEST['do'] ) ? sanitize_key( wp_unslash( $_REQUEST['do'] ) ) : '';
		$sec = 'cache'; $msg = '';
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified above by check_admin_referer().
		switch ( $do ) {
			case 'save_cache': Sidcraft_AutoSEO_Speed::save( wp_unslash( $_POST ), 'cache' ); $msg = __( 'Saved. The cache was cleared.', 'sidcraft-autoseo' ); break; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- every field is sanitised in Speed::save().
			case 'save_db': Sidcraft_AutoSEO_Speed::save( wp_unslash( $_POST ), 'db' ); $sec = 'database'; $msg = __( 'Saved.', 'sidcraft-autoseo' ); break; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			case 'save_debug': Sidcraft_AutoSEO_Speed::save( wp_unslash( $_POST ), 'debug' ); $sec = 'debug'; $msg = __( 'Saved.', 'sidcraft-autoseo' ); break; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			case 'clear_cache': $n = Sidcraft_AutoSEO_Cache::clear_all(); $msg = sprintf( /* translators: %d: number of files */ __( 'Page cache cleared (%d files).', 'sidcraft-autoseo' ), $n ); break;
			case 'preload': wp_schedule_single_event( time() + 5, Sidcraft_AutoSEO_Cache::HOOK, array( 0 ) ); $msg = __( 'Preloading has started in the background.', 'sidcraft-autoseo' ); break;
			case 'clear_min': $n = Sidcraft_AutoSEO_Minify::clear(); Sidcraft_AutoSEO_Cache::clear_all(); $msg = sprintf( /* translators: %d: number of files */ __( 'Minified files removed (%d). They are made again when needed.', 'sidcraft-autoseo' ), $n ); break;
			case 'db_run':
				$sec = 'database'; $keys = isset( $_POST['keys'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['keys'] ) ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised by array_map.
				$res = Sidcraft_AutoSEO_DbClean::run( $keys ); $items = Sidcraft_AutoSEO_DbClean::items(); $p = array();
				foreach ( $res as $k => $n ) { $p[] = $items[ $k ][0] . ': ' . (int) $n; }
				$msg = $p ? __( 'Cleaned.', 'sidcraft-autoseo' ) . ' ' . implode( ' · ', $p ) : __( 'Nothing was selected.', 'sidcraft-autoseo' ); break;
			case 'db_optimize': $sec = 'database'; $n = Sidcraft_AutoSEO_DbClean::optimize(); $msg = sprintf( /* translators: %d: number of tables */ __( 'Optimized %d tables.', 'sidcraft-autoseo' ), $n ); break;
			case 'speed_test': $sec = 'debug'; set_transient( 'sidcraft_autoseo_speedtest', Sidcraft_AutoSEO_Perf::speed_test(), HOUR_IN_SECONDS ); $msg = __( 'Speed test finished.', 'sidcraft-autoseo' ); break;
			case 'slow_clear': $sec = 'debug'; delete_option( Sidcraft_AutoSEO_Perf::SLOW ); $msg = __( 'Cleared.', 'sidcraft-autoseo' ); break;
			case 'log_clear': $sec = 'debug'; $msg = Sidcraft_AutoSEO_Perf::clear_log() ? __( 'The error log was emptied.', 'sidcraft-autoseo' ) : __( 'The error log could not be emptied (file permissions).', 'sidcraft-autoseo' ); break;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		if ( isset( $_GET['back'] ) && 'front' === sanitize_key( wp_unslash( $_GET['back'] ) ) ) { wp_safe_redirect( home_url( '/' ) ); exit; }
		wp_safe_redirect( self::url( $sec, array( 'done' => rawurlencode( $msg ) ) ) );
		exit;
	}
}
