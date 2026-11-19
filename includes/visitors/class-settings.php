<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Plugin settings: one option, sensible defaults. */
class Sidcraft_VS_Settings {

	const OPTION = 'sidcraft_vs_settings';

	public static function defaults() {
		return array(
			'enabled'          => 1,
			'exclude_roles'    => array( 'administrator' ),
			'exclude_ips'      => '',
			'track_bots'       => 0,
			'respect_dnt'      => 1,
			'proxy'            => 'none',
			'retention_days'   => 365,
			'track_404'        => 1,
			'show_widget'      => 1,
			'delete_on_uninstall' => 0,
		);
	}

	public static function all() {
		$o = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $o ) ? $o : array(), self::defaults() );
	}

	public static function sanitize( $in ) {
		$d = self::defaults(); $o = array();
		$roles = array_keys( wp_roles()->get_names() );
		foreach ( array( 'enabled', 'track_bots', 'respect_dnt', 'track_404', 'show_widget', 'delete_on_uninstall' ) as $k ) { $o[ $k ] = empty( $in[ $k ] ) ? 0 : 1; }
		$o['exclude_roles'] = array();
		if ( ! empty( $in['exclude_roles'] ) && is_array( $in['exclude_roles'] ) ) {
			foreach ( $in['exclude_roles'] as $r ) { $r = sanitize_key( $r ); if ( in_array( $r, $roles, true ) ) { $o['exclude_roles'][] = $r; } }
		}
		$ips = array();
		foreach ( preg_split( '/\R/', isset( $in['exclude_ips'] ) ? sanitize_textarea_field( $in['exclude_ips'] ) : '' ) as $l ) {
			$l = trim( $l ); if ( '' !== $l && false !== filter_var( $l, FILTER_VALIDATE_IP ) ) { $ips[] = $l; }
		}
		$o['exclude_ips']    = implode( "\n", array_slice( array_unique( $ips ), 0, 200 ) );
		$o['proxy']          = isset( $in['proxy'] ) && in_array( $in['proxy'], array( 'none', 'cloudflare', 'xff' ), true ) ? $in['proxy'] : $d['proxy'];
		$o['retention_days'] = isset( $in['retention_days'] ) ? max( 0, min( 3650, (int) $in['retention_days'] ) ) : $d['retention_days'];
		return $o;
	}

	/** Non-empty trimmed lines of a text box. */
	public static function lines( $text ) {
		$out = array();
		foreach ( preg_split( '/\R/', (string) $text ) as $l ) { $l = trim( $l ); if ( '' !== $l ) { $out[] = $l; } }
		return $out;
	}
}
