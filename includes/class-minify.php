<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Minifiers for CSS, JavaScript and HTML, plus the filters that serve minified copies of the site's own
 * stylesheets and scripts. Originals are never changed: a smaller copy is written to the uploads folder
 * and the page points to it. If anything looks wrong the original file is used.
 */
class Sidcraft_RankBoost_Minify {

	/* ---------------------------------------------------------------- CSS */

	/** Minify CSS. $base is the URL of the folder the file came from, so url() paths keep working. Returns '' to refuse. */
	public static function css( $css, $base = '' ) {
		if ( false !== stripos( $css, '@import' ) ) { return ''; }
		$css = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $css );
		$n   = strlen( $css ); $out = ''; $i = 0;
		while ( $i < $n ) {
			$c = $css[ $i ];
			if ( '"' === $c || "'" === $c ) { $e = self::str_end( $css, $i, $n ); $out .= substr( $css, $i, $e - $i ); $i = $e; continue; }
			if ( '/' === $c && $i + 1 < $n && '*' === $css[ $i + 1 ] ) { $e = strpos( $css, '*/', $i + 2 ); $i = false === $e ? $n : $e + 2; continue; }
			if ( ( 'u' === $c || 'U' === $c ) && 0 === strncasecmp( substr( $css, $i, 4 ), 'url(', 4 ) ) {
				$e = strpos( $css, ')', $i );
				if ( false === $e ) { return ''; }
				$inner = trim( substr( $css, $i + 4, $e - $i - 4 ) );
				$q = ''; if ( '' !== $inner && ( '"' === $inner[0] || "'" === $inner[0] ) ) { $q = $inner[0]; $inner = trim( $inner, '"\'' ); }
				$out .= 'url(' . $q . self::absolute( $inner, $base ) . $q . ')'; $i = $e + 1; continue;
			}
			if ( ctype_space( $c ) ) {
				while ( $i < $n && ctype_space( $css[ $i ] ) ) { $i++; }
				$last = '' === $out ? '' : $out[ strlen( $out ) - 1 ];
				$next = $i < $n ? $css[ $i ] : '';
				if ( '' === $last || '' === $next || false !== strpos( '{};,>', $last ) || false !== strpos( '{};,>', $next ) ) { continue; }
				$out .= ' '; continue;
			}
			if ( '}' === $c && ';' === substr( $out, -1 ) ) { $out = substr( $out, 0, -1 ); }
			$out .= $c; $i++;
		}
		return trim( $out );
	}

	private static function str_end( $s, $i, $n ) {
		$q = $s[ $i ]; $i++;
		while ( $i < $n ) { if ( '\\' === $s[ $i ] ) { $i += 2; continue; } if ( $s[ $i ] === $q ) { return $i + 1; } $i++; }
		return $n;
	}

	/** Make a url() value absolute against the folder the stylesheet lives in. */
	private static function absolute( $u, $base ) {
		if ( '' === $base || '' === $u || preg_match( '#^([a-z][a-z0-9+.-]*:|//|/|\#)#i', $u ) ) { return $u; }
		$parts = array(); $p = wp_parse_url( $base );
		if ( empty( $p['host'] ) ) { return $u; }
		$root = ( isset( $p['scheme'] ) ? $p['scheme'] . ':' : '' ) . '//' . $p['host'] . ( isset( $p['port'] ) ? ':' . $p['port'] : '' );
		$dir  = isset( $p['path'] ) ? $p['path'] : '/';
		foreach ( explode( '/', trim( $dir, '/' ) . '/' . $u ) as $seg ) {
			if ( '..' === $seg ) { array_pop( $parts ); } elseif ( '.' !== $seg && '' !== $seg ) { $parts[] = $seg; }
		}
		return $root . '/' . implode( '/', $parts );
	}

	/* ---------------------------------------------------------- JavaScript */

	private static $kw = array( 'return', 'typeof', 'instanceof', 'in', 'of', 'new', 'delete', 'void', 'throw', 'case', 'do', 'else', 'yield', 'await' );

	/** Minify JavaScript conservatively: comments and extra spacing go, names and line breaks that matter stay. */
	public static function js( $s ) {
		$s = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $s );
		$n = strlen( $s ); $out = ''; $i = 0; $last = ''; $word = '';
		while ( $i < $n ) {
			$c = $s[ $i ];
			if ( '"' === $c || "'" === $c ) { $e = self::js_str( $s, $i, $n ); $out .= substr( $s, $i, $e - $i ); $i = $e; $last = $c; $word = ''; continue; }
			if ( '`' === $c ) { $e = self::js_tpl( $s, $i, $n ); if ( $e < 0 ) { return ''; } $out .= substr( $s, $i, $e - $i ); $i = $e; $last = '`'; $word = ''; continue; }
			if ( '/' === $c && $i + 1 < $n && ( '/' === $s[ $i + 1 ] || '*' === $s[ $i + 1 ] ) || ctype_space( $c ) ) {
				// A run of spaces, line breaks and comments counts as one gap.
				$nl = false;
				while ( $i < $n ) {
					$x = $s[ $i ];
					if ( ctype_space( $x ) ) { if ( "\n" === $x || "\r" === $x ) { $nl = true; } $i++; continue; }
					if ( '/' === $x && $i + 1 < $n && '/' === $s[ $i + 1 ] ) { $e = strpos( $s, "\n", $i ); $i = false === $e ? $n : $e; continue; }
					if ( '/' === $x && $i + 1 < $n && '*' === $s[ $i + 1 ] ) {
						$e = strpos( $s, '*/', $i + 2 ); if ( false === $e ) { return ''; }
						if ( false !== strpos( substr( $s, $i, $e - $i ), "\n" ) ) { $nl = true; }
						$i = $e + 2; continue;
					}
					break;
				}
				if ( '' === $out || $i >= $n ) { continue; }
				$prev = $out[ strlen( $out ) - 1 ]; $next = $s[ $i ];
				if ( $nl ) {
					if ( "\n" === $prev || false !== strpos( '{([,;=:?&|*%^<>!~', $prev ) || ( false !== strpos( ')]},;.:?=*%&|^<>', $next ) ) ) { continue; }
					$out .= "\n"; continue;
				}
				$wp = ctype_alnum( $prev ) || '_' === $prev || '$' === $prev || '\\' === $prev || ord( $prev ) > 127;
				$wn = ctype_alnum( $next ) || '_' === $next || '$' === $next || '\\' === $next || ord( $next ) > 127;
				if ( ( $wp && $wn ) || false !== strpos( '+-/.', $prev ) || false !== strpos( '+-/.', $next ) ) { $out .= ' '; }
				continue;
			}
			if ( '/' === $c ) {
				$regex = '' === $last || false !== strpos( '(,=:[!&|?{};+-*%<>~^', $last ) || in_array( $word, self::$kw, true );
				if ( $regex ) { $e = self::js_re( $s, $i, $n ); if ( $e < 0 ) { return ''; } $out .= substr( $s, $i, $e - $i ); $i = $e; $last = ')'; $word = ''; continue; }
			}
			$out .= $c; $i++; $last = $c;
			if ( ctype_alnum( $c ) || '_' === $c || '$' === $c ) { $word .= $c; } else { $word = ''; }
		}
		return trim( $out );
	}

	private static function js_str( $s, $i, $n ) {
		$q = $s[ $i ]; $i++;
		while ( $i < $n ) { if ( '\\' === $s[ $i ] ) { $i += 2; continue; } if ( $s[ $i ] === $q ) { return $i + 1; } if ( "\n" === $s[ $i ] ) { return $i; } $i++; }
		return $n;
	}

	/** End of a template literal, following nested ${ ... } expressions. -1 when it never closes. */
	private static function js_tpl( $s, $i, $n ) {
		$i++;
		while ( $i < $n ) {
			$c = $s[ $i ];
			if ( '\\' === $c ) { $i += 2; continue; }
			if ( '`' === $c ) { return $i + 1; }
			if ( '$' === $c && $i + 1 < $n && '{' === $s[ $i + 1 ] ) {
				$i += 2; $d = 1; $lx = '(';
				while ( $i < $n && $d > 0 ) {
					$x = $s[ $i ];
					if ( ctype_space( $x ) ) { $i++; continue; }
					if ( '/' === $x && $i + 1 < $n && '/' !== $s[ $i + 1 ] && '*' !== $s[ $i + 1 ] && false !== strpos( '(,=:[!&|?{};+-*%<>~^', $lx ) ) { $e = self::js_re( $s, $i, $n ); if ( $e < 0 ) { return -1; } $i = $e; $lx = ')'; continue; }
					if ( '/' === $x && $i + 1 < $n && '*' === $s[ $i + 1 ] ) { $e = strpos( $s, '*/', $i + 2 ); if ( false === $e ) { return -1; } $i = $e + 2; continue; }
					$lx = $x;
					if ( '"' === $x || "'" === $x ) { $i = self::js_str( $s, $i, $n ); continue; }
					if ( '`' === $x ) { $e = self::js_tpl( $s, $i, $n ); if ( $e < 0 ) { return -1; } $i = $e; continue; }
					if ( '{' === $x ) { $d++; } elseif ( '}' === $x ) { $d--; }
					$i++;
				}
				continue;
			}
			$i++;
		}
		return -1;
	}

	/** End of a regular-expression literal including its flags. -1 when it does not look like one. */
	private static function js_re( $s, $i, $n ) {
		$i++; $cls = false;
		while ( $i < $n ) {
			$c = $s[ $i ];
			if ( "\n" === $c ) { return -1; }
			if ( '\\' === $c ) { $i += 2; continue; }
			if ( '[' === $c ) { $cls = true; } elseif ( ']' === $c ) { $cls = false; } elseif ( '/' === $c && ! $cls ) { $i++; while ( $i < $n && ctype_alpha( $s[ $i ] ) ) { $i++; } return $i; }
			$i++;
		}
		return -1;
	}

	/* ---------------------------------------------------------------- HTML */

	/** Remove comments and runs of spaces from a page. Scripts, styles, pre and textarea are left alone (inline CSS is shrunk). */
	public static function html( $html ) {
		$parts = preg_split( '#(<(pre|textarea|script|style)\b[^>]*>.*?</\2\s*>)#is', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( ! is_array( $parts ) ) { return $html; }
		$out = '';
		for ( $k = 0, $c = count( $parts ); $k < $c; $k += 3 ) {
			$out .= self::html_text( $parts[ $k ] );
			if ( isset( $parts[ $k + 1 ] ) ) {
				$blk = $parts[ $k + 1 ];
				if ( 0 === stripos( $blk, '<style' ) && preg_match( '#^(<style\b[^>]*>)(.*)(</style\s*>)$#is', $blk, $m ) && false === stripos( $m[1], 'type=' ) ) {
					$min = self::css( $m[2] ); $blk = '' !== $min || '' === trim( $m[2] ) ? $m[1] . $min . $m[3] : $blk;
				}
				$out .= $blk;
			}
		}
		return $out;
	}

	private static function html_text( $t ) {
		$t = preg_replace( '/<!--(?!\[if|<!\[endif|\s*\[if).*?-->/s', '', $t );
		$bits = preg_split( '/(<[^>]*>)/', $t, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( ! is_array( $bits ) ) { return $t; }
		foreach ( $bits as $k => $b ) {
			if ( 0 === $k % 2 ) { $bits[ $k ] = preg_replace( '/\s+/', ' ', $b ); }
		}
		return implode( '', $bits );
	}

	/* ------------------------------------------------- serving local assets */

	public static function init() {
		if ( is_admin() ) { return; }
		$o = Sidcraft_RankBoost_Speed::all();
		if ( $o['min_css'] ) { add_filter( 'style_loader_src', array( __CLASS__, 'asset' ), 9999, 2 ); }
		if ( $o['min_js'] ) { add_filter( 'script_loader_src', array( __CLASS__, 'asset' ), 9999, 2 ); }
	}

	public static function asset( $src, $handle ) {
		if ( ! is_string( $src ) || '' === $src || is_admin() ) { return $src; }
		$o = Sidcraft_RankBoost_Speed::all();
		$u = strtok( $src, '?' );
		$ext = strtolower( pathinfo( (string) wp_parse_url( $u, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
		if ( 'css' !== $ext && 'js' !== $ext ) { return $src; }
		if ( preg_match( '/\.min\.(css|js)$/i', $u ) ) { return $src; }
		if ( 'js' === $ext && ! $o['min_js'] ) { return $src; }
		if ( 'css' === $ext && ! $o['min_css'] ) { return $src; }
		foreach ( Sidcraft_RankBoost_Speed::lines( $o['min_exclude'] ) as $x ) { if ( $x === $handle || false !== strpos( $u, $x ) ) { return $src; } }
		$abs = self::local_path( $u );
		if ( '' === $abs || ! is_file( $abs ) ) { return $src; }
		$size = (int) filesize( $abs ); if ( $size < 200 || $size > 400000 ) { return $src; }
		$up = wp_get_upload_dir(); if ( ! empty( $up['error'] ) ) { return $src; }
		$name = substr( sha1( $abs ), 0, 12 ) . '-' . filemtime( $abs ) . '-' . $size . '.' . $ext;
		$dir  = trailingslashit( $up['basedir'] ) . 'sidcraft-search-boost-suite/min/';
		$url  = trailingslashit( $up['baseurl'] ) . 'sidcraft-search-boost-suite/min/' . $name;
		// Use the same scheme as the file being replaced (or the page's own), so an https site never gets an http:// script that browsers block as mixed content.
		$scheme = wp_parse_url( $src, PHP_URL_SCHEME );
		$url    = set_url_scheme( $url, in_array( $scheme, array( 'http', 'https' ), true ) ? $scheme : null );
		if ( is_file( $dir . $name ) ) { return $url; }
		$bad = get_option( 'sidcraft_rankboost_min_bad', array() ); if ( is_array( $bad ) && isset( $bad[ $name ] ) ) { return $src; }
		$code = file_get_contents( $abs ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading the site's own local file.
		$min  = false === $code ? '' : ( 'css' === $ext ? self::css( $code, trailingslashit( dirname( $u ) ) ) : self::js( $code ) );
		if ( '' === $min || strlen( $min ) >= strlen( $code ) || ! wp_mkdir_p( $dir ) || false === self::write( $dir . $name, $min ) ) {
			$bad = is_array( $bad ) ? $bad : array(); $bad[ $name ] = 1; update_option( 'sidcraft_rankboost_min_bad', array_slice( $bad, -200, null, true ), false );
			return $src;
		}
		return $url;
	}

	/** Absolute path of a file on this site from its URL, or '' when it is not one of ours. */
	private static function local_path( $u ) {
		$map = array( content_url() => WP_CONTENT_DIR, includes_url() => ABSPATH . WPINC . '/' );
		foreach ( $map as $url => $dir ) {
			$url = trailingslashit( set_url_scheme( $url, 'https' ) ); $uu = set_url_scheme( $u, 'https' );
			if ( 0 === strpos( $uu, $url ) ) {
				$rel = rawurldecode( substr( $uu, strlen( $url ) ) );
				if ( false !== strpos( $rel, '..' ) ) { return ''; }
				return trailingslashit( $dir ) . $rel;
			}
		}
		if ( 0 === strpos( $u, '/' ) && 0 !== strpos( $u, '//' ) ) { return ''; }
		return '';
	}

	public static function write( $file, $data ) {
		return file_put_contents( $file, $data, LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- a generated copy inside the uploads folder; written during a page request where no file-system credentials exist.
	}

	public static function clear() {
		$up = wp_get_upload_dir(); if ( ! empty( $up['error'] ) ) { return 0; }
		delete_option( 'sidcraft_rankboost_min_bad' );
		return Sidcraft_RankBoost_Speed::rm_dir( trailingslashit( $up['basedir'] ) . 'sidcraft-search-boost-suite/min' );
	}
}
