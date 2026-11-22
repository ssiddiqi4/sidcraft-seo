<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Manual SEO fields for one page: title, description, main phrase, canonical address, search-engine visibility and social sharing card.
 * They appear in a box on the editing screen and also on a screen inside Sidcraft RankBoost, so they work with any page builder.
 * Anything left empty stays automatic.
 */
class Sidcraft_RankBoost_Editor {
	const NONCE = 'sidcraft_rankboost_editor';

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_box' ) );
		add_action( 'save_post', array( __CLASS__, 'on_save' ), 5, 2 );
		add_action( 'admin_post_sidcraft_rankboost_edit', array( __CLASS__, 'on_post' ) );
	}

	public static function add_box() {
		foreach ( Sidcraft_RankBoost_Util::post_types() as $pt ) { add_meta_box( 'sidcraft-rankboost-box', 'Sidcraft RankBoost', array( __CLASS__, 'box' ), $pt, 'normal', 'default' ); }
	}

	public static function box( $post ) {
		wp_nonce_field( self::NONCE . $post->ID, 'sidcraft_rankboost_nonce' );
		self::fields( $post );
		echo '<p class="description">' . esc_html__( 'Leave a field empty to keep it automatic. What you type here is used exactly as written.', 'sidcraft-rankboost' ) . '</p>';
	}

	/** Meta keys and how to clean each. */
	public static function schema() {
		return array( 'title' => 'text', 'desc' => 'text', 'focus' => 'text', 'canonical' => 'url', 'noindex' => 'flag', 'nofollow' => 'flag', 'og_title' => 'text', 'og_desc' => 'text', 'og_image' => 'url' );
	}

	/** Save posted values. Empty values are removed so that the automatic value is used again. */
	public static function save( $post_id, array $in ) {
		foreach ( self::schema() as $key => $type ) {
			$raw = isset( $in[ $key ] ) ? wp_unslash( $in[ $key ] ) : '';
			if ( 'flag' === $type ) { $v = ! empty( $raw ) ? '1' : ''; }
			elseif ( 'url' === $type ) { $v = '' === trim( (string) $raw ) ? '' : esc_url_raw( trim( (string) $raw ) ); }
			else { $v = sanitize_text_field( (string) $raw ); }
			$v = trim( $v );
			if ( '' === $v ) { delete_post_meta( $post_id, '_sidcraft_rankboost_' . $key ); } else { update_post_meta( $post_id, '_sidcraft_rankboost_' . $key, $v ); }
		}
		if ( '' === Sidcraft_RankBoost_Meta::override( $post_id, 'focus' ) ) { delete_post_meta( $post_id, '_sidcraft_rankboost_focus' ); }
		do_action( 'sidcraft_rankboost_manual_saved', $post_id );
	}

	public static function on_save( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ! isset( $_POST['sidcraft_rankboost_nonce'] ) ) { return; }
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sidcraft_rankboost_nonce'] ) ), self::NONCE . $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) { return; }
		$in = array();
		foreach ( array_keys( self::schema() ) as $k ) { $f = 'sidcraft_rankboost_' . $k; if ( isset( $_POST[ $f ] ) ) { $in[ $k ] = $_POST[ $f ]; } } // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- cleaned in save().
		self::save( $post_id, $in );
	}

	/** Form on the RankBoost screen. */
	public static function on_post() {
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		if ( ! $id || ! current_user_can( 'edit_post', $id ) ) { wp_die( esc_html__( 'Not allowed.', 'sidcraft-rankboost' ), 403 ); }
		check_admin_referer( self::NONCE . $id, 'sidcraft_rankboost_nonce' );
		$in = array();
		foreach ( array_keys( self::schema() ) as $k ) { $f = 'sidcraft_rankboost_' . $k; if ( isset( $_POST[ $f ] ) ) { $in[ $k ] = $_POST[ $f ]; } } // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- cleaned in save().
		self::save( $id, $in );
		Sidcraft_RankBoost_Audit::store( $id );
		wp_safe_redirect( add_query_arg( array( 'page' => 'sidcraft-rankboost', 'tab' => 'edit', 'id' => $id, 'done' => rawurlencode( __( 'Saved.', 'sidcraft-rankboost' ) ) ), admin_url( 'admin.php' ) ) );
		exit;
	}

	private static function row( $label, $html, $help = '' ) {
		echo '<tr><th style="width:170px;vertical-align:top;padding-top:12px"><label>' . esc_html( $label ) . '</label></th><td>' . $html . ( '' !== $help ? '<p class="description" style="margin:4px 0 0">' . esc_html( $help ) . '</p>' : '' ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
	}

	private static function val( $post, $key ) { return Sidcraft_RankBoost_Meta::override( $post->ID, $key ); }

	/** The fields themselves (used by the editing-screen box and by the RankBoost screen). */
	public static function fields( $post ) {
		$auto_title = Sidcraft_RankBoost_Meta::auto_title( $post ); $auto_desc = Sidcraft_RankBoost_Meta::auto_description( $post );
		$f = function ( $k ) { return 'sidcraft_rankboost_' . $k; };
		$in = function ( $k, $ph = '', $type = 'text', $extra = '' ) use ( $post, $f ) { return '<input type="' . esc_attr( $type ) . '" id="' . esc_attr( $f( $k ) ) . '" name="' . esc_attr( $f( $k ) ) . '" value="' . esc_attr( self::val( $post, $k ) ) . '" placeholder="' . esc_attr( $ph ) . '" class="large-text" ' . $extra . '>'; };
		$ta = function ( $k, $ph = '' ) use ( $post, $f ) { return '<textarea id="' . esc_attr( $f( $k ) ) . '" name="' . esc_attr( $f( $k ) ) . '" rows="3" class="large-text" placeholder="' . esc_attr( $ph ) . '">' . esc_textarea( self::val( $post, $k ) ) . '</textarea>'; };
		$r = Sidcraft_RankBoost_Audit::analyze( $post );
		echo '<div id="sidcraft-rankboost-fields">';
		echo '<p style="margin:6px 0 10px">' . wp_kses_post( Sidcraft_RankBoost_Admin::badge( $r['score'] ) ) . ' <span class="description">' . esc_html__( 'Score for this page. It updates when you save.', 'sidcraft-rankboost' ) . '</span>';
		if ( $r['issues'] ) { echo '<br>'; foreach ( array_slice( $r['issues'], 0, 4 ) as $i ) { echo '<span class="description" style="display:block">• ' . esc_html( $i['msg'] ) . '</span>'; } }
		echo '</p>';
		// Search result preview.
		echo '<div id="sidcraft-rankboost-preview" style="border:1px solid #dcdcde;border-radius:6px;padding:10px 14px;margin:0 0 10px;background:#fff;max-width:640px"><div style="font:13px Arial,sans-serif;color:#202124" id="sidcraft-pv-url">' . esc_html( preg_replace( '#^https?://#', '', (string) get_permalink( $post ) ) ) . '</div><div style="font:20px/1.3 Arial,sans-serif;color:#1a0dab" id="sidcraft-pv-title"></div><div style="font:14px/1.58 Arial,sans-serif;color:#4d5156" id="sidcraft-pv-desc"></div></div>';
		echo '<table class="form-table" role="presentation" style="margin-top:0"><tbody>';
		self::row( __( 'SEO title', 'sidcraft-rankboost' ), $in( 'title', $auto_title ) . '<span class="description" id="sidcraft-len-title"></span>', __( 'The headline in search results. About 50 to 60 characters.', 'sidcraft-rankboost' ) );
		self::row( __( 'Meta description', 'sidcraft-rankboost' ), $ta( 'desc', $auto_desc ) . '<span class="description" id="sidcraft-len-desc"></span>', __( 'The text under the headline. About 120 to 158 characters.', 'sidcraft-rankboost' ) );
		self::row( __( 'Main phrase', 'sidcraft-rankboost' ), $in( 'focus', Sidcraft_RankBoost_Meta::focus_for_post( $post->ID ) ), __( 'The words people would search for to find this page.', 'sidcraft-rankboost' ) );
		self::row( __( 'Canonical address', 'sidcraft-rankboost' ), $in( 'canonical', (string) get_permalink( $post ), 'url' ), __( 'Only change this if the same content exists at another address that should be the official one.', 'sidcraft-rankboost' ) );
		self::row( __( 'Search engines', 'sidcraft-rankboost' ), '<input type="hidden" name="' . esc_attr( $f( 'noindex' ) ) . '" value=""><label><input type="checkbox" name="' . esc_attr( $f( 'noindex' ) ) . '" value="1" ' . checked( '' !== self::val( $post, 'noindex' ), true, false ) . '> ' . esc_html__( 'Hide this page from search engines (noindex)', 'sidcraft-rankboost' ) . '</label><br><input type="hidden" name="' . esc_attr( $f( 'nofollow' ) ) . '" value=""><label><input type="checkbox" name="' . esc_attr( $f( 'nofollow' ) ) . '" value="1" ' . checked( '' !== self::val( $post, 'nofollow' ), true, false ) . '> ' . esc_html__( 'Do not follow the links on this page (nofollow)', 'sidcraft-rankboost' ) . '</label>' );
		self::row( __( 'Social share title', 'sidcraft-rankboost' ), $in( 'og_title', $auto_title ), __( 'Shown when the page is shared on Facebook, LinkedIn, X and messengers. Empty = the SEO title.', 'sidcraft-rankboost' ) );
		self::row( __( 'Social share text', 'sidcraft-rankboost' ), $ta( 'og_desc', $auto_desc ), __( 'Empty = the meta description.', 'sidcraft-rankboost' ) );
		$img = Sidcraft_RankBoost_Util::post_image( $post );
		self::row( __( 'Social share image', 'sidcraft-rankboost' ), $in( 'og_image', ! empty( $img['url'] ) ? $img['url'] : '', 'url' ) . '<p style="margin:6px 0 0"><button type="button" class="button" id="sidcraft-pick-image">' . esc_html__( 'Choose image', 'sidcraft-rankboost' ) . '</button></p>', __( 'Best at 1200 × 630 pixels. Empty = the featured or first image of the page.', 'sidcraft-rankboost' ) );
		echo '</tbody></table></div>';
		self::script( $auto_title, $auto_desc );
	}

	private static function script( $auto_title, $auto_desc ) {
		static $done = false; if ( $done ) { return; } $done = true;
		wp_enqueue_media();
		wp_register_script( 'sidcraft-rankboost-editor', false, array( 'jquery' ), SIDCRAFT_RANKBOOST_VERSION, true ); wp_enqueue_script( 'sidcraft-rankboost-editor' );
		$cfg = wp_json_encode( array( 'title' => $auto_title, 'desc' => $auto_desc, 'pick' => __( 'Choose the social share image', 'sidcraft-rankboost' ), 'use' => __( 'Use this image', 'sidcraft-rankboost' ), 'chars' => __( 'characters', 'sidcraft-rankboost' ) ) );
		wp_add_inline_script( 'sidcraft-rankboost-editor', '(function($){var C=' . $cfg . ';function g(i){return document.getElementById(i)}var t=g("sidcraft_rankboost_title"),d=g("sidcraft_rankboost_desc");if(!t||!d)return;' .
			'function cls(n,min,max,el){el.style.color=(n===0?"#646970":(n<min||n>max)?"#b32d2e":"#007a5a")}' .
			'function upd(){var tt=t.value||C.title,dd=d.value||C.desc;g("sidcraft-pv-title").textContent=tt;g("sidcraft-pv-desc").textContent=dd;var a=g("sidcraft-len-title"),b=g("sidcraft-len-desc");a.textContent=" "+t.value.length+" "+C.chars;b.textContent=" "+d.value.length+" "+C.chars;cls(t.value.length,30,60,a);cls(d.value.length,70,158,b)}' .
			't.addEventListener("input",upd);d.addEventListener("input",upd);upd();' .
			'var pb=g("sidcraft-pick-image");if(pb&&window.wp&&wp.media){var fr;pb.addEventListener("click",function(){if(!fr){fr=wp.media({title:C.pick,button:{text:C.use},multiple:false});fr.on("select",function(){var at=fr.state().get("selection").first().toJSON();g("sidcraft_rankboost_og_image").value=at.url})}fr.open()})}})(jQuery);' );
	}
}
