<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Dashboard, settings, redirect manager and the optional per-post box. */
class Sidcraft_RankBoost_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_post_sidcraft_rankboost_action', array( __CLASS__, 'action' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'metabox' ) );
		add_action( 'save_post', array( __CLASS__, 'save_box' ), 10, 2 );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
		foreach ( Sidcraft_RankBoost_Util::post_types() as $pt ) {
			add_filter( "manage_{$pt}_posts_columns", array( __CLASS__, 'col' ) );
			add_action( "manage_{$pt}_posts_custom_column", array( __CLASS__, 'col_cell' ), 10, 2 );
		}
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'styles' ) );
		add_action( 'wp_ajax_sidcraft_rankboost_ip', array( __CLASS__, 'ajax_ip' ) );
	}

	public static function styles() {
		$css = '.sidcraft-rankboost-b{display:inline-block;min-width:34px;text-align:center;padding:2px 8px;border-radius:12px;color:#fff;font-weight:600}.sidcraft-rankboost-g{background:#1a7f37}.sidcraft-rankboost-o{background:#d97706}.sidcraft-rankboost-r{background:#cf222e}.sidcraft-rankboost-card{background:#fff;border:1px solid #ccd0d4;border-radius:8px;padding:16px 20px;margin:0 12px 12px 0;display:inline-block;vertical-align:top;min-width:180px}.sidcraft-rankboost-card b{font-size:28px;display:block}a.sidcraft-rankboost-card{text-decoration:none;color:inherit}a.sidcraft-rankboost-card:hover{border-color:#2271b1;box-shadow:0 1px 6px rgba(0,0,0,.15)}.sidcraft-rankboost-card small{display:block;color:#2271b1;margin-top:4px}.sidcraft-rankboost-banner{background:#fff8e5;border:1px solid #dba617;border-radius:6px;padding:14px 18px;margin:14px 0;max-width:900px}.sidcraft-rankboost-wrap table.widefat td,.sidcraft-rankboost-wrap table.widefat th{vertical-align:top}'.
			'.sidcraft-rankboost-tone-g{border-left:6px solid #1a7f37;background:#f0faf3}.sidcraft-rankboost-tone-g b{color:#1a7f37}.sidcraft-rankboost-tone-o{border-left:6px solid #d97706;background:#fff7ed}.sidcraft-rankboost-tone-o b{color:#c2410c}.sidcraft-rankboost-tone-r{border-left:6px solid #cf222e;background:#fff1f2}.sidcraft-rankboost-tone-r b{color:#cf222e}';
		$pg = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen check.
		$is_plugin_page = ( 0 === strpos( $pg, 'sidcraft-rankboost' ) || 0 === strpos( $pg, 'sidcraft-vs' ) );
		// The score badge also appears on post lists; load styling only there and on our own screens.
		$screen    = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$is_list   = $screen && 'edit' === $screen->base && in_array( (string) $screen->post_type, Sidcraft_RankBoost_Util::post_types(), true );
		if ( ! $is_plugin_page && ! $is_list ) { return; }
		if ( $is_plugin_page ) {
			wp_enqueue_script( 'sidcraft-rankboost-confirm', plugins_url( 'includes/assets/confirm.js', SIDCRAFT_RANKBOOST_FILE ), array(), SIDCRAFT_RANKBOOST_VERSION, true );
			wp_localize_script( 'sidcraft-rankboost-confirm', 'sidcraftConfirmText', array( 'yes' => __( 'Yes, continue', 'sidcraft-rankboost' ), 'no' => __( 'Cancel', 'sidcraft-rankboost' ) ) );
		}
		wp_register_style( 'sidcraft-rankboost-admin', false, array(), SIDCRAFT_RANKBOOST_VERSION );
		wp_enqueue_style( 'sidcraft-rankboost-admin' );
		wp_add_inline_style( 'sidcraft-rankboost-admin', $css );
		$page = $pg;
		if ( 'sidcraft-rankboost' === $page && current_user_can( 'manage_options' ) ) {
			$resume = (bool) get_transient( 'sidcraft_rankboost_resume' ) && Sidcraft_RankBoost_Audit::pending() > 0;
			wp_register_script( 'sidcraft-rankboost-admin', false, array(), SIDCRAFT_RANKBOOST_VERSION, true );
			wp_enqueue_script( 'sidcraft-rankboost-admin' );
			wp_add_inline_script( 'sidcraft-rankboost-admin', 'window.sidcraftRankBoost=' . wp_json_encode( array(
				'ajax' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'sidcraft_rankboost_audit' ), 'resume' => $resume, 'fixjob' => (bool) Sidcraft_RankBoost_Fixer::job(), 'fixing' => __( 'Fixing… please keep this page open.', 'sidcraft-rankboost' ), 'fixdone' => __( 'Fixes finished. Re-checking pages…', 'sidcraft-rankboost' ),
				'checking' => __( 'Checking pages…', 'sidcraft-rankboost' ), 'done' => __( 'Audit finished.', 'sidcraft-rankboost' ),
				'retry' => __( 'The server did not answer in time. Press “Run audit now” to carry on where it stopped.', 'sidcraft-rankboost' ),
			) ) . ';' . self::audit_js() );
		}
	}

	private static function audit_js() {
		return '(function(){var c=window.sidcraftRankBoost,b=document.getElementById("sidcraft-rankboost-run");if(!c||!b)return;var box=document.getElementById("sidcraft-rankboost-progress"),bar=document.getElementById("sidcraft-rankboost-bar"),txt=document.getElementById("sidcraft-rankboost-txt"),running=false,carry=false;'
		. 'function sleep(ms){return new Promise(function(r){setTimeout(r,ms)})}'
		. 'async function step(reset){for(var t=0;t<3;t++){try{var f=new FormData();f.append("action","sidcraft_rankboost_audit");f.append("_ajax_nonce",c.nonce);if(reset)f.append("reset","1");var r=await fetch(c.ajax,{method:"POST",body:f,credentials:"same-origin"});var j=JSON.parse(await r.text());if(j.success)return j.data}catch(e){}await sleep(1500)}throw new Error(c.retry)}'
		. 'async function run(reset){if(running)return;running=true;b.disabled=true;box.hidden=false;txt.textContent=c.checking;try{var d=await step(reset);for(;;){var n=Math.max(1,d.total);bar.max=n;bar.value=Math.max(0,n-d.left);txt.textContent=c.checking+" "+bar.value+" / "+n;if(d.done)break;await sleep(800);d=await step(false)}txt.textContent=c.done;setTimeout(function(){location.reload()},700)}catch(e){txt.textContent=e.message;carry=true;b.disabled=false;running=false}}'
		. 'b.addEventListener("click",function(){run(!carry)});if(c.resume)run(false);})();'
		. '(function(){var c=window.sidcraftRankBoost;if(!c||!c.fixjob)return;var w=document.querySelector(".wrap"),n=document.createElement("div");n.className="notice notice-info";n.style.padding="12px";n.textContent=c.fixing;if(w&&w.children[1])w.insertBefore(n,w.children[1]);else if(w)w.appendChild(n);'
		. 'function sleep(ms){return new Promise(function(r){setTimeout(r,ms)})}'
		. 'async function go(){var fails=0;for(;;){try{var f=new FormData();f.append("action","sidcraft_rankboost_fix");f.append("_ajax_nonce",c.nonce);var r=await fetch(c.ajax,{method:"POST",body:f,credentials:"same-origin"});var j=JSON.parse(await r.text());if(!j.success)throw 0;fails=0;n.textContent=c.fixing+" ("+j.data.n+")";if(j.data.done){n.textContent=c.fixdone;setTimeout(function(){location.reload()},900);return}}catch(e){if(++fails>5){n.textContent=c.fixing+" – the server is slow; reload this page to carry on.";return}await sleep(2500)}await sleep(500)}}go();})();';
	}

	public static function badge( $score ) {
		$score = (int) $score;
		$c = $score >= 80 ? 'sidcraft-rankboost-g' : ( $score >= 60 ? 'sidcraft-rankboost-o' : 'sidcraft-rankboost-r' );
		return '<span class="sidcraft-rankboost-b ' . $c . '">' . $score . '</span>';
	}

	public static function notice() {
		$other     = Sidcraft_RankBoost_Util::other_seo_plugin();
		$page_slug = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen check.
		if ( $other && current_user_can( 'manage_options' ) && 0 === strpos( $page_slug, 'sidcraft-rankboost' ) ) {
			echo '<div class="notice notice-warning"><p>' . esc_html( sprintf( '%s is active, so Sidcraft RankBoost is not printing titles, descriptions, social cards, schema or sitemap changes to avoid duplicates. Auditing, redirects, image alt text and IndexNow still run.', $other ) ) . '</p></div>';
		}
	}

	/* ---------- menu ---------- */

	public static function menu() {
		add_menu_page( 'Sidcraft RankBoost', 'Sidcraft RankBoost', 'manage_options', 'sidcraft-rankboost', array( __CLASS__, 'page' ), 'dashicons-chart-line', 80 );
	}

	public static function register() {
		register_setting( 'sidcraft_rankboost_settings_group', Sidcraft_RankBoost_Settings::OPTION, array( 'type' => 'array', 'sanitize_callback' => array( 'Sidcraft_RankBoost_Settings', 'sanitize' ), 'default' => Sidcraft_RankBoost_Settings::defaults() ) );
	}

	private static function tabs() {
		return array( 'overview' => __( 'Overview', 'sidcraft-rankboost' ), 'pages' => __( 'Pages & Posts', 'sidcraft-rankboost' ), 'links' => __( 'Broken Links', 'sidcraft-rankboost' ), 'urls' => __( 'Broken URLs', 'sidcraft-rankboost' ), 'redirects' => __( 'Redirects & 404s', 'sidcraft-rankboost' ), 'speed' => __( 'Speed & Database', 'sidcraft-rankboost' ), 'settings' => __( 'Settings', 'sidcraft-rankboost' ) );
	}

	private static function tab_speed() { Sidcraft_RankBoost_Speed_Admin::render(); }

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview'; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! isset( self::tabs()[ $tab ] ) && ! in_array( $tab, array( 'suggest', 'edit' ), true ) ) { $tab = 'overview'; }
		echo '<div class="wrap sidcraft-rankboost-wrap"><h1>Sidcraft RankBoost</h1><h2 class="nav-tab-wrapper">';
		foreach ( self::tabs() as $k => $label ) {
			printf( '<a class="nav-tab %s" href="%s">%s</a>', $k === $tab ? 'nav-tab-active' : '', esc_url( admin_url( 'admin.php?page=sidcraft-rankboost&tab=' . $k ) ), esc_html( $label ) );
		}
		echo '</h2>';
		if ( isset( $_GET['done'] ) ) { echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['done'] ) ) ) . '</p></div>'; } // phpcs:ignore WordPress.Security.NonceVerification
		call_user_func( array( __CLASS__, 'tab_' . $tab ) );
		echo '</div>';
	}

	/** Tags the small action buttons need (used when their HTML is printed inside a table). */
	private static function kses() {
		return array(
			'form' => array( 'method' => array(), 'action' => array(), 'style' => array(), 'data-sidcraft-confirm' => array() ),
			'input' => array( 'type' => array(), 'name' => array(), 'value' => array() ),
			'button' => array( 'class' => array(), 'type' => array(), 'name' => array(), 'value' => array(), 'data-do' => array(), 'data-ip' => array(), 'data-confirm' => array(), 'data-sidcraft-confirm' => array() ),
			'div' => array( 'style' => array(), 'class' => array(), 'data-ip' => array() ), 'code' => array(), 'em' => array(), 'span' => array( 'class' => array() ),
		);
	}

	private static function button_html( $do, $label, $extra = array(), $class = 'button', $confirm = '' ) { ob_start(); self::button( $do, $label, $extra, $class, $confirm ); return ob_get_clean(); }

	private static function button( $do, $label, $extra = array(), $class = 'button', $confirm = '' ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin:0 8px 8px 0"' . ( '' !== $confirm ? ' data-sidcraft-confirm="' . esc_attr( $confirm ) . '"' : '' ) . '>';
		wp_nonce_field( 'sidcraft_rankboost_action' );
		echo '<input type="hidden" name="action" value="sidcraft_rankboost_action"><input type="hidden" name="do" value="' . esc_attr( $do ) . '">';
		foreach ( $extra as $k => $v ) { echo '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">'; }
		echo '<button class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</button></form>';
	}

	/* ---------- tabs ---------- */

	private static function pages_url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => 'sidcraft-rankboost', 'tab' => 'pages' ), $args ), admin_url( 'admin.php' ) );
	}

	private static function tab_overview() {
		$s = Sidcraft_RankBoost_Audit::summary();
		$r = Sidcraft_RankBoost_Redirects::counts();
		$last = (int) get_option( 'sidcraft_rankboost_last_run' );
		$in = get_option( 'sidcraft_rankboost_indexnow_last' );
		echo '<p>' . esc_html__( 'Sidcraft RankBoost works on its own: every page and post already gets a title, description, social card, structured data, sitemap entry, image alt text and redirect protection. This page shows how it is doing and lets you fix the rest with one click.', 'sidcraft-rankboost' ) . '</p>';
		echo '<div>';
		printf( '<a class="sidcraft-rankboost-card" href="%s">%s<b>%s</b>%s<small>%s</small></a>', esc_url( self::pages_url() ), esc_html__( 'Average score', 'sidcraft-rankboost' ), wp_kses_post( self::badge( $s['avg'] ) ), esc_html( sprintf( /* translators: %d: number of pages */ __( '%d pages and posts', 'sidcraft-rankboost' ), (int) $s['total'] ) ), esc_html__( 'View all →', 'sidcraft-rankboost' ) );
		printf( '<a class="sidcraft-rankboost-card sidcraft-rankboost-tone-g" href="%s">%s<b>%d</b>%s<small>%s</small></a>', esc_url( self::pages_url( array( 'band' => 'good' ) ) ), esc_html__( 'Good (80+)', 'sidcraft-rankboost' ), (int) $s['good'], esc_html__( 'Nothing urgent', 'sidcraft-rankboost' ), esc_html__( 'View →', 'sidcraft-rankboost' ) );
		printf( '<a class="sidcraft-rankboost-card sidcraft-rankboost-tone-o" href="%s">%s<b>%d</b>%s<small>%s</small></a>', esc_url( self::pages_url( array( 'band' => 'ok' ) ) ), esc_html__( 'Needs work (60–79)', 'sidcraft-rankboost' ), (int) $s['ok'], esc_html__( 'Click to see and fix', 'sidcraft-rankboost' ), esc_html__( 'View and fix →', 'sidcraft-rankboost' ) );
		printf( '<a class="sidcraft-rankboost-card sidcraft-rankboost-tone-r" href="%s">%s<b>%d</b>%s<small>%s</small></a>', esc_url( self::pages_url( array( 'band' => 'poor' ) ) ), esc_html__( 'Poor (under 60)', 'sidcraft-rankboost' ), (int) $s['bad'], esc_html__( 'Click to see and fix', 'sidcraft-rankboost' ), esc_html__( 'View and fix →', 'sidcraft-rankboost' ) );
		$bl = Sidcraft_RankBoost_Links::count();
		printf( '<a class="sidcraft-rankboost-card %s" href="%s">%s<b>%d</b>%s<small>%s</small></a>', $bl ? 'sidcraft-rankboost-tone-r' : 'sidcraft-rankboost-tone-g', esc_url( add_query_arg( array( 'page' => 'sidcraft-rankboost', 'tab' => 'links' ), admin_url( 'admin.php' ) ) ), esc_html__( 'Broken links', 'sidcraft-rankboost' ), (int) $bl, esc_html( $bl ? __( 'inside your pages', 'sidcraft-rankboost' ) : __( 'none found', 'sidcraft-rankboost' ) ), esc_html__( 'Open Broken Links →', 'sidcraft-rankboost' ) );
		printf( '<div class="sidcraft-rankboost-card">%s<b>%d</b>%s</div>', esc_html__( 'Redirects', 'sidcraft-rankboost' ), (int) $r['redirects'], esc_html( sprintf( /* translators: 1: automatic redirects, 2: times used */ __( '%1$d automatic · used %2$d times', 'sidcraft-rankboost' ), (int) $r['auto'], (int) $r['hits'] ) ) );
		printf( '<a class="sidcraft-rankboost-card %s" href="%s">%s<b>%d</b>%s<small>%s</small></a>', $r['missing'] > 0 ? 'sidcraft-rankboost-tone-o' : 'sidcraft-rankboost-tone-g', esc_url( add_query_arg( array( 'page' => 'sidcraft-rankboost', 'tab' => 'urls' ), admin_url( 'admin.php' ) ) ), esc_html__( 'Broken URLs seen', 'sidcraft-rankboost' ), (int) $r['missing'], esc_html( $r['missing'] ? __( 'not yet fixed', 'sidcraft-rankboost' ) : __( 'none recorded', 'sidcraft-rankboost' ) ), esc_html__( 'View and fix →', 'sidcraft-rankboost' ) );
		echo '</div>';
		if ( ( $s['ok'] + $s['bad'] ) > 0 ) {
			$fixable = 0;
			foreach ( array_keys( Sidcraft_RankBoost_Fixer::catalog() ) as $fid ) { $fixable += count( Sidcraft_RankBoost_Fixer::posts_with_issue( $fid, 1 ) ); }
			echo '<div class="sidcraft-rankboost-banner"><strong>' . esc_html( sprintf( /* translators: %d: number of pages */ _n( '%d page needs work.', '%d pages need work.', $s['ok'] + $s['bad'], 'sidcraft-rankboost' ), $s['ok'] + $s['bad'] ) ) . '</strong> ';
			if ( $fixable > 0 ) {
				echo esc_html__( 'Some problems can be fixed automatically; others need you to write something. The table below says which is which.', 'sidcraft-rankboost' ) . '<br><br>';
				self::button( 'fix_all', __( 'Fix everything that can be fixed automatically', 'sidcraft-rankboost' ), array(), 'button button-primary button-hero' );
				echo '<p class="description" style="margin:4px 0 0">' . esc_html__( 'Adds related-page links, writes missing excerpts and image alt text, then re-checks every page. It never changes web addresses or rewrites your text.', 'sidcraft-rankboost' ) . '</p>';
			} else {
				echo esc_html__( 'Everything that can be fixed automatically has been fixed. The rest needs you to add or edit content: click a number in the table below to see those pages.', 'sidcraft-rankboost' );
			}
			echo '</div>';
		}
		echo '<h2>' . esc_html__( 'What is most worth looking at', 'sidcraft-rankboost' ) . '</h2>';
		if ( $s['top'] ) {
			$cat = Sidcraft_RankBoost_Fixer::catalog();
			echo '<table class="widefat striped" style="max-width:980px"><thead><tr><th>' . esc_html__( 'Issue', 'sidcraft-rankboost' ) . '</th><th>' . esc_html__( 'Pages', 'sidcraft-rankboost' ) . '</th><th>' . esc_html__( 'What to do', 'sidcraft-rankboost' ) . '</th></tr></thead><tbody>';
			foreach ( $s['top'] as $id => $t ) {
				echo '<tr><td>' . esc_html( $t['msg'] ) . '</td><td><a href="' . esc_url( self::pages_url( array( 'issue' => $id ) ) ) . '">' . (int) $t['n'] . '</a></td><td>';
				if ( isset( $cat[ $id ] ) ) {
					self::button( 'fix', $cat[ $id ]['label'], array( 'what' => $id ), 'button button-primary', $cat[ $id ]['confirm'] );
					echo '<br><span class="description">' . esc_html( $cat[ $id ]['hint'] ) . '</span>';
				} else {
					echo esc_html( Sidcraft_RankBoost_Fixer::advice( $id ) );
					echo ' <a href="' . esc_url( self::pages_url( array( 'issue' => $id ) ) ) . '">' . esc_html__( 'See pages', 'sidcraft-rankboost' ) . '</a>';
				}
				echo '</td></tr>';
			}
			echo '</tbody></table>';
		} else { echo '<p>' . esc_html__( 'No issues found yet. Press “Run audit now” to check everything.', 'sidcraft-rankboost' ) . '</p>'; }
		echo '<h2>' . esc_html__( 'Status', 'sidcraft-rankboost' ) . '</h2><table class="widefat striped" style="max-width:980px"><tbody>';
		$sitemap = Sidcraft_RankBoost_Sitemap::index_url();
		echo '<tr><td>' . esc_html__( 'Sitemap', 'sidcraft-rankboost' ) . '</td><td>' . ( $sitemap ? '<a href="' . esc_url( $sitemap ) . '" target="_blank" rel="noopener">' . esc_html( $sitemap ) . '</a>' : esc_html__( 'Sitemaps are switched off for this site.', 'sidcraft-rankboost' ) ) . '</td></tr>';
		$status = __( 'Off', 'sidcraft-rankboost' );
		if ( Sidcraft_RankBoost_Settings::on( 'indexnow' ) ) {
			if ( ! Sidcraft_RankBoost_IndexNow::allowed() ) { $status = __( 'On, but paused here because this looks like a local or private site.', 'sidcraft-rankboost' ); }
			elseif ( is_array( $in ) ) { /* translators: 1: time ago, 2: number of URLs */ $status = sprintf( __( 'On. Last sent %1$s ago (%2$d URLs).', 'sidcraft-rankboost' ), human_time_diff( $in['time'] ), $in['count'] ); }
			else { $status = __( 'On. Waiting for the next publish.', 'sidcraft-rankboost' ); }
		}
		echo '<tr><td>' . esc_html__( 'Search engine notification (IndexNow)', 'sidcraft-rankboost' ) . '</td><td>' . esc_html( $status ) . '</td></tr>';
		echo '<tr><td>' . esc_html__( 'Last full audit', 'sidcraft-rankboost' ) . '</td><td>' . esc_html( ( $last ? human_time_diff( $last ) . ' ' . __( 'ago', 'sidcraft-rankboost' ) : __( 'Not yet', 'sidcraft-rankboost' ) ) . ' · ' . __( 'waiting to check:', 'sidcraft-rankboost' ) . ' ' . (int) Sidcraft_RankBoost_Audit::pending() ) . '</td></tr>';
		echo '</tbody></table><p style="margin-top:14px">';
		echo '<button type="button" class="button" id="sidcraft-rankboost-run" style="margin:0 8px 8px 0">' . esc_html__( 'Run audit now', 'sidcraft-rankboost' ) . '</button>';
		self::button( 'alt', __( 'Fix missing image alt text now', 'sidcraft-rankboost' ) );
		echo '</p><p id="sidcraft-rankboost-progress" hidden style="max-width:520px"><progress id="sidcraft-rankboost-bar" value="0" max="1" style="width:100%;height:18px"></progress><br><span id="sidcraft-rankboost-txt" class="description"></span></p>';
	}

	private static function tab_pages() {
		$paged = max( 1, isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1 ); // phpcs:ignore WordPress.Security.NonceVerification
		$band  = isset( $_GET['band'] ) ? sanitize_key( wp_unslash( $_GET['band'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$issue = isset( $_GET['issue'] ) ? sanitize_key( wp_unslash( $_GET['issue'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$meta  = array( 'relation' => 'AND', array( 'key' => '_sidcraft_rankboost_score', 'compare' => 'EXISTS' ) );
		$title = __( 'All pages and posts, lowest score first.', 'sidcraft-rankboost' );
		if ( 'good' === $band ) { $meta[] = array( 'key' => '_sidcraft_rankboost_score', 'value' => 80, 'compare' => '>=', 'type' => 'NUMERIC' ); $title = __( 'Good pages (80 and above).', 'sidcraft-rankboost' ); }
		if ( 'ok' === $band ) { $meta[] = array( 'key' => '_sidcraft_rankboost_score', 'value' => array( 60, 79 ), 'compare' => 'BETWEEN', 'type' => 'NUMERIC' ); $title = __( 'Pages that need work (60–79).', 'sidcraft-rankboost' ); }
		if ( 'poor' === $band ) { $meta[] = array( 'key' => '_sidcraft_rankboost_score', 'value' => 60, 'compare' => '<', 'type' => 'NUMERIC' ); $title = __( 'Poor pages (under 60).', 'sidcraft-rankboost' ); }
		if ( '' !== $issue ) { $meta[] = array( 'key' => '_sidcraft_rankboost_issues', 'value' => '"id":"' . $issue . '"', 'compare' => 'LIKE' ); $title = __( 'Pages with this issue.', 'sidcraft-rankboost' ); }
		$q = new WP_Query( array(
			'post_type' => Sidcraft_RankBoost_Util::post_types(), 'post_status' => 'publish', 'posts_per_page' => 40, 'paged' => $paged,
			'meta_key' => '_sidcraft_rankboost_score', 'orderby' => 'meta_value_num', 'order' => 'ASC', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- admin list sorted by score.
			'meta_query' => $meta, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- admin list filter.
		) );
		echo '<p><strong>' . esc_html( $title ) . '</strong> ' . ( $band || $issue ? '<a href="' . esc_url( self::pages_url() ) . '">' . esc_html__( 'Show all', 'sidcraft-rankboost' ) . '</a>' : '' ) . '</p>';
		$cat = Sidcraft_RankBoost_Fixer::catalog();
		if ( $issue && isset( $cat[ $issue ] ) ) {
			self::button( 'fix', $cat[ $issue ]['label'], array( 'what' => $issue ), 'button button-primary', $cat[ $issue ]['confirm'] );
			echo '<span class="description">' . esc_html( $cat[ $issue ]['hint'] ) . '</span>';
		} elseif ( 'ok' === $band || 'poor' === $band ) {
			self::button( 'fix_all', __( 'Fix everything that can be fixed automatically', 'sidcraft-rankboost' ), array(), 'button button-primary' );
		}
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Page', 'sidcraft-rankboost' ) . '</th><th>' . esc_html__( 'Type', 'sidcraft-rankboost' ) . '</th><th>' . esc_html__( 'Score', 'sidcraft-rankboost' ) . '</th><th>' . esc_html__( 'Main phrase', 'sidcraft-rankboost' ) . '</th><th>' . esc_html__( 'What could be better', 'sidcraft-rankboost' ) . '</th><th></th></tr></thead><tbody>';
		foreach ( $q->posts as $p ) {
			$issues = json_decode( (string) get_post_meta( $p->ID, '_sidcraft_rankboost_issues', true ), true );
			$issues = is_array( $issues ) ? $issues : array();
			$lines  = array();
			$fixable = false;
			foreach ( array_slice( $issues, 0, 4 ) as $i ) {
				$tag = isset( $cat[ $i['id'] ] ) ? ' <em>(' . esc_html__( 'can be fixed automatically', 'sidcraft-rankboost' ) . ')</em>' : '';
				$lines[] = esc_html( $i['msg'] ) . $tag;
			}
			foreach ( $issues as $i ) { if ( isset( $cat[ $i['id'] ] ) ) { $fixable = true; } }
			echo '<tr><td><a href="' . esc_url( get_edit_post_link( $p->ID ) ) . '"><strong>' . esc_html( get_the_title( $p ) ) . '</strong></a><br><a href="' . esc_url( get_permalink( $p ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'View', 'sidcraft-rankboost' ) . '</a></td><td>' . esc_html( $p->post_type ) . '</td><td>' . wp_kses_post( self::badge( (int) get_post_meta( $p->ID, '_sidcraft_rankboost_score', true ) ) ) . '</td><td>' . esc_html( Sidcraft_RankBoost_Meta::focus_for_post( $p->ID ) ) . '</td><td>' . ( $lines ? wp_kses( implode( '<br>', $lines ), array( 'em' => array(), 'br' => array() ) ) : '—' ) . '</td><td>';
			echo '<a class="button button-primary" style="margin:0 8px 8px 0" href="' . esc_url( add_query_arg( array( 'page' => 'sidcraft-rankboost', 'tab' => 'edit', 'id' => $p->ID ), admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Edit SEO', 'sidcraft-rankboost' ) . '</a>';
			if ( $fixable ) { self::button( 'fix_post', __( 'Fix automatically', 'sidcraft-rankboost' ), array( 'id' => $p->ID ) ); }
			if ( $issues ) { echo '<a class="button" href="' . esc_url( add_query_arg( array( 'page' => 'sidcraft-rankboost', 'tab' => 'suggest', 'id' => $p->ID ), admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Suggestions', 'sidcraft-rankboost' ) . '</a>'; }
			echo '</td></tr>';
		}
		if ( ! $q->posts ) { echo '<tr><td colspan="6">' . esc_html__( 'Nothing here. Go to Overview and press “Run audit now”.', 'sidcraft-rankboost' ) . '</td></tr>'; }
		echo '</tbody></table>';
		$links = paginate_links( array( 'base' => add_query_arg( 'paged', '%#%' ), 'format' => '', 'current' => $paged, 'total' => max( 1, (int) $q->max_num_pages ) ) );
		if ( $links ) { echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( $links ) . '</div></div>'; }
	}

	private static function tab_links() {
		$st    = Sidcraft_RankBoost_Links::state();
		$items = Sidcraft_RankBoost_Links::visible();
		echo '<p>' . esc_html__( 'Finds links inside your pages and posts that lead nowhere, so visitors and search engines do not hit dead ends. Fix each one with a click.', 'sidcraft-rankboost' ) . '</p>';
		self::button( 'links_scan', __( 'Scan for broken links now', 'sidcraft-rankboost' ), array(), 'button button-primary' );
		if ( $st['finished'] ) {
			/* translators: 1: date and time, 2: number of pages, 3: number of links. */
			echo '<span class="description">' . esc_html( sprintf( __( 'Last scan %1$s: %2$d pages, %3$d links checked.', 'sidcraft-rankboost' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $st['finished'] ), (int) $st['posts'], (int) $st['links'] ) ) . '</span>';
		} elseif ( $st['started'] ) {
			/* translators: %d: number of pages checked so far. */
			echo '<span class="description">' . esc_html( sprintf( __( 'Scan in progress… %d pages checked so far. Refresh in a minute.', 'sidcraft-rankboost' ), (int) $st['posts'] ) ) . '</span>';
		} else {
			echo '<span class="description">' . esc_html__( 'No scan has run yet. Click the button; the scan also repeats every day.', 'sidcraft-rankboost' ) . '</span>';
		}
		if ( ! Sidcraft_RankBoost_Settings::on( 'check_external' ) ) {
			echo '<p class="description">' . esc_html__( 'Only links to your own site are checked. To also check links to other websites, tick the option under Settings.', 'sidcraft-rankboost' ) . '</p>';
		}
		if ( ! $items ) {
			if ( $st['finished'] ) { echo '<div class="sidcraft-rankboost-card sidcraft-rankboost-tone-g" style="display:block;max-width:520px"><b>0</b>' . esc_html__( 'No broken links found. Nice.', 'sidcraft-rankboost' ) . '</div>'; }
			return;
		}
		$with = 0;
		foreach ( $items as $it ) { if ( '' !== $it['suggest'] ) { ++$with; } }
		if ( $with ) {
			echo '<div class="sidcraft-rankboost-banner"><strong>' . esc_html( sprintf( /* translators: %d: number of links */ _n( '%d broken link has a close match on your site.', '%d broken links have a close match on your site.', $with, 'sidcraft-rankboost' ), $with ) ) . '</strong><br><br>';
			self::button( 'links_fix_all', __( 'Repair them all with the suggested address', 'sidcraft-rankboost' ), array(), 'button button-primary', __( 'Replace each broken link with the suggested page? You can review the list first.', 'sidcraft-rankboost' ) );
			echo '</div>';
		}
		$reasons = Sidcraft_RankBoost_Links::reasons();
		echo '<table class="widefat striped" style="margin-top:12px"><thead><tr><th>' . esc_html__( 'Broken link', 'sidcraft-rankboost' ) . '</th><th>' . esc_html__( 'Problem', 'sidcraft-rankboost' ) . '</th><th>' . esc_html__( 'Found on', 'sidcraft-rankboost' ) . '</th><th>' . esc_html__( 'Fix it', 'sidcraft-rankboost' ) . '</th></tr></thead><tbody>';
		foreach ( array_slice( $items, 0, 100, true ) as $id => $it ) {
			$post = get_post( $it['post_id'] );
			echo '<tr><td style="word-break:break-all"><code>' . esc_html( $it['url'] ) . '</code></td><td>' . esc_html( isset( $reasons[ $it['reason'] ] ) ? $reasons[ $it['reason'] ] : $it['reason'] ) . '</td><td>';
			if ( $post ) { echo '<a href="' . esc_url( get_edit_post_link( $post ) ) . '">' . esc_html( wp_strip_all_tags( get_the_title( $post ) ) ) . '</a>'; }
			echo '</td><td>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'sidcraft_rankboost_action' );
			echo '<input type="hidden" name="action" value="sidcraft_rankboost_action"><input type="hidden" name="lid" value="' . esc_attr( $id ) . '">';
			echo '<input type="url" class="regular-text" name="new_url" value="' . esc_attr( $it['suggest'] ) . '" placeholder="' . esc_attr__( 'New address', 'sidcraft-rankboost' ) . '" style="width:100%;max-width:340px"><br>';
			echo '<button class="button button-primary" name="do" value="link_replace">' . esc_html__( 'Use this address', 'sidcraft-rankboost' ) . '</button> ';
			if ( $it['internal'] ) { echo '<button class="button" name="do" value="link_redirect" title="' . esc_attr__( 'Keeps the link as it is and sends anyone who follows it to the new address', 'sidcraft-rankboost' ) . '">' . esc_html__( 'Redirect old address', 'sidcraft-rankboost' ) . '</button> '; }
			echo '<button class="button" name="do" value="link_unlink" formnovalidate data-sidcraft-confirm="' . esc_attr__( 'Remove this link from the page? The words stay.', 'sidcraft-rankboost' ) . '">' . esc_html__( 'Remove link', 'sidcraft-rankboost' ) . '</button> ';
			echo '<button class="button-link" name="do" value="link_ignore" formnovalidate>' . esc_html__( 'Ignore', 'sidcraft-rankboost' ) . '</button>';
			echo '</form></td></tr>';
		}
		echo '</tbody></table>';
		if ( count( $items ) > 100 ) { echo '<p class="description">' . esc_html( sprintf( /* translators: %d: number of links */ __( 'Showing the first 100 of %d. Fix some and the rest appear.', 'sidcraft-rankboost' ), count( $items ) ) ) . '</p>'; }
	}

	private static function tab_urls() {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY hits DESC, last_seen DESC LIMIT %d', Sidcraft_RankBoost_Redirects::table404(), 100 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom plugin table.
		echo '<p>' . esc_html__( 'Addresses on your site that visitors or search engines asked for but that do not exist. Each one is a dead end. Send it to the right page with a click and it is fixed for good.', 'sidcraft-rankboost' ) . '</p>';
		echo '<p class="description">' . esc_html__( 'Near misses are repaired automatically while the setting “Repair near-miss broken URLs” is on, so what is listed here is what could not be matched with confidence.', 'sidcraft-rankboost' ) . '</p>';
		self::gone_box();
		if ( ! $rows ) {
			echo '<div class="sidcraft-rankboost-card sidcraft-rankboost-tone-g" style="display:block;max-width:520px"><b>0</b>' . esc_html__( 'No broken URLs recorded. Nice.', 'sidcraft-rankboost' ) . '</div>';
			return;
		}
		$sug = array();
		$with = 0;
		foreach ( $rows as $r ) {
			$sug[ $r->id ] = Sidcraft_RankBoost_Redirects::guess( $r->path, 70 );
			if ( $sug[ $r->id ] ) { ++$with; }
		}
		echo '<div class="sidcraft-rankboost-banner"><strong>' . esc_html( sprintf( /* translators: %d: number of addresses */ _n( '%d broken URL.', '%d broken URLs.', count( $rows ), 'sidcraft-rankboost' ), count( $rows ) ) ) . '</strong> ';
		if ( $with ) {
			echo esc_html( sprintf( /* translators: %d: number of addresses */ _n( '%d has a likely match on your site.', '%d have a likely match on your site.', $with, 'sidcraft-rankboost' ), $with ) ) . '<br><br>';
			self::button( 'urls_fix_all', __( 'Redirect them all to the suggested pages', 'sidcraft-rankboost' ), array(), 'button button-primary', __( 'Create a 301 redirect to the suggested page for every address that has one?', 'sidcraft-rankboost' ) );
		} else {
			echo esc_html__( 'None has an obvious match; choose where each should go.', 'sidcraft-rankboost' ) . '<br><br>';
		}
		self::button( 'urls_dismiss_all', __( 'Dismiss all', 'sidcraft-rankboost' ), array(), 'button', __( 'Remove every address from this list? An address comes back only if someone requests it again.', 'sidcraft-rankboost' ) );
		echo '</div>';
		echo '<table class="widefat striped" style="margin-top:12px"><thead><tr><th>' . esc_html__( 'Broken URL', 'sidcraft-rankboost' ) . '</th><th>' . esc_html__( 'Visits', 'sidcraft-rankboost' ) . '</th><th>' . esc_html__( 'Last seen', 'sidcraft-rankboost' ) . '</th><th>' . esc_html__( 'IP address', 'sidcraft-rankboost' ) . '</th><th>' . esc_html__( 'Came from', 'sidcraft-rankboost' ) . '</th><th>' . esc_html__( 'Fix it', 'sidcraft-rankboost' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$target = $sug[ $r->id ] ? $sug[ $r->id ] : home_url( '/' );
			echo '<tr><td style="word-break:break-all"><code>' . esc_html( $r->path ) . '</code></td><td>' . (int) $r->hits . '</td><td>' . esc_html( wp_date( get_option( 'date_format' ), strtotime( $r->last_seen . ' UTC' ) ) ) . '</td><td>' . wp_kses( self::ip_cell( $r ), self::kses() ) . '</td><td style="word-break:break-all">';
			echo $r->referrer ? esc_html( $r->referrer ) : '<span class="description">' . esc_html__( 'direct or unknown', 'sidcraft-rankboost' ) . '</span>';
			echo '</td><td><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'sidcraft_rankboost_action' );
			echo '<input type="hidden" name="action" value="sidcraft_rankboost_action"><input type="hidden" name="uid" value="' . (int) $r->id . '">';
			echo '<input type="url" name="new_url" value="' . esc_attr( $target ) . '" style="width:100%;max-width:340px"><br>';
			echo '<button class="button button-primary" name="do" value="url_redirect">' . esc_html__( 'Redirect (301)', 'sidcraft-rankboost' ) . '</button> ';
			echo '<button class="button-link" name="do" value="url_dismiss" formnovalidate>' . esc_html__( 'Dismiss', 'sidcraft-rankboost' ) . '</button>';
			echo '</form></td></tr>';
			self::ip_script();
	}
		echo '</tbody></table>';
		self::ip_script();
	}

	/** Names that real search-engine crawlers resolve to (reverse DNS), by company. */
	private static function engine_hosts() {
		return array( 'Google' => array( '.googlebot.com', '.google.com' ), 'Bing' => array( '.search.msn.com' ), 'Apple' => array( '.applebot.apple.com' ), 'Yahoo' => array( '.crawl.yahoo.net' ), 'Yandex' => array( '.yandex.ru', '.yandex.net', '.yandex.com' ), 'Baidu' => array( '.baidu.com', '.baidu.jp' ), 'DuckDuckGo' => array( '.duckduckgo.com' ) );
	}

	/** Does this address really belong to a search engine? Returns array( company or '', host name or '' ). Checks the name both ways, so it cannot be faked. */
	public static function search_engine( $ip ) {
		$host = apply_filters( 'sidcraft_rankboost_rdns', null, $ip );
		if ( null === $host ) { $host = @gethostbyaddr( $ip ); } // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a failed lookup is a normal outcome.
		if ( ! is_string( $host ) || '' === $host || $host === $ip ) { return array( '', '' ); }
		$host = strtolower( rtrim( $host, '.' ) );
		foreach ( self::engine_hosts() as $name => $suffixes ) {
			foreach ( $suffixes as $suf ) {
				if ( strlen( $host ) > strlen( $suf ) && substr( $host, -strlen( $suf ) ) === $suf ) {
					$back = apply_filters( 'sidcraft_rankboost_forward', null, $host );
					if ( null === $back ) { $back = false !== strpos( $ip, ':' ) ? array() : (array) @gethostbynamel( $host ); } // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
					$want = @inet_pton( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
					foreach ( (array) $back as $b ) { if ( $want && @inet_pton( $b ) === $want ) { return array( $name, $host ); } } // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				}
			}
		}
		return array( '', $host );
	}

	/** The addresses that asked for a missing URL, each with Check, and Block or Unblock for Sidcraft BotVanguard (they work in place, without reloading the page). */
	private static function ip_cell( $r ) {
		$ips = isset( $r->ips ) && '' !== $r->ips ? explode( ',', $r->ips ) : array();
		if ( ! $ips ) { return '<span class="description">' . esc_html__( 'not recorded', 'sidcraft-rankboost' ) . '</span>'; }
		$out = '';
		foreach ( $ips as $ip ) {
			$ip = trim( $ip );
			$out .= '<div class="sidcraft-ip-row" data-ip="' . esc_attr( $ip ) . '" style="white-space:nowrap"><code>' . esc_html( $ip ) . '</code> <span class="sidcraft-ip-ctl">' . self::ip_controls( $ip ) . '</span><div class="sidcraft-ip-msg" style="white-space:normal"></div></div>';
		}
		return $out;
	}

	/** The small script behind the Check / Block / Unblock buttons: sends the click, then shows the answer next to the address. */
	private static function ip_script() {
		wp_register_script( 'sidcraft-rankboost-ip', false, array(), SIDCRAFT_RANKBOOST_VERSION, true );
		wp_enqueue_script( 'sidcraft-rankboost-ip' );
		wp_localize_script( 'sidcraft-rankboost-ip', 'sidcraftRankBoostIp', array( 'ajax' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'sidcraft_rankboost_ip' ), 'working' => __( 'Working…', 'sidcraft-rankboost' ), 'failed' => __( 'The request failed. Reload the page and try again.', 'sidcraft-rankboost' ) ) );
		wp_add_inline_script( 'sidcraft-rankboost-ip', "document.addEventListener('click',function(e){var b=e.target.closest('.sidcraft-ip-act');if(!b){return;}e.preventDefault();var c=window.sidcraftRankBoostIp,ip=b.getAttribute('data-ip'),q=b.getAttribute('data-confirm');function run(){var rows=document.querySelectorAll('.sidcraft-ip-row');rows=Array.prototype.filter.call(rows,function(r){return r.getAttribute('data-ip')===ip;});rows.forEach(function(r){r.querySelector('.sidcraft-ip-msg').textContent=c.working;});var f=new URLSearchParams({action:'sidcraft_rankboost_ip',nonce:c.nonce,do:b.getAttribute('data-do'),ip:ip});fetch(c.ajax,{method:'POST',credentials:'same-origin',body:f}).then(function(r){return r.json();}).then(function(j){var d=j&&j.data?j.data:{};rows.forEach(function(r){var m=r.querySelector('.sidcraft-ip-msg');m.textContent=d.msg||c.failed;m.style.cssText='white-space:normal;margin:4px 0;padding:6px 8px;border-left:4px solid '+(j&&j.success?'#00a32a':'#d63638')+';background:#fff;max-width:420px';if(d.html){r.querySelector('.sidcraft-ip-ctl').innerHTML=d.html;}});}).catch(function(){rows.forEach(function(r){r.querySelector('.sidcraft-ip-msg').textContent=c.failed;});});}if(q&&window.sidcraftConfirm){window.sidcraftConfirm(q,run);}else{run();}});" );
	}

	/** Check, block or unblock one address. Returns the message to show. Shared by the form buttons and the in-page (AJAX) buttons. */
	private static function ip_action( $do, $ip ) {
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) { return __( 'That is not a valid address.', 'sidcraft-rankboost' ); }
		list( $engine, $host ) = self::search_engine( $ip );
		if ( 'url_check_ip' === $do ) {
			if ( $engine ) { /* translators: 1: IP address 2: company 3: host name */ return sprintf( __( '%1$s is a genuine %2$s crawler (%3$s). Do not block it: blocking it would remove your pages from search results.', 'sidcraft-rankboost' ), $ip, $engine, $host ); }
			if ( $host ) { /* translators: 1: IP address 2: host name */ return sprintf( __( '%1$s is NOT a verified search engine. Its host name is %2$s.', 'sidcraft-rankboost' ), $ip, $host ); }
			/* translators: %s: IP address */
			return sprintf( __( '%s has no host name, so it is not a search engine crawler. Search engines can always be traced back to their own servers.', 'sidcraft-rankboost' ), $ip );
		}
		$S = self::bv( 'Settings' ); $R = self::bv( 'Ranges' ); $B = self::bv( 'Bans' );
		if ( '' === $S ) { return __( 'Install and activate Sidcraft BotVanguard to block or unblock addresses.', 'sidcraft-rankboost' ); }
		if ( 'url_unblock_ip' === $do ) {
			$S::remove_line( 'ip_block', $ip );
			if ( '' !== $B ) { $B::remove( $ip ); }
			$still = '' !== $R && $R::in_list( $ip, $S::lines( 'ip_block' ) );
			/* translators: %s: IP address */
			return $still ? sprintf( __( '%s was removed from the list by itself, but a range on the Sidcraft BotVanguard block list still covers it. Edit that range in the Sidcraft BotVanguard menu.', 'sidcraft-rankboost' ), $ip ) : sprintf( __( '%s is unblocked.', 'sidcraft-rankboost' ), $ip );
		}
		if ( method_exists( $S, 'is_allowed_ip' ) && $S::is_allowed_ip( $ip ) ) { /* translators: %s: IP address */ return sprintf( __( '%s is on the Sidcraft BotVanguard never-block list, so it was not blocked. Remove it from that list first if you really want to block it.', 'sidcraft-rankboost' ), $ip ); }
		if ( $engine ) { /* translators: 1: IP address 2: company */ return sprintf( __( '%1$s is a genuine %2$s crawler, so it was not blocked. Blocking it would remove your pages from search results.', 'sidcraft-rankboost' ), $ip, $engine ); }
		if ( false === $S::add_line( 'ip_block', $ip ) ) { /* translators: %s: IP address */ return sprintf( __( '%s could not be added to the block list.', 'sidcraft-rankboost' ), $ip ); }
		$ok = '' !== $R && $R::in_list( $ip, $S::lines( 'ip_block' ) );
		/* translators: %s: IP address */
		return $ok ? sprintf( __( '%s is now on the Sidcraft BotVanguard block list. You can unblock it here at any time.', 'sidcraft-rankboost' ), $ip ) : sprintf( __( '%s was saved but Sidcraft BotVanguard does not report it as blocked. Check the Sidcraft BotVanguard menu.', 'sidcraft-rankboost' ), $ip );
	}

	/** The class of the Sidcraft BotVanguard plugin for a part (Settings, Ranges, Bans, Guard), or '' when it is not active. The earlier names (BotVaguard, Sidcraft Bot Shield) still work. */
	public static function bv( $part ) {
		foreach ( array( 'Botvanguard_', 'Botvaguard_', 'Sidcraft_BotShield_' ) as $prefix ) { if ( class_exists( $prefix . $part, false ) ) { return $prefix . $part; } }
		return '';
	}

	/** In-page Check / Block / Unblock: answers without reloading the page, so the result appears next to the button. */
	public static function ajax_ip() {
		check_ajax_referer( 'sidcraft_rankboost_ip', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'msg' => __( 'You do not have permission to do this.', 'sidcraft-rankboost' ) ), 403 ); }
		$do = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		$ip = isset( $_POST['ip'] ) ? sanitize_text_field( wp_unslash( $_POST['ip'] ) ) : '';
		if ( ! in_array( $do, array( 'url_check_ip', 'url_block_ip', 'url_unblock_ip' ), true ) ) { wp_send_json_error( array( 'msg' => __( 'Unknown action.', 'sidcraft-rankboost' ) ), 400 ); }
		$msg = self::ip_action( $do, $ip );
		wp_send_json_success( array( 'msg' => $msg, 'html' => wp_kses( self::ip_controls( $ip ), self::kses_ip() ) ) );
	}

	private static function kses_ip() { return array( 'em' => array(), 'button' => array( 'type' => array(), 'class' => array(), 'data-do' => array(), 'data-ip' => array(), 'data-confirm' => array() ), 'span' => array( 'class' => array() ) ); }

	/** Check, and Block or Unblock, for one address (the part of the cell that changes). */
	private static function ip_controls( $ip ) {
		$out = '<button type="button" class="button-link sidcraft-ip-act" data-do="url_check_ip" data-ip="' . esc_attr( $ip ) . '">' . esc_html__( 'Check', 'sidcraft-rankboost' ) . '</button>';
		$S = self::bv( 'Settings' ); $R = self::bv( 'Ranges' );
		if ( '' === $S ) { return $out; }
		$blocked = '' !== $R && $R::in_list( $ip, $S::lines( 'ip_block' ) );
		$safe    = method_exists( $S, 'is_allowed_ip' ) && $S::is_allowed_ip( $ip );
		if ( $safe && ! $blocked ) { return $out . ' <em>' . esc_html__( 'never blocked', 'sidcraft-rankboost' ) . '</em>'; }
		if ( $blocked ) { return $out . ' <em>' . esc_html__( 'blocked', 'sidcraft-rankboost' ) . '</em> <button type="button" class="button-link sidcraft-ip-act" data-do="url_unblock_ip" data-ip="' . esc_attr( $ip ) . '">' . esc_html__( 'Unblock', 'sidcraft-rankboost' ) . '</button>'; }
		return $out . ' <button type="button" class="button-link sidcraft-ip-act" data-do="url_block_ip" data-ip="' . esc_attr( $ip ) . '" data-confirm="' . esc_attr__( 'Block this address for good in Sidcraft BotVanguard?', 'sidcraft-rankboost' ) . '">' . esc_html__( 'Block', 'sidcraft-rankboost' ) . '</button>';
	}

	/** Groups of broken addresses that share a first folder (like /itm/) and the folders already answered with 410 Gone. */
	private static function gone_box() {
		global $wpdb;
		$count = array();
		foreach ( (array) $wpdb->get_col( $wpdb->prepare( 'SELECT path FROM %i LIMIT %d', Sidcraft_RankBoost_Redirects::table404(), 1000 ) ) as $path ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom plugin table.
			$parts = explode( '/', trim( $path, '/' ) ); if ( count( $parts ) < 2 ) { continue; }
			$k = '/' . $parts[0] . '/'; $count[ $k ] = ( $count[ $k ] ?? 0 ) + 1;
		}
		arsort( $count ); $groups = array_filter( $count, function ( $n ) { return $n >= 3; } );
		$gone = Sidcraft_RankBoost_Redirects::gone_prefixes();
		if ( $groups ) {
			echo '<div class="sidcraft-rankboost-banner" style="margin-bottom:12px"><strong>' . esc_html__( 'Many addresses start the same way.', 'sidcraft-rankboost' ) . '</strong> ' . esc_html__( 'If a whole folder never existed on your site (for example old spam pages, or another site’s addresses), tell search engines it is gone for good. The site then answers “410 Gone”, Google stops asking, and these addresses leave this list.', 'sidcraft-rankboost' ) . '<br><br>';
			foreach ( array_slice( $groups, 0, 6, true ) as $pre => $n ) { self::button( 'urls_gone', sprintf( /* translators: 1: folder 2: number of addresses */ __( '%1$s is gone for good (%2$d addresses)', 'sidcraft-rankboost' ), $pre, $n ), array( 'prefix' => $pre ), 'button', sprintf( /* translators: %s: folder */ __( 'Answer “410 Gone” for everything under %s and remove it from this list? Only do this if no real page of your site lives there.', 'sidcraft-rankboost' ), $pre ) ); }
			echo '</div>';
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:0 0 12px">'; wp_nonce_field( 'sidcraft_rankboost_action' ); echo '<input type="hidden" name="action" value="sidcraft_rankboost_action"><input type="hidden" name="do" value="urls_gone"><label>' . esc_html__( 'Mark a folder as gone for good:', 'sidcraft-rankboost' ) . ' <input type="text" name="prefix" placeholder="/itm/" style="width:180px"></label> <button class="button">' . esc_html__( 'Mark as gone (410)', 'sidcraft-rankboost' ) . '</button></form>';
		if ( $gone ) {
			echo '<p><strong>' . esc_html__( 'Answered with 410 Gone:', 'sidcraft-rankboost' ) . '</strong> ';
			foreach ( $gone as $g ) { echo '<code>' . esc_html( $g ) . '</code> ' . wp_kses( self::button_html( 'urls_gone_remove', __( 'Remove', 'sidcraft-rankboost' ), array( 'prefix' => $g ), 'button-link' ), self::kses() ) . ' &nbsp; '; }
			echo '</p>';
		}
	}

	/** Edit the SEO fields of one page by hand. */
	private static function tab_edit() {
		$id   = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen.
		$post = $id ? get_post( $id ) : null;
		if ( ! $post || ! current_user_can( 'edit_post', $id ) ) { echo '<p>' . esc_html__( 'Page not found.', 'sidcraft-rankboost' ) . '</p>'; return; }
		echo '<p><a href="' . esc_url( self::pages_url() ) . '">← ' . esc_html__( 'Back to Pages & Posts', 'sidcraft-rankboost' ) . '</a> &nbsp;|&nbsp; <a href="' . esc_url( get_edit_post_link( $id ) ) . '">' . esc_html__( 'Open the editor', 'sidcraft-rankboost' ) . '</a> &nbsp;|&nbsp; <a href="' . esc_url( get_permalink( $post ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'View the page', 'sidcraft-rankboost' ) . '</a></p>';
		echo '<h2>' . esc_html( get_the_title( $post ) ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( Sidcraft_RankBoost_Editor::NONCE . $id, 'sidcraft_rankboost_nonce' );
		echo '<input type="hidden" name="action" value="sidcraft_rankboost_edit"><input type="hidden" name="id" value="' . (int) $id . '">';
		Sidcraft_RankBoost_Editor::fields( $post );
		echo '<p class="description">' . esc_html__( 'Leave a field empty to keep it automatic. What you type here is used exactly as written.', 'sidcraft-rankboost' ) . '</p>';
		submit_button( __( 'Save SEO settings', 'sidcraft-rankboost' ) );
		echo '</form>';
	}

	private static function tab_suggest() {
		$id   = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen.
		$post = $id ? get_post( $id ) : null;
		if ( ! $post || ! current_user_can( 'edit_post', $id ) ) { echo '<p>' . esc_html__( 'Page not found.', 'sidcraft-rankboost' ) . '</p>'; return; }
		$r = Sidcraft_RankBoost_Audit::analyze( $post );
		echo '<p><a href="' . esc_url( self::pages_url() ) . '">← ' . esc_html__( 'Back to Pages & Posts', 'sidcraft-rankboost' ) . '</a></p>';
		echo '<h2>' . esc_html( get_the_title( $post ) ) . ' ' . wp_kses_post( self::badge( $r['score'] ) ) . '</h2>';
		echo '<p><a class="button button-primary" href="' . esc_url( get_edit_post_link( $post->ID ) ) . '">' . esc_html__( 'Open this page to edit it', 'sidcraft-rankboost' ) . '</a> <a class="button" href="' . esc_url( get_permalink( $post ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'View page', 'sidcraft-rankboost' ) . '</a></p>';
		echo '<p class="description">' . esc_html__( 'Suggestions are built from the text, headings, categories and images already on your site: no AI and nothing leaves your server. “Use this” changes only the search-engine title, description or featured image; it never rewrites your page text.', 'sidcraft-rankboost' ) . '</p>';
		if ( ! $r['issues'] ) { echo '<p>' . esc_html__( 'Nothing to improve on this page.', 'sidcraft-rankboost' ) . '</p>'; return; }
		foreach ( $r['issues'] as $i ) {
			echo '<div class="sidcraft-rankboost-banner" style="background:#fff;border-color:#c3c4c7;max-width:1000px"><strong>' . esc_html( $i['msg'] ) . '</strong>';
			$sugg = in_array( $i['id'], Sidcraft_RankBoost_Suggest::supported(), true ) ? Sidcraft_RankBoost_Suggest::for_issue( $post, $i['id'] ) : array();
			if ( ! $sugg ) {
				$adv = Sidcraft_RankBoost_Fixer::advice( $i['id'] );
				echo '<p class="description">' . esc_html( $adv ? $adv : __( 'Nothing to suggest automatically.', 'sidcraft-rankboost' ) ) . '</p></div>';
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
					wp_nonce_field( 'sidcraft_rankboost_action' );
					echo '<input type="hidden" name="action" value="sidcraft_rankboost_action"><input type="hidden" name="do" value="sugg_apply"><input type="hidden" name="id" value="' . (int) $post->ID . '"><input type="hidden" name="what" value="' . esc_attr( $sg['apply'] ) . '"><input type="hidden" name="value" value="' . esc_attr( $sg['value'] ) . '">';
					echo '<button class="button button-primary" style="margin-left:8px">' . esc_html__( 'Use this', 'sidcraft-rankboost' ) . '</button></form>';
				}
				echo '</li>';
			}
			echo '</ul>';
			if ( ! in_array( $i['id'], array( 'title', 'kw_title', 'desc', 'social' ), true ) ) { echo '<p><a href="' . esc_url( get_edit_post_link( $post->ID ) ) . '">' . esc_html__( 'Open the page and make this change →', 'sidcraft-rankboost' ) . '</a></p>'; }
			echo '</div>';
		}
	}

	private static function tab_redirects() {
		global $wpdb;
		echo '<p>Redirects are created automatically when a page address changes, and near-miss broken URLs are repaired by themselves. You can also add one by hand.</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'sidcraft_rankboost_action' );
		echo '<input type="hidden" name="action" value="sidcraft_rankboost_action"><input type="hidden" name="do" value="add_redirect">';
		echo '<input type="text" name="source" placeholder="/old-address" class="regular-text"> → <input type="text" name="target" placeholder="https://example.com/new-address" class="regular-text"> <button class="button button-primary">Add 301 redirect</button></form>';
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY id DESC LIMIT %d', Sidcraft_RankBoost_Redirects::table(), 200 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom plugin table.
		echo '<h2>Redirects</h2><table class="widefat striped"><thead><tr><th>From</th><th>To</th><th>Type</th><th>Used</th><th></th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			echo '<tr><td>' . esc_html( $r->source ) . '</td><td>' . esc_html( $r->target ) . '</td><td>' . ( $r->auto ? 'automatic' : 'manual' ) . '</td><td>' . (int) $r->hits . '</td><td>';
			self::button( 'del_redirect', __( 'Delete', 'sidcraft-rankboost' ), array( 'id' => $r->id ) );
			echo '</td></tr>';
		}
		if ( ! $rows ) { echo '<tr><td colspan="5">None yet.</td></tr>'; }
		echo '</tbody></table>';
		echo '</tbody></table>';
		/* translators: %s: link to the Broken URLs tab */
		echo '<p>' . wp_kses_post( sprintf( __( 'Addresses visitors reached that do not exist are listed under %s, where you can fix them.', 'sidcraft-rankboost' ), '<a href="' . esc_url( admin_url( 'admin.php?page=sidcraft-rankboost&tab=urls' ) ) . '">' . esc_html__( 'Broken URLs', 'sidcraft-rankboost' ) . '</a>' ) ) . '</p>';
	}

	private static function tab_settings() {
		$o = Sidcraft_RankBoost_Settings::all();
		$boxes = array(
			'title_suffix' => __( 'Add the site name to page titles when it fits', 'sidcraft-rankboost' ),
			'social_cards' => __( 'Social sharing cards (Facebook, LinkedIn, X)', 'sidcraft-rankboost' ),
			'schema'       => __( 'Structured data (organization, article, breadcrumbs, FAQ)', 'sidcraft-rankboost' ),
			'sitemap'      => __( 'Improve the XML sitemap (dates, hide noindex pages)', 'sidcraft-rankboost' ),
			'auto_redirects' => __( 'Create 301 redirects automatically when a page address changes', 'sidcraft-rankboost' ),
			'fuzzy_404'    => __( 'Repair near-miss broken URLs by redirecting to the closest page', 'sidcraft-rankboost' ),
			'junk_block'   => __( 'Block for good an address that asks for a junk path such as /-/-/-/-/-/-/ (Block needs Sidcraft BotVanguard)', 'sidcraft-rankboost' ),
			'auto_alt'     => __( 'Write image alt text automatically', 'sidcraft-rankboost' ),
			'related_links' => __( 'Add a short “You may also like” list to posts that link to no other page', 'sidcraft-rankboost' ),
			'check_external' => __( 'Broken-link scan also checks links to other websites (sends a request to each linked site)', 'sidcraft-rankboost' ),
			'related_pages' => __( 'Also add the “You may also like” list to pages that link to no other page', 'sidcraft-rankboost' ),
			'auto_excerpt' => __( 'Write a short excerpt for posts that have none', 'sidcraft-rankboost' ),
			'indexnow'     => __( 'Tell Bing & other IndexNow search engines when pages are published or changed', 'sidcraft-rankboost' ),
			'hygiene'      => __( 'Tidy-ups: remove generator tags, redirect attachment pages', 'sidcraft-rankboost' ),
			'redirect_author' => __( 'Single-author site: redirect the author archive to the home page', 'sidcraft-rankboost' ),
			'noindex_thin_tags' => __( 'Keep tag pages with fewer than 2 posts out of search results', 'sidcraft-rankboost' ),
			'daily_audit'  => __( 'Re-check every page once a day and repair what can be repaired', 'sidcraft-rankboost' ),
			'remove_data'  => __( 'Delete all RankBoost data when the plugin is deleted', 'sidcraft-rankboost' ),
		);
		echo '<form method="post" action="options.php">';
		settings_fields( 'sidcraft_rankboost_settings_group' );
		echo '<table class="form-table"><tbody>';
		foreach ( $boxes as $k => $label ) {
			printf( '<tr><th scope="row">%s</th><td><label><input type="checkbox" name="%s[%s]" value="1" %s> On</label></td></tr>', esc_html( $label ), esc_attr( Sidcraft_RankBoost_Settings::OPTION ), esc_attr( $k ), checked( ! empty( $o[ $k ] ), true, false ) );
		}
		$jr = Sidcraft_RankBoost_Redirects::recent_junk();
		echo '<tr><th scope="row">' . esc_html__( 'Junk paths: more patterns', 'sidcraft-rankboost' ) . '</th><td><textarea name="' . esc_attr( Sidcraft_RankBoost_Settings::OPTION ) . '[junk_extra]" rows="4" class="large-text code">' . esc_textarea( $o['junk_extra'] ) . '</textarea><p class="description">' . esc_html__( 'One per line, at least 4 characters. A visitor whose address (or search words) contains a line is treated like the built-in junk pattern: a path made only of slashes and dashes, such as /-/-/-/-/-/-/. Case does not matter.', 'sidcraft-rankboost' ) . '</p><p class="description">' . esc_html__( 'Genuine search engine crawlers, addresses on the BotVanguard never-block list, and logged-in users are never blocked. Without Sidcraft BotVanguard the request is refused but the address cannot be blocked for good.', 'sidcraft-rankboost' ) . '</p>';
		if ( $jr ) { echo '<p><strong>' . esc_html__( 'Blocked by this rule recently:', 'sidcraft-rankboost' ) . '</strong></p><ul style="margin:0 0 0 18px;list-style:disc">'; foreach ( $jr as $j ) { echo '<li><code>' . esc_html( $j['ip'] ) . '</code> · ' . esc_html( human_time_diff( $j['t'] ) ) . ' ' . esc_html__( 'ago', 'sidcraft-rankboost' ) . ' · <code>' . esc_html( $j['path'] ) . '</code> · ' . esc_html( ! empty( $j['perm'] ) ? __( 'blocked for good', 'sidcraft-rankboost' ) : __( 'refused (BotVanguard not active)', 'sidcraft-rankboost' ) ) . '</li>'; } echo '</ul>'; }
		echo '</td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Posts to check', 'sidcraft-rankboost' ) . '</th><td><input type="number" min="0" step="100" class="small-text" name="' . esc_attr( Sidcraft_RankBoost_Settings::OPTION ) . '[audit_limit]" value="' . (int) Sidcraft_RankBoost_Settings::get( 'audit_limit' ) . '"> <span class="description">' . esc_html__( 'The audit and the broken-link scan check all pages and your newest posts, up to this number. Keep it modest on a site with thousands of posts, otherwise the scan keeps the server busy. 0 = every post. New and edited posts are always checked when saved.', 'sidcraft-rankboost' ) . '</span></td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Related list style', 'sidcraft-rankboost' ) . '</th><td><select name="' . esc_attr( Sidcraft_RankBoost_Settings::OPTION ) . '[related_style]"><option value="collapsed" ' . selected( $o['related_style'], 'collapsed', false ) . '>' . esc_html__( 'Folded (click to open)', 'sidcraft-rankboost' ) . '</option><option value="open" ' . selected( $o['related_style'], 'open', false ) . '>' . esc_html__( 'Always open', 'sidcraft-rankboost' ) . '</option></select><p class="description">' . esc_html__( 'The home page never shows this list. On any other page you can switch it off in that page’s Sidcraft RankBoost box.', 'sidcraft-rankboost' ) . '</p></td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Title separator', 'sidcraft-rankboost' ) . '</th><td><input type="text" size="3" name="' . esc_attr( Sidcraft_RankBoost_Settings::OPTION ) . '[title_sep]" value="' . esc_attr( $o['title_sep'] ) . '"></td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Default sharing image URL', 'sidcraft-rankboost' ) . '</th><td><input type="url" class="regular-text" name="' . esc_attr( Sidcraft_RankBoost_Settings::OPTION ) . '[default_image]" value="' . esc_attr( $o['default_image'] ) . '"><p class="description">' . esc_html__( 'Used when a page has no featured image and no image in its text. Leave empty to use the site icon.', 'sidcraft-rankboost' ) . '</p></td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'X (Twitter) username', 'sidcraft-rankboost' ) . '</th><td><input type="text" name="' . esc_attr( Sidcraft_RankBoost_Settings::OPTION ) . '[twitter_site]" value="' . esc_attr( $o['twitter_site'] ) . '" placeholder="@yourname"></td></tr>';
		echo '</tbody></table>';
		submit_button();
		echo '</form>';
	}

	/* ---------- actions ---------- */

	public static function action() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Not allowed.', 403 ); }
		check_admin_referer( 'sidcraft_rankboost_action' );
		$do  = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		$msg = '';
		$tab = 'overview';
		switch ( $do ) {
			case 'audit':
				Sidcraft_RankBoost_Audit::reset_marks();
				set_transient( 'sidcraft_rankboost_resume', 1, 10 * MINUTE_IN_SECONDS );
				wp_schedule_single_event( time() + 60, 'sidcraft_rankboost_audit_more' );
				$msg = __( 'Audit started. It runs a few pages at a time while this page is open.', 'sidcraft-rankboost' );
				break;
			case 'fix':
				$what = isset( $_POST['what'] ) ? sanitize_key( wp_unslash( $_POST['what'] ) ) : '';
				if ( ! isset( Sidcraft_RankBoost_Fixer::catalog()[ $what ] ) ) { $msg = __( 'Unknown fix.', 'sidcraft-rankboost' ); break; }
				Sidcraft_RankBoost_Fixer::job_start( $what );
				$msg = __( 'The fix is running a few pages at a time. Keep this page open until it says it is finished.', 'sidcraft-rankboost' );
				break;
			case 'fix_all':
				Sidcraft_RankBoost_Fixer::job_start( 'all' );
				$msg = __( 'The fixes are running a few pages at a time. Keep this page open until it says they are finished.', 'sidcraft-rankboost' );
				break;
			case 'fix_post':
				$tab  = 'pages';
				$pid  = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
				if ( $pid && current_user_can( 'edit_post', $pid ) ) {
					$n   = Sidcraft_RankBoost_Fixer::fix_post( $pid );
					/* translators: %d: number of fixes applied. */
					$msg = sprintf( _n( '%d fix applied and the page re-checked.', '%d fixes applied and the page re-checked.', $n, 'sidcraft-rankboost' ), $n );
					Sidcraft_RankBoost_Audit::summary( true );
				}
				break;
			case 'links_scan':
				$tab = 'links';
				Sidcraft_RankBoost_Links::start();
				$more = Sidcraft_RankBoost_Links::batch( 25 );
				if ( $more ) { wp_schedule_single_event( time() + 15, Sidcraft_RankBoost_Links::EVENT ); }
				$msg = $more ? __( 'Scan started. It carries on in the background; refresh this page in a minute.', 'sidcraft-rankboost' ) : __( 'Scan finished.', 'sidcraft-rankboost' );
				break;
			case 'link_replace':
			case 'link_redirect':
			case 'link_unlink':
			case 'link_ignore':
				$tab = 'links';
				$msg = Sidcraft_RankBoost_Links::repair( isset( $_POST['lid'] ) ? sanitize_key( wp_unslash( $_POST['lid'] ) ) : '', substr( $do, 5 ), isset( $_POST['new_url'] ) ? esc_url_raw( wp_unslash( $_POST['new_url'] ) ) : '' );
				break;
			case 'urls_gone':
				$tab = 'urls';
				$pre = isset( $_POST['prefix'] ) ? sanitize_text_field( wp_unslash( $_POST['prefix'] ) ) : '';
				$n   = Sidcraft_RankBoost_Redirects::mark_gone( $pre );
				if ( false === $n ) { $msg = __( 'That folder cannot be marked as gone: it is too short, or a real page of your site lives there.', 'sidcraft-rankboost' ); }
				else { /* translators: %d: number of addresses */ $msg = sprintf( _n( 'Done. It now answers “410 Gone”. %d address was removed from the list.', 'Done. It now answers “410 Gone”. %d addresses were removed from the list.', $n, 'sidcraft-rankboost' ), $n ); }
				break;
			case 'urls_gone_remove':
				$tab = 'urls';
				Sidcraft_RankBoost_Redirects::unmark_gone( isset( $_POST['prefix'] ) ? sanitize_text_field( wp_unslash( $_POST['prefix'] ) ) : '' );
				$msg = __( 'Removed. Addresses there answer “404 not found” again.', 'sidcraft-rankboost' );
				break;
			case 'url_check_ip':
			case 'url_block_ip':
			case 'url_unblock_ip':
				$tab = 'urls';
				$msg = self::ip_action( $do, isset( $_POST['ip'] ) ? sanitize_text_field( wp_unslash( $_POST['ip'] ) ) : '' );
				break;
			case 'url_redirect':
			case 'url_dismiss':
			case 'urls_dismiss_all':
			case 'urls_fix_all':
				$tab = 'urls';
				global $wpdb;
				if ( 'urls_dismiss_all' === $do ) {
					$n = Sidcraft_RankBoost_Redirects::clear_404();
					/* translators: %d: number of addresses */
					$msg = sprintf( _n( 'Dismissed %d address.', 'Dismissed %d addresses.', $n, 'sidcraft-rankboost' ), $n );
					break;
				}
				if ( 'urls_fix_all' === $do ) {
					$n = 0;
					foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT id, path FROM %i LIMIT %d', Sidcraft_RankBoost_Redirects::table404(), 200 ) ) as $r ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom plugin table.
						$t = Sidcraft_RankBoost_Redirects::guess( $r->path, 70 );
						if ( $t && Sidcraft_RankBoost_Redirects::add( $r->path, $t ) ) { ++$n; }
					}
					/* translators: %d: number of redirects */
					$msg = sprintf( _n( '%d redirect created.', '%d redirects created.', $n, 'sidcraft-rankboost' ), $n );
					break;
				}
				$uid = isset( $_POST['uid'] ) ? (int) $_POST['uid'] : 0;
				$row = $wpdb->get_row( $wpdb->prepare( 'SELECT path FROM %i WHERE id = %d', Sidcraft_RankBoost_Redirects::table404(), $uid ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom plugin table.
				if ( ! $row ) { $msg = __( 'That address is no longer in the list.', 'sidcraft-rankboost' ); break; }
				if ( 'url_dismiss' === $do ) { Sidcraft_RankBoost_Redirects::delete_404( $uid ); $msg = __( 'Dismissed. It comes back only if someone requests it again.', 'sidcraft-rankboost' ); break; }
				$ok  = Sidcraft_RankBoost_Redirects::add( $row->path, isset( $_POST['new_url'] ) ? esc_url_raw( wp_unslash( $_POST['new_url'] ) ) : '' );
				$msg = $ok ? __( 'Redirect saved. Anyone who asks for that address now arrives at the new page.', 'sidcraft-rankboost' ) : __( 'Could not save: choose a different address.', 'sidcraft-rankboost' );
				break;
			case 'sugg_apply':
				$tab = 'suggest';
				$pid = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
				$ok  = Sidcraft_RankBoost_Suggest::apply( $pid, isset( $_POST['what'] ) ? sanitize_key( wp_unslash( $_POST['what'] ) ) : '', isset( $_POST['value'] ) ? sanitize_text_field( wp_unslash( $_POST['value'] ) ) : '' );
				$sc  = (int) get_post_meta( $pid, '_sidcraft_rankboost_score', true );
				/* translators: %d: new score */
				$msg = $ok ? sprintf( __( 'Applied. The page now scores %d.', 'sidcraft-rankboost' ), $sc ) : __( 'Could not apply that suggestion.', 'sidcraft-rankboost' );
				wp_safe_redirect( add_query_arg( array( 'page' => 'sidcraft-rankboost', 'tab' => 'suggest', 'id' => $pid, 'done' => rawurlencode( $msg ) ), admin_url( 'admin.php' ) ) );
				exit;
			case 'links_fix_all':
				$tab = 'links';
				$msg = Sidcraft_RankBoost_Links::fix_all();
				break;
			case 'alt':
				$msg = sprintf( 'Alt text written for %d images.', Sidcraft_RankBoost_Images::fix_library( 300 ) );
				break;
			case 'add_redirect':
				$tab = 'redirects';
				$ok  = Sidcraft_RankBoost_Redirects::add( isset( $_POST['source'] ) ? sanitize_text_field( wp_unslash( $_POST['source'] ) ) : '', isset( $_POST['target'] ) ? esc_url_raw( wp_unslash( $_POST['target'] ) ) : '' );
				$msg = $ok ? 'Redirect saved.' : 'Could not save: check both addresses.';
				break;
			case 'del_redirect':
				$tab = 'redirects';
				Sidcraft_RankBoost_Redirects::delete( isset( $_POST['id'] ) ? (int) $_POST['id'] : 0 );
				$msg = 'Redirect deleted.';
				break;
		}
		wp_safe_redirect( add_query_arg( array( 'page' => 'sidcraft-rankboost', 'tab' => $tab, 'done' => rawurlencode( $msg ) ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/* ---------- post screen ---------- */

	public static function metabox() {
		foreach ( Sidcraft_RankBoost_Util::post_types() as $pt ) { add_meta_box( 'sidcraft_rankboost_box', 'Sidcraft RankBoost', array( __CLASS__, 'box' ), $pt, 'side', 'default' ); }
	}

	public static function box( $post ) {
		wp_nonce_field( 'sidcraft_rankboost_box', 'sidcraft_rankboost_box_nonce' );
		$r = 'publish' === $post->post_status ? Sidcraft_RankBoost_Audit::analyze( $post ) : null;
		echo '<p><strong>Search result preview</strong><br><span style="color:#1a0dab">' . esc_html( Sidcraft_RankBoost_Meta::title_for_post( $post ) ) . '</span><br><span style="color:#4d5156;font-size:12px">' . esc_html( Sidcraft_RankBoost_Meta::description_for_post( $post ) ) . '</span></p>';
		if ( $r ) {
			echo '<p>Score ' . wp_kses_post( self::badge( $r['score'] ) ) . '</p>';
			if ( $r['issues'] ) { echo '<ul style="list-style:disc;margin-left:18px">'; foreach ( array_slice( $r['issues'], 0, 5 ) as $i ) { echo '<li>' . esc_html( $i['msg'] ) . '</li>'; } echo '</ul>'; }
		} else { echo '<p>The score appears after you publish.</p>'; }
		if ( $r && $r['issues'] ) { echo '<p><a href="' . esc_url( add_query_arg( array( 'page' => 'sidcraft-rankboost', 'tab' => 'suggest', 'id' => $post->ID ), admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'See suggestions for this page →', 'sidcraft-rankboost' ) . '</a></p>'; }
		echo '<p class="description">Everything above is generated automatically. Fill in a field only if you want to override it.</p>';
		foreach ( array( 'title' => 'Title', 'desc' => 'Description', 'focus' => 'Main phrase' ) as $k => $label ) {
			printf( '<p><label>%s<br><input type="text" class="widefat" name="sidcraft_rankboost_%s" value="%s"></label></p>', esc_html( $label ), esc_attr( $k ), esc_attr( Sidcraft_RankBoost_Meta::override( $post->ID, $k ) ) );
		}
		printf( '<p><label><input type="checkbox" name="sidcraft_rankboost_no_related" value="1" %s> %s</label></p>', checked( (bool) get_post_meta( $post->ID, '_sidcraft_rankboost_no_related', true ), true, false ), esc_html__( 'Do not show a “You may also like” list on this page', 'sidcraft-rankboost' ) );
		printf( '<p><label><input type="checkbox" name="sidcraft_rankboost_noindex" value="1" %s> Hide from search engines</label></p>', checked( (bool) Sidcraft_RankBoost_Meta::override( $post->ID, 'noindex' ), true, false ) );
	}

	public static function save_box( $post_id, $post ) {
		if ( ! isset( $_POST['sidcraft_rankboost_box_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sidcraft_rankboost_box_nonce'] ) ), 'sidcraft_rankboost_box' ) ) { return; }
		if ( wp_is_post_autosave( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) { return; }
		foreach ( array( 'title', 'desc', 'focus' ) as $k ) {
			$v = isset( $_POST[ 'sidcraft_rankboost_' . $k ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'sidcraft_rankboost_' . $k ] ) ) : '';
			if ( '' === $v ) { delete_post_meta( $post_id, '_sidcraft_rankboost_' . $k ); } else { update_post_meta( $post_id, '_sidcraft_rankboost_' . $k, $v ); }
		}
		if ( ! empty( $_POST['sidcraft_rankboost_no_related'] ) ) { update_post_meta( $post_id, '_sidcraft_rankboost_no_related', 1 ); } else { delete_post_meta( $post_id, '_sidcraft_rankboost_no_related' ); }
		if ( ! empty( $_POST['sidcraft_rankboost_noindex'] ) ) { update_post_meta( $post_id, '_sidcraft_rankboost_noindex', 1 ); } else { delete_post_meta( $post_id, '_sidcraft_rankboost_noindex' ); }
	}

	public static function col( $cols ) { $cols['aseo'] = 'SEO'; return $cols; }

	public static function col_cell( $col, $post_id ) {
		if ( 'aseo' !== $col ) { return; }
		$s = get_post_meta( $post_id, '_sidcraft_rankboost_score', true );
		echo '' === $s ? '—' : wp_kses_post( self::badge( (int) $s ) );
	}
}
