<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Dashboard, settings, redirect manager and the optional per-post box. */
class Sidcraft_AutoSEO_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_post_sidcraft_autoseo_action', array( __CLASS__, 'action' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'metabox' ) );
		add_action( 'save_post', array( __CLASS__, 'save_box' ), 10, 2 );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
		foreach ( Sidcraft_AutoSEO_Util::post_types() as $pt ) {
			add_filter( "manage_{$pt}_posts_columns", array( __CLASS__, 'col' ) );
			add_action( "manage_{$pt}_posts_custom_column", array( __CLASS__, 'col_cell' ), 10, 2 );
		}
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'styles' ) );
	}

	public static function styles() {
		$css = '.sidcraft-autoseo-b{display:inline-block;min-width:34px;text-align:center;padding:2px 8px;border-radius:12px;color:#fff;font-weight:600}.sidcraft-autoseo-g{background:#1a7f37}.sidcraft-autoseo-o{background:#d97706}.sidcraft-autoseo-r{background:#cf222e}.sidcraft-autoseo-card{background:#fff;border:1px solid #ccd0d4;border-radius:8px;padding:16px 20px;margin:0 12px 12px 0;display:inline-block;vertical-align:top;min-width:180px}.sidcraft-autoseo-card b{font-size:28px;display:block}a.sidcraft-autoseo-card{text-decoration:none;color:inherit}a.sidcraft-autoseo-card:hover{border-color:#2271b1;box-shadow:0 1px 6px rgba(0,0,0,.15)}.sidcraft-autoseo-card small{display:block;color:#2271b1;margin-top:4px}.sidcraft-autoseo-banner{background:#fff8e5;border:1px solid #dba617;border-radius:6px;padding:14px 18px;margin:14px 0;max-width:900px}.sidcraft-autoseo-wrap table.widefat td,.sidcraft-autoseo-wrap table.widefat th{vertical-align:top}'.
			'.sidcraft-autoseo-tone-g{border-left:6px solid #1a7f37;background:#f0faf3}.sidcraft-autoseo-tone-g b{color:#1a7f37}.sidcraft-autoseo-tone-o{border-left:6px solid #d97706;background:#fff7ed}.sidcraft-autoseo-tone-o b{color:#c2410c}.sidcraft-autoseo-tone-r{border-left:6px solid #cf222e;background:#fff1f2}.sidcraft-autoseo-tone-r b{color:#cf222e}';
		wp_register_style( 'sidcraft-autoseo-admin', false, array(), SIDCRAFT_AUTOSEO_VERSION );
		wp_enqueue_style( 'sidcraft-autoseo-admin' );
		wp_add_inline_style( 'sidcraft-autoseo-admin', $css );
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen check.
		if ( 'sidcraft-autoseo' === $page && current_user_can( 'manage_options' ) ) {
			$resume = (bool) get_transient( 'sidcraft_autoseo_resume' ) && Sidcraft_AutoSEO_Audit::pending() > 0;
			wp_register_script( 'sidcraft-autoseo-admin', false, array(), SIDCRAFT_AUTOSEO_VERSION, true );
			wp_enqueue_script( 'sidcraft-autoseo-admin' );
			wp_add_inline_script( 'sidcraft-autoseo-admin', 'window.sidcraftAutoSEO=' . wp_json_encode( array(
				'ajax' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'sidcraft_autoseo_audit' ), 'resume' => $resume,
				'checking' => __( 'Checking pages…', 'sidcraft-autoseo' ), 'done' => __( 'Audit finished.', 'sidcraft-autoseo' ),
				'retry' => __( 'The server did not answer in time. Press “Run audit now” to carry on where it stopped.', 'sidcraft-autoseo' ),
			) ) . ';' . self::audit_js() );
		}
	}

	private static function audit_js() {
		return '(function(){var c=window.sidcraftAutoSEO,b=document.getElementById("sidcraft-autoseo-run");if(!c||!b)return;var box=document.getElementById("sidcraft-autoseo-progress"),bar=document.getElementById("sidcraft-autoseo-bar"),txt=document.getElementById("sidcraft-autoseo-txt"),running=false,carry=false;'
		. 'function sleep(ms){return new Promise(function(r){setTimeout(r,ms)})}'
		. 'async function step(reset){for(var t=0;t<3;t++){try{var f=new FormData();f.append("action","sidcraft_autoseo_audit");f.append("_ajax_nonce",c.nonce);if(reset)f.append("reset","1");var r=await fetch(c.ajax,{method:"POST",body:f,credentials:"same-origin"});var j=JSON.parse(await r.text());if(j.success)return j.data}catch(e){}await sleep(1500)}throw new Error(c.retry)}'
		. 'async function run(reset){if(running)return;running=true;b.disabled=true;box.hidden=false;txt.textContent=c.checking;try{var d=await step(reset);for(;;){var n=Math.max(1,d.total);bar.max=n;bar.value=Math.max(0,n-d.left);txt.textContent=c.checking+" "+bar.value+" / "+n;if(d.done)break;d=await step(false)}txt.textContent=c.done;setTimeout(function(){location.reload()},700)}catch(e){txt.textContent=e.message;carry=true;b.disabled=false;running=false}}'
		. 'b.addEventListener("click",function(){run(!carry)});if(c.resume)run(false);})();';
	}

	public static function badge( $score ) {
		$score = (int) $score;
		$c = $score >= 80 ? 'sidcraft-autoseo-g' : ( $score >= 60 ? 'sidcraft-autoseo-o' : 'sidcraft-autoseo-r' );
		return '<span class="sidcraft-autoseo-b ' . $c . '">' . $score . '</span>';
	}

	public static function notice() {
		$other     = Sidcraft_AutoSEO_Util::other_seo_plugin();
		$page_slug = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen check.
		if ( $other && current_user_can( 'manage_options' ) && 0 === strpos( $page_slug, 'sidcraft-autoseo' ) ) {
			echo '<div class="notice notice-warning"><p>' . esc_html( sprintf( '%s is active, so Sidcraft AutoSEO is not printing titles, descriptions, social cards, schema or sitemap changes to avoid duplicates. Auditing, redirects, image alt text and IndexNow still run.', $other ) ) . '</p></div>';
		}
	}

	/* ---------- menu ---------- */

	public static function menu() {
		add_menu_page( 'Sidcraft AutoSEO', 'Sidcraft AutoSEO', 'manage_options', 'sidcraft-autoseo', array( __CLASS__, 'page' ), 'dashicons-chart-line', 80 );
	}

	public static function register() {
		register_setting( 'sidcraft_autoseo_settings_group', Sidcraft_AutoSEO_Settings::OPTION, array( 'type' => 'array', 'sanitize_callback' => array( 'Sidcraft_AutoSEO_Settings', 'sanitize' ), 'default' => Sidcraft_AutoSEO_Settings::defaults() ) );
	}

	private static function tabs() {
		return array( 'overview' => __( 'Overview', 'sidcraft-autoseo' ), 'pages' => __( 'Pages & Posts', 'sidcraft-autoseo' ), 'links' => __( 'Broken Links', 'sidcraft-autoseo' ), 'urls' => __( 'Broken URLs', 'sidcraft-autoseo' ), 'redirects' => __( 'Redirects & 404s', 'sidcraft-autoseo' ), 'settings' => __( 'Settings', 'sidcraft-autoseo' ) );
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview'; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! isset( self::tabs()[ $tab ] ) && 'suggest' !== $tab ) { $tab = 'overview'; }
		echo '<div class="wrap sidcraft-autoseo-wrap"><h1>Sidcraft AutoSEO</h1><h2 class="nav-tab-wrapper">';
		foreach ( self::tabs() as $k => $label ) {
			printf( '<a class="nav-tab %s" href="%s">%s</a>', $k === $tab ? 'nav-tab-active' : '', esc_url( admin_url( 'admin.php?page=sidcraft-autoseo&tab=' . $k ) ), esc_html( $label ) );
		}
		echo '</h2>';
		if ( isset( $_GET['done'] ) ) { echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['done'] ) ) ) . '</p></div>'; } // phpcs:ignore WordPress.Security.NonceVerification
		call_user_func( array( __CLASS__, 'tab_' . $tab ) );
		echo '</div>';
	}

	private static function button_html( $do, $label, $extra = array(), $class = 'button', $confirm = '' ) { ob_start(); self::button( $do, $label, $extra, $class, $confirm ); return ob_get_clean(); }

	private static function button( $do, $label, $extra = array(), $class = 'button', $confirm = '' ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin:0 8px 8px 0"' . ( '' !== $confirm ? ' onsubmit="return confirm(' . esc_attr( wp_json_encode( $confirm ) ) . ');"' : '' ) . '>';
		wp_nonce_field( 'sidcraft_autoseo_action' );
		echo '<input type="hidden" name="action" value="sidcraft_autoseo_action"><input type="hidden" name="do" value="' . esc_attr( $do ) . '">';
		foreach ( $extra as $k => $v ) { echo '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">'; }
		echo '<button class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</button></form>';
	}

	/* ---------- tabs ---------- */

	private static function pages_url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => 'sidcraft-autoseo', 'tab' => 'pages' ), $args ), admin_url( 'admin.php' ) );
	}

	private static function tab_overview() {
		$s = Sidcraft_AutoSEO_Audit::summary();
		$r = Sidcraft_AutoSEO_Redirects::counts();
		$last = (int) get_option( 'sidcraft_autoseo_last_run' );
		$in = get_option( 'sidcraft_autoseo_indexnow_last' );
		echo '<p>' . esc_html__( 'Sidcraft AutoSEO works on its own: every page and post already gets a title, description, social card, structured data, sitemap entry, image alt text and redirect protection. This page shows how it is doing and lets you fix the rest with one click.', 'sidcraft-autoseo' ) . '</p>';
		echo '<div>';
		printf( '<a class="sidcraft-autoseo-card" href="%s">%s<b>%s</b>%s<small>%s</small></a>', esc_url( self::pages_url() ), esc_html__( 'Average score', 'sidcraft-autoseo' ), wp_kses_post( self::badge( $s['avg'] ) ), esc_html( sprintf( /* translators: %d: number of pages */ __( '%d pages and posts', 'sidcraft-autoseo' ), (int) $s['total'] ) ), esc_html__( 'View all →', 'sidcraft-autoseo' ) );
		printf( '<a class="sidcraft-autoseo-card sidcraft-autoseo-tone-g" href="%s">%s<b>%d</b>%s<small>%s</small></a>', esc_url( self::pages_url( array( 'band' => 'good' ) ) ), esc_html__( 'Good (80+)', 'sidcraft-autoseo' ), (int) $s['good'], esc_html__( 'Nothing urgent', 'sidcraft-autoseo' ), esc_html__( 'View →', 'sidcraft-autoseo' ) );
		printf( '<a class="sidcraft-autoseo-card sidcraft-autoseo-tone-o" href="%s">%s<b>%d</b>%s<small>%s</small></a>', esc_url( self::pages_url( array( 'band' => 'ok' ) ) ), esc_html__( 'Needs work (60–79)', 'sidcraft-autoseo' ), (int) $s['ok'], esc_html__( 'Click to see and fix', 'sidcraft-autoseo' ), esc_html__( 'View and fix →', 'sidcraft-autoseo' ) );
		printf( '<a class="sidcraft-autoseo-card sidcraft-autoseo-tone-r" href="%s">%s<b>%d</b>%s<small>%s</small></a>', esc_url( self::pages_url( array( 'band' => 'poor' ) ) ), esc_html__( 'Poor (under 60)', 'sidcraft-autoseo' ), (int) $s['bad'], esc_html__( 'Click to see and fix', 'sidcraft-autoseo' ), esc_html__( 'View and fix →', 'sidcraft-autoseo' ) );
		$bl = Sidcraft_AutoSEO_Links::count();
		printf( '<a class="sidcraft-autoseo-card %s" href="%s">%s<b>%d</b>%s<small>%s</small></a>', $bl ? 'sidcraft-autoseo-tone-r' : 'sidcraft-autoseo-tone-g', esc_url( add_query_arg( array( 'page' => 'sidcraft-autoseo', 'tab' => 'links' ), admin_url( 'admin.php' ) ) ), esc_html__( 'Broken links', 'sidcraft-autoseo' ), (int) $bl, esc_html( $bl ? __( 'inside your pages', 'sidcraft-autoseo' ) : __( 'none found', 'sidcraft-autoseo' ) ), esc_html__( 'Open Broken Links →', 'sidcraft-autoseo' ) );
		printf( '<div class="sidcraft-autoseo-card">%s<b>%d</b>%s</div>', esc_html__( 'Redirects', 'sidcraft-autoseo' ), (int) $r['redirects'], esc_html( sprintf( /* translators: 1: automatic redirects, 2: times used */ __( '%1$d automatic · used %2$d times', 'sidcraft-autoseo' ), (int) $r['auto'], (int) $r['hits'] ) ) );
		printf( '<a class="sidcraft-autoseo-card %s" href="%s">%s<b>%d</b>%s<small>%s</small></a>', $r['missing'] > 0 ? 'sidcraft-autoseo-tone-o' : 'sidcraft-autoseo-tone-g', esc_url( add_query_arg( array( 'page' => 'sidcraft-autoseo', 'tab' => 'urls' ), admin_url( 'admin.php' ) ) ), esc_html__( 'Broken URLs seen', 'sidcraft-autoseo' ), (int) $r['missing'], esc_html( $r['missing'] ? __( 'not yet fixed', 'sidcraft-autoseo' ) : __( 'none recorded', 'sidcraft-autoseo' ) ), esc_html__( 'View and fix →', 'sidcraft-autoseo' ) );
		echo '</div>';
		if ( ( $s['ok'] + $s['bad'] ) > 0 ) {
			$fixable = 0;
			foreach ( array_keys( Sidcraft_AutoSEO_Fixer::catalog() ) as $fid ) { $fixable += count( Sidcraft_AutoSEO_Fixer::posts_with_issue( $fid, 1 ) ); }
			echo '<div class="sidcraft-autoseo-banner"><strong>' . esc_html( sprintf( /* translators: %d: number of pages */ _n( '%d page needs work.', '%d pages need work.', $s['ok'] + $s['bad'], 'sidcraft-autoseo' ), $s['ok'] + $s['bad'] ) ) . '</strong> ';
			if ( $fixable > 0 ) {
				echo esc_html__( 'Some problems can be fixed automatically; others need you to write something. The table below says which is which.', 'sidcraft-autoseo' ) . '<br><br>';
				self::button( 'fix_all', __( 'Fix everything that can be fixed automatically', 'sidcraft-autoseo' ), array(), 'button button-primary button-hero' );
				echo '<p class="description" style="margin:4px 0 0">' . esc_html__( 'Adds related-page links, writes missing excerpts and image alt text, then re-checks every page. It never changes web addresses or rewrites your text.', 'sidcraft-autoseo' ) . '</p>';
			} else {
				echo esc_html__( 'Everything that can be fixed automatically has been fixed. The rest needs you to add or edit content: click a number in the table below to see those pages.', 'sidcraft-autoseo' );
			}
			echo '</div>';
		}
		echo '<h2>' . esc_html__( 'What is most worth looking at', 'sidcraft-autoseo' ) . '</h2>';
		if ( $s['top'] ) {
			$cat = Sidcraft_AutoSEO_Fixer::catalog();
			echo '<table class="widefat striped" style="max-width:980px"><thead><tr><th>' . esc_html__( 'Issue', 'sidcraft-autoseo' ) . '</th><th>' . esc_html__( 'Pages', 'sidcraft-autoseo' ) . '</th><th>' . esc_html__( 'What to do', 'sidcraft-autoseo' ) . '</th></tr></thead><tbody>';
			foreach ( $s['top'] as $id => $t ) {
				echo '<tr><td>' . esc_html( $t['msg'] ) . '</td><td><a href="' . esc_url( self::pages_url( array( 'issue' => $id ) ) ) . '">' . (int) $t['n'] . '</a></td><td>';
				if ( isset( $cat[ $id ] ) ) {
					self::button( 'fix', $cat[ $id ]['label'], array( 'what' => $id ), 'button button-primary', $cat[ $id ]['confirm'] );
					echo '<br><span class="description">' . esc_html( $cat[ $id ]['hint'] ) . '</span>';
				} else {
					echo esc_html( Sidcraft_AutoSEO_Fixer::advice( $id ) );
					echo ' <a href="' . esc_url( self::pages_url( array( 'issue' => $id ) ) ) . '">' . esc_html__( 'See pages', 'sidcraft-autoseo' ) . '</a>';
				}
				echo '</td></tr>';
			}
			echo '</tbody></table>';
		} else { echo '<p>' . esc_html__( 'No issues found yet. Press “Run audit now” to check everything.', 'sidcraft-autoseo' ) . '</p>'; }
		echo '<h2>' . esc_html__( 'Status', 'sidcraft-autoseo' ) . '</h2><table class="widefat striped" style="max-width:980px"><tbody>';
		echo '<tr><td>' . esc_html__( 'Sitemap', 'sidcraft-autoseo' ) . '</td><td><a href="' . esc_url( home_url( '/wp-sitemap.xml' ) ) . '" target="_blank" rel="noopener">' . esc_html( home_url( '/wp-sitemap.xml' ) ) . '</a></td></tr>';
		$status = __( 'Off', 'sidcraft-autoseo' );
		if ( Sidcraft_AutoSEO_Settings::on( 'indexnow' ) ) {
			if ( ! Sidcraft_AutoSEO_IndexNow::allowed() ) { $status = __( 'On, but paused here because this looks like a local or private site.', 'sidcraft-autoseo' ); }
			elseif ( is_array( $in ) ) { /* translators: 1: time ago, 2: number of URLs */ $status = sprintf( __( 'On. Last sent %1$s ago (%2$d URLs).', 'sidcraft-autoseo' ), human_time_diff( $in['time'] ), $in['count'] ); }
			else { $status = __( 'On. Waiting for the next publish.', 'sidcraft-autoseo' ); }
		}
		echo '<tr><td>' . esc_html__( 'Search engine notification (IndexNow)', 'sidcraft-autoseo' ) . '</td><td>' . esc_html( $status ) . '</td></tr>';
		echo '<tr><td>' . esc_html__( 'Last full audit', 'sidcraft-autoseo' ) . '</td><td>' . esc_html( ( $last ? human_time_diff( $last ) . ' ' . __( 'ago', 'sidcraft-autoseo' ) : __( 'Not yet', 'sidcraft-autoseo' ) ) . ' · ' . __( 'waiting to check:', 'sidcraft-autoseo' ) . ' ' . (int) Sidcraft_AutoSEO_Audit::pending() ) . '</td></tr>';
		echo '</tbody></table><p style="margin-top:14px">';
		echo '<button type="button" class="button" id="sidcraft-autoseo-run" style="margin:0 8px 8px 0">' . esc_html__( 'Run audit now', 'sidcraft-autoseo' ) . '</button>';
		self::button( 'alt', __( 'Fix missing image alt text now', 'sidcraft-autoseo' ) );
		echo '</p><p id="sidcraft-autoseo-progress" hidden style="max-width:520px"><progress id="sidcraft-autoseo-bar" value="0" max="1" style="width:100%;height:18px"></progress><br><span id="sidcraft-autoseo-txt" class="description"></span></p>';
	}

	private static function tab_pages() {
		$paged = max( 1, isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1 ); // phpcs:ignore WordPress.Security.NonceVerification
		$band  = isset( $_GET['band'] ) ? sanitize_key( wp_unslash( $_GET['band'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$issue = isset( $_GET['issue'] ) ? sanitize_key( wp_unslash( $_GET['issue'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$meta  = array( 'relation' => 'AND', array( 'key' => '_sidcraft_autoseo_score', 'compare' => 'EXISTS' ) );
		$title = __( 'All pages and posts, lowest score first.', 'sidcraft-autoseo' );
		if ( 'good' === $band ) { $meta[] = array( 'key' => '_sidcraft_autoseo_score', 'value' => 80, 'compare' => '>=', 'type' => 'NUMERIC' ); $title = __( 'Good pages (80 and above).', 'sidcraft-autoseo' ); }
		if ( 'ok' === $band ) { $meta[] = array( 'key' => '_sidcraft_autoseo_score', 'value' => array( 60, 79 ), 'compare' => 'BETWEEN', 'type' => 'NUMERIC' ); $title = __( 'Pages that need work (60–79).', 'sidcraft-autoseo' ); }
		if ( 'poor' === $band ) { $meta[] = array( 'key' => '_sidcraft_autoseo_score', 'value' => 60, 'compare' => '<', 'type' => 'NUMERIC' ); $title = __( 'Poor pages (under 60).', 'sidcraft-autoseo' ); }
		if ( '' !== $issue ) { $meta[] = array( 'key' => '_sidcraft_autoseo_issues', 'value' => '"id":"' . $issue . '"', 'compare' => 'LIKE' ); $title = __( 'Pages with this issue.', 'sidcraft-autoseo' ); }
		$q = new WP_Query( array(
			'post_type' => Sidcraft_AutoSEO_Util::post_types(), 'post_status' => 'publish', 'posts_per_page' => 40, 'paged' => $paged,
			'meta_key' => '_sidcraft_autoseo_score', 'orderby' => 'meta_value_num', 'order' => 'ASC', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- admin list sorted by score.
			'meta_query' => $meta, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- admin list filter.
		) );
		echo '<p><strong>' . esc_html( $title ) . '</strong> ' . ( $band || $issue ? '<a href="' . esc_url( self::pages_url() ) . '">' . esc_html__( 'Show all', 'sidcraft-autoseo' ) . '</a>' : '' ) . '</p>';
		$cat = Sidcraft_AutoSEO_Fixer::catalog();
		if ( $issue && isset( $cat[ $issue ] ) ) {
			self::button( 'fix', $cat[ $issue ]['label'], array( 'what' => $issue ), 'button button-primary', $cat[ $issue ]['confirm'] );
			echo '<span class="description">' . esc_html( $cat[ $issue ]['hint'] ) . '</span>';
		} elseif ( 'ok' === $band || 'poor' === $band ) {
			self::button( 'fix_all', __( 'Fix everything that can be fixed automatically', 'sidcraft-autoseo' ), array(), 'button button-primary' );
		}
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Page', 'sidcraft-autoseo' ) . '</th><th>' . esc_html__( 'Type', 'sidcraft-autoseo' ) . '</th><th>' . esc_html__( 'Score', 'sidcraft-autoseo' ) . '</th><th>' . esc_html__( 'Main phrase', 'sidcraft-autoseo' ) . '</th><th>' . esc_html__( 'What could be better', 'sidcraft-autoseo' ) . '</th><th></th></tr></thead><tbody>';
		foreach ( $q->posts as $p ) {
			$issues = json_decode( (string) get_post_meta( $p->ID, '_sidcraft_autoseo_issues', true ), true );
			$issues = is_array( $issues ) ? $issues : array();
			$lines  = array();
			$fixable = false;
			foreach ( array_slice( $issues, 0, 4 ) as $i ) {
				$tag = isset( $cat[ $i['id'] ] ) ? ' <em>(' . esc_html__( 'can be fixed automatically', 'sidcraft-autoseo' ) . ')</em>' : '';
				$lines[] = esc_html( $i['msg'] ) . $tag;
			}
			foreach ( $issues as $i ) { if ( isset( $cat[ $i['id'] ] ) ) { $fixable = true; } }
			echo '<tr><td><a href="' . esc_url( get_edit_post_link( $p->ID ) ) . '"><strong>' . esc_html( get_the_title( $p ) ) . '</strong></a><br><a href="' . esc_url( get_permalink( $p ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'View', 'sidcraft-autoseo' ) . '</a></td><td>' . esc_html( $p->post_type ) . '</td><td>' . wp_kses_post( self::badge( (int) get_post_meta( $p->ID, '_sidcraft_autoseo_score', true ) ) ) . '</td><td>' . esc_html( Sidcraft_AutoSEO_Meta::focus_for_post( $p->ID ) ) . '</td><td>' . ( $lines ? wp_kses( implode( '<br>', $lines ), array( 'em' => array(), 'br' => array() ) ) : '—' ) . '</td><td>';
			if ( $fixable ) { self::button( 'fix_post', __( 'Fix automatically', 'sidcraft-autoseo' ), array( 'id' => $p->ID ) ); }
			if ( $issues ) { echo '<a class="button" href="' . esc_url( add_query_arg( array( 'page' => 'sidcraft-autoseo', 'tab' => 'suggest', 'id' => $p->ID ), admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Suggestions', 'sidcraft-autoseo' ) . '</a>'; }
			echo '</td></tr>';
		}
		if ( ! $q->posts ) { echo '<tr><td colspan="6">' . esc_html__( 'Nothing here. Go to Overview and press “Run audit now”.', 'sidcraft-autoseo' ) . '</td></tr>'; }
		echo '</tbody></table>';
		$links = paginate_links( array( 'base' => add_query_arg( 'paged', '%#%' ), 'format' => '', 'current' => $paged, 'total' => max( 1, (int) $q->max_num_pages ) ) );
		if ( $links ) { echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( $links ) . '</div></div>'; }
	}

	private static function tab_links() {
		$st    = Sidcraft_AutoSEO_Links::state();
		$items = Sidcraft_AutoSEO_Links::visible();
		echo '<p>' . esc_html__( 'Finds links inside your pages and posts that lead nowhere, so visitors and search engines do not hit dead ends. Fix each one with a click.', 'sidcraft-autoseo' ) . '</p>';
		self::button( 'links_scan', __( 'Scan for broken links now', 'sidcraft-autoseo' ), array(), 'button button-primary' );
		if ( $st['finished'] ) {
			/* translators: 1: date and time, 2: number of pages, 3: number of links. */
			echo '<span class="description">' . esc_html( sprintf( __( 'Last scan %1$s: %2$d pages, %3$d links checked.', 'sidcraft-autoseo' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $st['finished'] ), (int) $st['posts'], (int) $st['links'] ) ) . '</span>';
		} elseif ( $st['started'] ) {
			/* translators: %d: number of pages checked so far. */
			echo '<span class="description">' . esc_html( sprintf( __( 'Scan in progress… %d pages checked so far. Refresh in a minute.', 'sidcraft-autoseo' ), (int) $st['posts'] ) ) . '</span>';
		} else {
			echo '<span class="description">' . esc_html__( 'No scan has run yet. Click the button; the scan also repeats every day.', 'sidcraft-autoseo' ) . '</span>';
		}
		if ( ! Sidcraft_AutoSEO_Settings::on( 'check_external' ) ) {
			echo '<p class="description">' . esc_html__( 'Only links to your own site are checked. To also check links to other websites, tick the option under Settings.', 'sidcraft-autoseo' ) . '</p>';
		}
		if ( ! $items ) {
			if ( $st['finished'] ) { echo '<div class="sidcraft-autoseo-card sidcraft-autoseo-tone-g" style="display:block;max-width:520px"><b>0</b>' . esc_html__( 'No broken links found. Nice.', 'sidcraft-autoseo' ) . '</div>'; }
			return;
		}
		$with = 0;
		foreach ( $items as $it ) { if ( '' !== $it['suggest'] ) { ++$with; } }
		if ( $with ) {
			echo '<div class="sidcraft-autoseo-banner"><strong>' . esc_html( sprintf( /* translators: %d: number of links */ _n( '%d broken link has a close match on your site.', '%d broken links have a close match on your site.', $with, 'sidcraft-autoseo' ), $with ) ) . '</strong><br><br>';
			self::button( 'links_fix_all', __( 'Repair them all with the suggested address', 'sidcraft-autoseo' ), array(), 'button button-primary', __( 'Replace each broken link with the suggested page? You can review the list first.', 'sidcraft-autoseo' ) );
			echo '</div>';
		}
		$reasons = Sidcraft_AutoSEO_Links::reasons();
		echo '<table class="widefat striped" style="margin-top:12px"><thead><tr><th>' . esc_html__( 'Broken link', 'sidcraft-autoseo' ) . '</th><th>' . esc_html__( 'Problem', 'sidcraft-autoseo' ) . '</th><th>' . esc_html__( 'Found on', 'sidcraft-autoseo' ) . '</th><th>' . esc_html__( 'Fix it', 'sidcraft-autoseo' ) . '</th></tr></thead><tbody>';
		foreach ( array_slice( $items, 0, 100, true ) as $id => $it ) {
			$post = get_post( $it['post_id'] );
			echo '<tr><td style="word-break:break-all"><code>' . esc_html( $it['url'] ) . '</code></td><td>' . esc_html( isset( $reasons[ $it['reason'] ] ) ? $reasons[ $it['reason'] ] : $it['reason'] ) . '</td><td>';
			if ( $post ) { echo '<a href="' . esc_url( get_edit_post_link( $post ) ) . '">' . esc_html( wp_strip_all_tags( get_the_title( $post ) ) ) . '</a>'; }
			echo '</td><td>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'sidcraft_autoseo_action' );
			echo '<input type="hidden" name="action" value="sidcraft_autoseo_action"><input type="hidden" name="lid" value="' . esc_attr( $id ) . '">';
			echo '<input type="url" class="regular-text" name="new_url" value="' . esc_attr( $it['suggest'] ) . '" placeholder="' . esc_attr__( 'New address', 'sidcraft-autoseo' ) . '" style="width:100%;max-width:340px"><br>';
			echo '<button class="button button-primary" name="do" value="link_replace">' . esc_html__( 'Use this address', 'sidcraft-autoseo' ) . '</button> ';
			if ( $it['internal'] ) { echo '<button class="button" name="do" value="link_redirect" title="' . esc_attr__( 'Keeps the link as it is and sends anyone who follows it to the new address', 'sidcraft-autoseo' ) . '">' . esc_html__( 'Redirect old address', 'sidcraft-autoseo' ) . '</button> '; }
			echo '<button class="button" name="do" value="link_unlink" formnovalidate onclick="return confirm(' . esc_attr( wp_json_encode( __( 'Remove this link from the page? The words stay.', 'sidcraft-autoseo' ) ) ) . ');">' . esc_html__( 'Remove link', 'sidcraft-autoseo' ) . '</button> ';
			echo '<button class="button-link" name="do" value="link_ignore" formnovalidate>' . esc_html__( 'Ignore', 'sidcraft-autoseo' ) . '</button>';
			echo '</form></td></tr>';
		}
		echo '</tbody></table>';
		if ( count( $items ) > 100 ) { echo '<p class="description">' . esc_html( sprintf( /* translators: %d: number of links */ __( 'Showing the first 100 of %d. Fix some and the rest appear.', 'sidcraft-autoseo' ), count( $items ) ) ) . '</p>'; }
	}

	private static function tab_urls() {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY hits DESC, last_seen DESC LIMIT %d', Sidcraft_AutoSEO_Redirects::table404(), 100 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom plugin table.
		echo '<p>' . esc_html__( 'Addresses on your site that visitors or search engines asked for but that do not exist. Each one is a dead end. Send it to the right page with a click and it is fixed for good.', 'sidcraft-autoseo' ) . '</p>';
		echo '<p class="description">' . esc_html__( 'Near misses are repaired automatically while the setting “Repair near-miss broken URLs” is on, so what is listed here is what could not be matched with confidence.', 'sidcraft-autoseo' ) . '</p>';
		if ( ! $rows ) {
			echo '<div class="sidcraft-autoseo-card sidcraft-autoseo-tone-g" style="display:block;max-width:520px"><b>0</b>' . esc_html__( 'No broken URLs recorded. Nice.', 'sidcraft-autoseo' ) . '</div>';
			return;
		}
		$sug = array();
		$with = 0;
		foreach ( $rows as $r ) {
			$sug[ $r->id ] = Sidcraft_AutoSEO_Redirects::guess( $r->path, 70 );
			if ( $sug[ $r->id ] ) { ++$with; }
		}
		echo '<div class="sidcraft-autoseo-banner"><strong>' . esc_html( sprintf( /* translators: %d: number of addresses */ _n( '%d broken URL.', '%d broken URLs.', count( $rows ), 'sidcraft-autoseo' ), count( $rows ) ) ) . '</strong> ';
		if ( $with ) {
			echo esc_html( sprintf( /* translators: %d: number of addresses */ _n( '%d has a likely match on your site.', '%d have a likely match on your site.', $with, 'sidcraft-autoseo' ), $with ) ) . '<br><br>';
			self::button( 'urls_fix_all', __( 'Redirect them all to the suggested pages', 'sidcraft-autoseo' ), array(), 'button button-primary', __( 'Create a 301 redirect to the suggested page for every address that has one?', 'sidcraft-autoseo' ) );
		} else {
			echo esc_html__( 'None has an obvious match; choose where each should go.', 'sidcraft-autoseo' ) . '<br><br>';
		}
		self::button( 'urls_dismiss_all', __( 'Dismiss all', 'sidcraft-autoseo' ), array(), 'button', __( 'Remove every address from this list? An address comes back only if someone requests it again.', 'sidcraft-autoseo' ) );
		echo '</div>';
		echo '<table class="widefat striped" style="margin-top:12px"><thead><tr><th>' . esc_html__( 'Broken URL', 'sidcraft-autoseo' ) . '</th><th>' . esc_html__( 'Visits', 'sidcraft-autoseo' ) . '</th><th>' . esc_html__( 'Last seen', 'sidcraft-autoseo' ) . '</th><th>' . esc_html__( 'IP address', 'sidcraft-autoseo' ) . '</th><th>' . esc_html__( 'Came from', 'sidcraft-autoseo' ) . '</th><th>' . esc_html__( 'Fix it', 'sidcraft-autoseo' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$target = $sug[ $r->id ] ? $sug[ $r->id ] : home_url( '/' );
			echo '<tr><td style="word-break:break-all"><code>' . esc_html( $r->path ) . '</code></td><td>' . (int) $r->hits . '</td><td>' . esc_html( wp_date( get_option( 'date_format' ), strtotime( $r->last_seen . ' UTC' ) ) ) . '</td><td>' . self::ip_cell( $r ) . '</td><td style="word-break:break-all">';
			echo $r->referrer ? esc_html( $r->referrer ) : '<span class="description">' . esc_html__( 'direct or unknown', 'sidcraft-autoseo' ) . '</span>';
			echo '</td><td><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'sidcraft_autoseo_action' );
			echo '<input type="hidden" name="action" value="sidcraft_autoseo_action"><input type="hidden" name="uid" value="' . (int) $r->id . '">';
			echo '<input type="url" name="new_url" value="' . esc_attr( $target ) . '" style="width:100%;max-width:340px"><br>';
			echo '<button class="button button-primary" name="do" value="url_redirect">' . esc_html__( 'Redirect (301)', 'sidcraft-autoseo' ) . '</button> ';
			echo '<button class="button-link" name="do" value="url_dismiss" formnovalidate>' . esc_html__( 'Dismiss', 'sidcraft-autoseo' ) . '</button>';
			echo '</form></td></tr>';
		}
		echo '</tbody></table>';
	}

	/** Names that real search-engine crawlers resolve to (reverse DNS), by company. */
	private static function engine_hosts() {
		return array( 'Google' => array( '.googlebot.com', '.google.com' ), 'Bing' => array( '.search.msn.com' ), 'Apple' => array( '.applebot.apple.com' ), 'Yahoo' => array( '.crawl.yahoo.net' ), 'Yandex' => array( '.yandex.ru', '.yandex.net', '.yandex.com' ), 'Baidu' => array( '.baidu.com', '.baidu.jp' ), 'DuckDuckGo' => array( '.duckduckgo.com' ) );
	}

	/** Does this address really belong to a search engine? Returns array( company or '', host name or '' ). Checks the name both ways, so it cannot be faked. */
	private static function search_engine( $ip ) {
		$host = apply_filters( 'sidcraft_autoseo_rdns', null, $ip );
		if ( null === $host ) { $host = @gethostbyaddr( $ip ); } // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a failed lookup is a normal outcome.
		if ( ! is_string( $host ) || '' === $host || $host === $ip ) { return array( '', '' ); }
		$host = strtolower( rtrim( $host, '.' ) );
		foreach ( self::engine_hosts() as $name => $suffixes ) {
			foreach ( $suffixes as $suf ) {
				if ( strlen( $host ) > strlen( $suf ) && substr( $host, -strlen( $suf ) ) === $suf ) {
					$back = apply_filters( 'sidcraft_autoseo_forward', null, $host );
					if ( null === $back ) { $back = false !== strpos( $ip, ':' ) ? array() : (array) @gethostbynamel( $host ); } // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
					$want = @inet_pton( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
					foreach ( (array) $back as $b ) { if ( $want && @inet_pton( $b ) === $want ) { return array( $name, $host ); } } // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				}
			}
		}
		return array( '', $host );
	}

	/** The addresses that asked for a missing URL, each with Check, and Block or Unblock for Sidcraft Bot Shield. */
	private static function ip_cell( $r ) {
		$ips = isset( $r->ips ) && '' !== $r->ips ? explode( ',', $r->ips ) : array();
		if ( ! $ips ) { return '<span class="description">' . esc_html__( 'not recorded', 'sidcraft-autoseo' ) . '</span>'; }
		$shield = class_exists( 'Sidcraft_BotShield_Settings' ); $out = '';
		foreach ( $ips as $ip ) {
			$out .= '<div style="white-space:nowrap"><code>' . esc_html( $ip ) . '</code> ' . self::button_html( 'url_check_ip', __( 'Check', 'sidcraft-autoseo' ), array( 'ip' => $ip ), 'button-link' );
			if ( $shield ) {
				$blocked = class_exists( 'Sidcraft_BotShield_Ranges' ) && Sidcraft_BotShield_Ranges::in_list( $ip, Sidcraft_BotShield_Settings::lines( 'ip_block' ) );
				$out .= $blocked ? ' <em>' . esc_html__( 'blocked', 'sidcraft-autoseo' ) . '</em> ' . self::button_html( 'url_unblock_ip', __( 'Unblock', 'sidcraft-autoseo' ), array( 'ip' => $ip ), 'button-link' ) : ' ' . self::button_html( 'url_block_ip', __( 'Block', 'sidcraft-autoseo' ), array( 'ip' => $ip ), 'button-link', __( 'Block this address for good in Sidcraft Bot Shield?', 'sidcraft-autoseo' ) );
			}
			$out .= '</div>';
		}
		return $out;
	}

	private static function tab_suggest() {
		$id   = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen.
		$post = $id ? get_post( $id ) : null;
		if ( ! $post || ! current_user_can( 'edit_post', $id ) ) { echo '<p>' . esc_html__( 'Page not found.', 'sidcraft-autoseo' ) . '</p>'; return; }
		$r = Sidcraft_AutoSEO_Audit::analyze( $post );
		echo '<p><a href="' . esc_url( self::pages_url() ) . '">← ' . esc_html__( 'Back to Pages & Posts', 'sidcraft-autoseo' ) . '</a></p>';
		echo '<h2>' . esc_html( get_the_title( $post ) ) . ' ' . wp_kses_post( self::badge( $r['score'] ) ) . '</h2>';
		echo '<p><a class="button button-primary" href="' . esc_url( get_edit_post_link( $post->ID ) ) . '">' . esc_html__( 'Open this page to edit it', 'sidcraft-autoseo' ) . '</a> <a class="button" href="' . esc_url( get_permalink( $post ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'View page', 'sidcraft-autoseo' ) . '</a></p>';
		echo '<p class="description">' . esc_html__( 'Suggestions are built from the text, headings, categories and images already on your site: no AI and nothing leaves your server. “Use this” changes only the search-engine title, description or featured image; it never rewrites your page text.', 'sidcraft-autoseo' ) . '</p>';
		if ( ! $r['issues'] ) { echo '<p>' . esc_html__( 'Nothing to improve on this page.', 'sidcraft-autoseo' ) . '</p>'; return; }
		foreach ( $r['issues'] as $i ) {
			echo '<div class="sidcraft-autoseo-banner" style="background:#fff;border-color:#c3c4c7;max-width:1000px"><strong>' . esc_html( $i['msg'] ) . '</strong>';
			$sugg = in_array( $i['id'], Sidcraft_AutoSEO_Suggest::supported(), true ) ? Sidcraft_AutoSEO_Suggest::for_issue( $post, $i['id'] ) : array();
			if ( ! $sugg ) {
				$adv = Sidcraft_AutoSEO_Fixer::advice( $i['id'] );
				echo '<p class="description">' . esc_html( $adv ? $adv : __( 'Nothing to suggest automatically.', 'sidcraft-autoseo' ) ) . '</p></div>';
				continue;
			}
			echo '<ul style="margin:10px 0 0">';
			foreach ( $sugg as $sg ) {
				echo '<li style="margin:0 0 10px;padding:8px 10px;background:#f6f7f7;border-radius:4px">';
				if ( $sg['note'] ) { echo '<span class="description">' . esc_html( $sg['note'] ) . '</span><br>'; }
				if ( 'thumb' === $sg['kind'] ) { echo wp_kses_post( wp_get_attachment_image( (int) $sg['value'], array( 90, 90 ), false, array( 'style' => 'vertical-align:middle;margin-right:8px;border-radius:4px' ) ) ); }
				if ( 'link' === $sg['kind'] ) { echo '<a href="' . esc_url( get_edit_post_link( (int) $sg['value'] ) ) . '">' . esc_html( $sg['text'] ) . '</a>'; }
				else { echo '<span>' . esc_html( $sg['text'] ) . '</span>'; }
				if ( $sg['apply'] ) {
					echo ' <form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
					wp_nonce_field( 'sidcraft_autoseo_action' );
					echo '<input type="hidden" name="action" value="sidcraft_autoseo_action"><input type="hidden" name="do" value="sugg_apply"><input type="hidden" name="id" value="' . (int) $post->ID . '"><input type="hidden" name="what" value="' . esc_attr( $sg['apply'] ) . '"><input type="hidden" name="value" value="' . esc_attr( $sg['value'] ) . '">';
					echo '<button class="button button-primary" style="margin-left:8px">' . esc_html__( 'Use this', 'sidcraft-autoseo' ) . '</button></form>';
				}
				echo '</li>';
			}
			echo '</ul>';
			if ( ! in_array( $i['id'], array( 'title', 'kw_title', 'desc', 'social' ), true ) ) { echo '<p><a href="' . esc_url( get_edit_post_link( $post->ID ) ) . '">' . esc_html__( 'Open the page and make this change →', 'sidcraft-autoseo' ) . '</a></p>'; }
			echo '</div>';
		}
	}

	private static function tab_redirects() {
		global $wpdb;
		echo '<p>Redirects are created automatically when a page address changes, and near-miss broken URLs are repaired by themselves. You can also add one by hand.</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'sidcraft_autoseo_action' );
		echo '<input type="hidden" name="action" value="sidcraft_autoseo_action"><input type="hidden" name="do" value="add_redirect">';
		echo '<input type="text" name="source" placeholder="/old-address" class="regular-text"> → <input type="text" name="target" placeholder="https://example.com/new-address" class="regular-text"> <button class="button button-primary">Add 301 redirect</button></form>';
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY id DESC LIMIT %d', Sidcraft_AutoSEO_Redirects::table(), 200 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom plugin table.
		echo '<h2>Redirects</h2><table class="widefat striped"><thead><tr><th>From</th><th>To</th><th>Type</th><th>Used</th><th></th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			echo '<tr><td>' . esc_html( $r->source ) . '</td><td>' . esc_html( $r->target ) . '</td><td>' . ( $r->auto ? 'automatic' : 'manual' ) . '</td><td>' . (int) $r->hits . '</td><td>';
			self::button( 'del_redirect', __( 'Delete', 'sidcraft-autoseo' ), array( 'id' => $r->id ) );
			echo '</td></tr>';
		}
		if ( ! $rows ) { echo '<tr><td colspan="5">None yet.</td></tr>'; }
		echo '</tbody></table>';
		echo '</tbody></table>';
		/* translators: %s: link to the Broken URLs tab */
		echo '<p>' . wp_kses_post( sprintf( __( 'Addresses visitors reached that do not exist are listed under %s, where you can fix them.', 'sidcraft-autoseo' ), '<a href="' . esc_url( admin_url( 'admin.php?page=sidcraft-autoseo&tab=urls' ) ) . '">' . esc_html__( 'Broken URLs', 'sidcraft-autoseo' ) . '</a>' ) ) . '</p>';
	}

	private static function tab_settings() {
		$o = Sidcraft_AutoSEO_Settings::all();
		$boxes = array(
			'title_suffix' => __( 'Add the site name to page titles when it fits', 'sidcraft-autoseo' ),
			'social_cards' => __( 'Social sharing cards (Facebook, LinkedIn, X)', 'sidcraft-autoseo' ),
			'schema'       => __( 'Structured data (organization, article, breadcrumbs, FAQ)', 'sidcraft-autoseo' ),
			'sitemap'      => __( 'Improve the XML sitemap (dates, hide noindex pages)', 'sidcraft-autoseo' ),
			'auto_redirects' => __( 'Create 301 redirects automatically when a page address changes', 'sidcraft-autoseo' ),
			'fuzzy_404'    => __( 'Repair near-miss broken URLs by redirecting to the closest page', 'sidcraft-autoseo' ),
			'auto_alt'     => __( 'Write image alt text automatically', 'sidcraft-autoseo' ),
			'related_links' => __( 'Add a short “You may also like” list to posts that link to no other page', 'sidcraft-autoseo' ),
			'check_external' => __( 'Broken-link scan also checks links to other websites (sends a request to each linked site)', 'sidcraft-autoseo' ),
			'related_pages' => __( 'Also add the “You may also like” list to pages that link to no other page', 'sidcraft-autoseo' ),
			'auto_excerpt' => __( 'Write a short excerpt for posts that have none', 'sidcraft-autoseo' ),
			'indexnow'     => __( 'Tell Bing & other IndexNow search engines when pages are published or changed', 'sidcraft-autoseo' ),
			'hygiene'      => __( 'Tidy-ups: remove generator tags, redirect attachment pages', 'sidcraft-autoseo' ),
			'redirect_author' => __( 'Single-author site: redirect the author archive to the home page', 'sidcraft-autoseo' ),
			'noindex_thin_tags' => __( 'Keep tag pages with fewer than 2 posts out of search results', 'sidcraft-autoseo' ),
			'daily_audit'  => __( 'Re-check every page once a day and repair what can be repaired', 'sidcraft-autoseo' ),
			'remove_data'  => __( 'Delete all AutoSEO data when the plugin is deleted', 'sidcraft-autoseo' ),
		);
		echo '<form method="post" action="options.php">';
		settings_fields( 'sidcraft_autoseo_settings_group' );
		echo '<table class="form-table"><tbody>';
		foreach ( $boxes as $k => $label ) {
			printf( '<tr><th scope="row">%s</th><td><label><input type="checkbox" name="%s[%s]" value="1" %s> On</label></td></tr>', esc_html( $label ), esc_attr( Sidcraft_AutoSEO_Settings::OPTION ), esc_attr( $k ), checked( ! empty( $o[ $k ] ), true, false ) );
		}
		echo '<tr><th scope="row">' . esc_html__( 'Related list style', 'sidcraft-autoseo' ) . '</th><td><select name="' . esc_attr( Sidcraft_AutoSEO_Settings::OPTION ) . '[related_style]"><option value="collapsed" ' . selected( $o['related_style'], 'collapsed', false ) . '>' . esc_html__( 'Folded (click to open)', 'sidcraft-autoseo' ) . '</option><option value="open" ' . selected( $o['related_style'], 'open', false ) . '>' . esc_html__( 'Always open', 'sidcraft-autoseo' ) . '</option></select><p class="description">' . esc_html__( 'The home page never shows this list. On any other page you can switch it off in that page’s Sidcraft AutoSEO box.', 'sidcraft-autoseo' ) . '</p></td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Title separator', 'sidcraft-autoseo' ) . '</th><td><input type="text" size="3" name="' . esc_attr( Sidcraft_AutoSEO_Settings::OPTION ) . '[title_sep]" value="' . esc_attr( $o['title_sep'] ) . '"></td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Default sharing image URL', 'sidcraft-autoseo' ) . '</th><td><input type="url" class="regular-text" name="' . esc_attr( Sidcraft_AutoSEO_Settings::OPTION ) . '[default_image]" value="' . esc_attr( $o['default_image'] ) . '"><p class="description">' . esc_html__( 'Used when a page has no featured image and no image in its text. Leave empty to use the site icon.', 'sidcraft-autoseo' ) . '</p></td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'X (Twitter) username', 'sidcraft-autoseo' ) . '</th><td><input type="text" name="' . esc_attr( Sidcraft_AutoSEO_Settings::OPTION ) . '[twitter_site]" value="' . esc_attr( $o['twitter_site'] ) . '" placeholder="@yourname"></td></tr>';
		echo '</tbody></table>';
		submit_button();
		echo '</form>';
	}

	/* ---------- actions ---------- */

	public static function action() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Not allowed.', 403 ); }
		check_admin_referer( 'sidcraft_autoseo_action' );
		$do  = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		$msg = '';
		$tab = 'overview';
		switch ( $do ) {
			case 'audit':
				Sidcraft_AutoSEO_Audit::reset_marks();
				set_transient( 'sidcraft_autoseo_resume', 1, 10 * MINUTE_IN_SECONDS );
				wp_schedule_single_event( time() + 60, 'sidcraft_autoseo_audit_more' );
				$msg = __( 'Audit started. It runs a few pages at a time while this page is open.', 'sidcraft-autoseo' );
				break;
			case 'fix':
				$what = isset( $_POST['what'] ) ? sanitize_key( wp_unslash( $_POST['what'] ) ) : '';
				if ( ! isset( Sidcraft_AutoSEO_Fixer::catalog()[ $what ] ) ) { $msg = __( 'Unknown fix.', 'sidcraft-autoseo' ); break; }
				list( , $msg ) = Sidcraft_AutoSEO_Fixer::run( $what );
				Sidcraft_AutoSEO_Fixer::recheck();
				$msg .= ' ' . __( 'Re-checking every page now.', 'sidcraft-autoseo' );
				break;
			case 'fix_all':
				$msg = Sidcraft_AutoSEO_Fixer::run_all_safe();
				break;
			case 'fix_post':
				$tab  = 'pages';
				$pid  = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
				if ( $pid && current_user_can( 'edit_post', $pid ) ) {
					$n   = Sidcraft_AutoSEO_Fixer::fix_post( $pid );
					/* translators: %d: number of fixes applied. */
					$msg = sprintf( _n( '%d fix applied and the page re-checked.', '%d fixes applied and the page re-checked.', $n, 'sidcraft-autoseo' ), $n );
					Sidcraft_AutoSEO_Audit::summary( true );
				}
				break;
			case 'links_scan':
				$tab = 'links';
				Sidcraft_AutoSEO_Links::start();
				$more = Sidcraft_AutoSEO_Links::batch( 25 );
				if ( $more ) { wp_schedule_single_event( time() + 15, Sidcraft_AutoSEO_Links::EVENT ); }
				$msg = $more ? __( 'Scan started. It carries on in the background; refresh this page in a minute.', 'sidcraft-autoseo' ) : __( 'Scan finished.', 'sidcraft-autoseo' );
				break;
			case 'link_replace':
			case 'link_redirect':
			case 'link_unlink':
			case 'link_ignore':
				$tab = 'links';
				$msg = Sidcraft_AutoSEO_Links::repair( isset( $_POST['lid'] ) ? sanitize_key( wp_unslash( $_POST['lid'] ) ) : '', substr( $do, 5 ), isset( $_POST['new_url'] ) ? esc_url_raw( wp_unslash( $_POST['new_url'] ) ) : '' );
				break;
			case 'url_check_ip':
			case 'url_block_ip':
			case 'url_unblock_ip':
				$tab = 'urls';
				$ip  = isset( $_POST['ip'] ) ? sanitize_text_field( wp_unslash( $_POST['ip'] ) ) : '';
				if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) { $msg = __( 'That is not a valid address.', 'sidcraft-autoseo' ); break; }
				list( $engine, $host ) = self::search_engine( $ip );
				if ( 'url_check_ip' === $do ) {
					if ( $engine ) { /* translators: 1: IP address 2: company 3: host name */ $msg = sprintf( __( '%1$s is a genuine %2$s crawler (%3$s). Do not block it: blocking it would remove your pages from search results.', 'sidcraft-autoseo' ), $ip, $engine, $host ); }
					elseif ( $host ) { /* translators: 1: IP address 2: host name */ $msg = sprintf( __( '%1$s is NOT a verified search engine. Its host name is %2$s.', 'sidcraft-autoseo' ), $ip, $host ); }
					else { /* translators: %s: IP address */ $msg = sprintf( __( '%s has no host name, so it is not a search engine crawler. Search engines can always be traced back to their own servers.', 'sidcraft-autoseo' ), $ip ); }
					break;
				}
				if ( ! class_exists( 'Sidcraft_BotShield_Settings' ) ) { $msg = __( 'Install and activate Sidcraft Bot Shield to block or unblock addresses.', 'sidcraft-autoseo' ); break; }
				if ( 'url_unblock_ip' === $do ) {
					Sidcraft_BotShield_Settings::remove_line( 'ip_block', $ip );
					if ( class_exists( 'Sidcraft_BotShield_Bans' ) ) { Sidcraft_BotShield_Bans::remove( $ip ); }
					$still = class_exists( 'Sidcraft_BotShield_Ranges' ) && Sidcraft_BotShield_Ranges::in_list( $ip, Sidcraft_BotShield_Settings::lines( 'ip_block' ) );
					/* translators: %s: IP address */
					$msg = $still ? sprintf( __( '%s was removed from the list by itself, but a range on the Sidcraft Bot Shield block list still covers it. Edit that range in Settings → Bot Shield.', 'sidcraft-autoseo' ), $ip ) : sprintf( __( '%s is unblocked.', 'sidcraft-autoseo' ), $ip );
					break;
				}
				if ( $engine ) { /* translators: 1: IP address 2: company */ $msg = sprintf( __( '%1$s is a genuine %2$s crawler, so it was not blocked. Blocking it would remove your pages from search results.', 'sidcraft-autoseo' ), $ip, $engine ); break; }
				Sidcraft_BotShield_Settings::add_line( 'ip_block', $ip );
				/* translators: %s: IP address */
				$msg = sprintf( __( '%s is now on the Sidcraft Bot Shield block list. You can unblock it here at any time.', 'sidcraft-autoseo' ), $ip );
				break;
			case 'url_redirect':
			case 'url_dismiss':
			case 'urls_dismiss_all':
			case 'urls_fix_all':
				$tab = 'urls';
				global $wpdb;
				if ( 'urls_dismiss_all' === $do ) {
					$n = Sidcraft_AutoSEO_Redirects::clear_404();
					/* translators: %d: number of addresses */
					$msg = sprintf( _n( 'Dismissed %d address.', 'Dismissed %d addresses.', $n, 'sidcraft-autoseo' ), $n );
					break;
				}
				if ( 'urls_fix_all' === $do ) {
					$n = 0;
					foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT id, path FROM %i LIMIT %d', Sidcraft_AutoSEO_Redirects::table404(), 200 ) ) as $r ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom plugin table.
						$t = Sidcraft_AutoSEO_Redirects::guess( $r->path, 70 );
						if ( $t && Sidcraft_AutoSEO_Redirects::add( $r->path, $t ) ) { ++$n; }
					}
					/* translators: %d: number of redirects */
					$msg = sprintf( _n( '%d redirect created.', '%d redirects created.', $n, 'sidcraft-autoseo' ), $n );
					break;
				}
				$uid = isset( $_POST['uid'] ) ? (int) $_POST['uid'] : 0;
				$row = $wpdb->get_row( $wpdb->prepare( 'SELECT path FROM %i WHERE id = %d', Sidcraft_AutoSEO_Redirects::table404(), $uid ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom plugin table.
				if ( ! $row ) { $msg = __( 'That address is no longer in the list.', 'sidcraft-autoseo' ); break; }
				if ( 'url_dismiss' === $do ) { Sidcraft_AutoSEO_Redirects::delete_404( $uid ); $msg = __( 'Dismissed. It comes back only if someone requests it again.', 'sidcraft-autoseo' ); break; }
				$ok  = Sidcraft_AutoSEO_Redirects::add( $row->path, isset( $_POST['new_url'] ) ? esc_url_raw( wp_unslash( $_POST['new_url'] ) ) : '' );
				$msg = $ok ? __( 'Redirect saved. Anyone who asks for that address now arrives at the new page.', 'sidcraft-autoseo' ) : __( 'Could not save: choose a different address.', 'sidcraft-autoseo' );
				break;
			case 'sugg_apply':
				$tab = 'suggest';
				$pid = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
				$ok  = Sidcraft_AutoSEO_Suggest::apply( $pid, isset( $_POST['what'] ) ? sanitize_key( wp_unslash( $_POST['what'] ) ) : '', isset( $_POST['value'] ) ? sanitize_text_field( wp_unslash( $_POST['value'] ) ) : '' );
				$sc  = (int) get_post_meta( $pid, '_sidcraft_autoseo_score', true );
				/* translators: %d: new score */
				$msg = $ok ? sprintf( __( 'Applied. The page now scores %d.', 'sidcraft-autoseo' ), $sc ) : __( 'Could not apply that suggestion.', 'sidcraft-autoseo' );
				wp_safe_redirect( add_query_arg( array( 'page' => 'sidcraft-autoseo', 'tab' => 'suggest', 'id' => $pid, 'done' => rawurlencode( $msg ) ), admin_url( 'admin.php' ) ) );
				exit;
			case 'links_fix_all':
				$tab = 'links';
				$msg = Sidcraft_AutoSEO_Links::fix_all();
				break;
			case 'alt':
				$msg = sprintf( 'Alt text written for %d images.', Sidcraft_AutoSEO_Images::fix_library( 300 ) );
				break;
			case 'add_redirect':
				$tab = 'redirects';
				$ok  = Sidcraft_AutoSEO_Redirects::add( isset( $_POST['source'] ) ? sanitize_text_field( wp_unslash( $_POST['source'] ) ) : '', isset( $_POST['target'] ) ? esc_url_raw( wp_unslash( $_POST['target'] ) ) : '' );
				$msg = $ok ? 'Redirect saved.' : 'Could not save: check both addresses.';
				break;
			case 'del_redirect':
				$tab = 'redirects';
				Sidcraft_AutoSEO_Redirects::delete( isset( $_POST['id'] ) ? (int) $_POST['id'] : 0 );
				$msg = 'Redirect deleted.';
				break;
		}
		wp_safe_redirect( add_query_arg( array( 'page' => 'sidcraft-autoseo', 'tab' => $tab, 'done' => rawurlencode( $msg ) ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/* ---------- post screen ---------- */

	public static function metabox() {
		foreach ( Sidcraft_AutoSEO_Util::post_types() as $pt ) { add_meta_box( 'sidcraft_autoseo_box', 'Sidcraft AutoSEO', array( __CLASS__, 'box' ), $pt, 'side', 'default' ); }
	}

	public static function box( $post ) {
		wp_nonce_field( 'sidcraft_autoseo_box', 'sidcraft_autoseo_box_nonce' );
		$r = 'publish' === $post->post_status ? Sidcraft_AutoSEO_Audit::analyze( $post ) : null;
		echo '<p><strong>Search result preview</strong><br><span style="color:#1a0dab">' . esc_html( Sidcraft_AutoSEO_Meta::title_for_post( $post ) ) . '</span><br><span style="color:#4d5156;font-size:12px">' . esc_html( Sidcraft_AutoSEO_Meta::description_for_post( $post ) ) . '</span></p>';
		if ( $r ) {
			echo '<p>Score ' . wp_kses_post( self::badge( $r['score'] ) ) . '</p>';
			if ( $r['issues'] ) { echo '<ul style="list-style:disc;margin-left:18px">'; foreach ( array_slice( $r['issues'], 0, 5 ) as $i ) { echo '<li>' . esc_html( $i['msg'] ) . '</li>'; } echo '</ul>'; }
		} else { echo '<p>The score appears after you publish.</p>'; }
		if ( $r && $r['issues'] ) { echo '<p><a href="' . esc_url( add_query_arg( array( 'page' => 'sidcraft-autoseo', 'tab' => 'suggest', 'id' => $post->ID ), admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'See suggestions for this page →', 'sidcraft-autoseo' ) . '</a></p>'; }
		echo '<p class="description">Everything above is generated automatically. Fill in a field only if you want to override it.</p>';
		foreach ( array( 'title' => 'Title', 'desc' => 'Description', 'focus' => 'Main phrase' ) as $k => $label ) {
			printf( '<p><label>%s<br><input type="text" class="widefat" name="sidcraft_autoseo_%s" value="%s"></label></p>', esc_html( $label ), esc_attr( $k ), esc_attr( Sidcraft_AutoSEO_Meta::override( $post->ID, $k ) ) );
		}
		printf( '<p><label><input type="checkbox" name="sidcraft_autoseo_no_related" value="1" %s> %s</label></p>', checked( (bool) get_post_meta( $post->ID, '_sidcraft_autoseo_no_related', true ), true, false ), esc_html__( 'Do not show a “You may also like” list on this page', 'sidcraft-autoseo' ) );
		printf( '<p><label><input type="checkbox" name="sidcraft_autoseo_noindex" value="1" %s> Hide from search engines</label></p>', checked( (bool) Sidcraft_AutoSEO_Meta::override( $post->ID, 'noindex' ), true, false ) );
	}

	public static function save_box( $post_id, $post ) {
		if ( ! isset( $_POST['sidcraft_autoseo_box_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sidcraft_autoseo_box_nonce'] ) ), 'sidcraft_autoseo_box' ) ) { return; }
		if ( wp_is_post_autosave( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) { return; }
		foreach ( array( 'title', 'desc', 'focus' ) as $k ) {
			$v = isset( $_POST[ 'sidcraft_autoseo_' . $k ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'sidcraft_autoseo_' . $k ] ) ) : '';
			if ( '' === $v ) { delete_post_meta( $post_id, '_sidcraft_autoseo_' . $k ); } else { update_post_meta( $post_id, '_sidcraft_autoseo_' . $k, $v ); }
		}
		if ( ! empty( $_POST['sidcraft_autoseo_no_related'] ) ) { update_post_meta( $post_id, '_sidcraft_autoseo_no_related', 1 ); } else { delete_post_meta( $post_id, '_sidcraft_autoseo_no_related' ); }
		if ( ! empty( $_POST['sidcraft_autoseo_noindex'] ) ) { update_post_meta( $post_id, '_sidcraft_autoseo_noindex', 1 ); } else { delete_post_meta( $post_id, '_sidcraft_autoseo_noindex' ); }
	}

	public static function col( $cols ) { $cols['aseo'] = 'SEO'; return $cols; }

	public static function col_cell( $col, $post_id ) {
		if ( 'aseo' !== $col ) { return; }
		$s = get_post_meta( $post_id, '_sidcraft_autoseo_score', true );
		echo '' === $s ? '—' : wp_kses_post( self::badge( (int) $s ) );
	}
}
