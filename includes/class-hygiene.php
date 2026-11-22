<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Small technical clean-ups that search engines like. */
class Sidcraft_RankBoost_Hygiene {

	public static function init() {
		if ( ! Sidcraft_RankBoost_Settings::on( 'hygiene' ) ) { return; }
		remove_action( 'wp_head', 'wp_generator' );
		remove_action( 'wp_head', 'rsd_link' );
		remove_action( 'wp_head', 'wlwmanifest_link' );
		remove_action( 'wp_head', 'wp_shortlink_wp_head', 10 );
		add_filter( 'the_generator', '__return_empty_string' );
		add_action( 'template_redirect', array( __CLASS__, 'redirects' ), 1 );
	}

	public static function redirects() {
		if ( is_admin() ) { return; }
		// Attachment pages are thin duplicates: send people (and bots) to the parent post or the file.
		if ( is_attachment() ) {
			$post = get_queried_object();
			if ( $post && $post->post_parent && 'publish' === get_post_status( $post->post_parent ) ) {
				wp_safe_redirect( get_permalink( $post->post_parent ), 301 );
			} else {
				wp_safe_redirect( wp_get_attachment_url( $post->ID ), 301 );
			}
			exit;
		}
		// Single-author sites: the author archive repeats the blog page.
		if ( is_author() && Sidcraft_RankBoost_Settings::on( 'redirect_author' ) && (int) count_users()['total_users'] <= 1 ) {
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
		// ?replytocom=123 creates a duplicate of every post with comments.
		if ( isset( $_GET['replytocom'] ) && is_singular() ) { // phpcs:ignore WordPress.Security.NonceVerification
			wp_safe_redirect( get_permalink(), 301 );
			exit;
		}
	}
}
