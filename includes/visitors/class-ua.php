<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Small user-agent reader: robot or person, browser, system and device type. No library and no lookups. */
class Sidcraft_VS_UA {

	public static function is_bot( $ua ) {
		if ( '' === $ua ) { return true; }
		return (bool) preg_match( '/bot|crawl|spider|slurp|bingpreview|facebookexternalhit|facebot|headless|lighthouse|pagespeed|gtmetrix|pingdom|uptime|monitor|curl\/|wget|python|go-http|java\/|httpclient|okhttp|scrapy|libwww|ahrefs|semrush|mj12|dotbot|petalbot|bytespider|gptbot|claude|ccbot|perplexity|amazonbot|feedfetcher|feedly|jetpack|wordpress|validator|preview|archive\.org|embedly|phantom|selenium|playwright|puppeteer/i', $ua );
	}

	public static function parse( $ua ) {
		$b = 'Other';
		if ( preg_match( '/Edg(e|A|iOS)?\//', $ua ) ) { $b = 'Edge'; }
		elseif ( preg_match( '/OPR\/|Opera/', $ua ) ) { $b = 'Opera'; }
		elseif ( false !== strpos( $ua, 'SamsungBrowser' ) ) { $b = 'Samsung'; }
		elseif ( preg_match( '/Firefox\/|FxiOS/', $ua ) ) { $b = 'Firefox'; }
		elseif ( preg_match( '/Chrome\/|CriOS/', $ua ) ) { $b = 'Chrome'; }
		elseif ( preg_match( '/Safari\//', $ua ) ) { $b = 'Safari'; }
		elseif ( preg_match( '/MSIE|Trident/', $ua ) ) { $b = 'IE'; }

		$o = 'Other';
		if ( preg_match( '/Windows/', $ua ) ) { $o = 'Windows'; }
		elseif ( preg_match( '/Android/', $ua ) ) { $o = 'Android'; }
		elseif ( preg_match( '/iPhone|iPad|iPod/', $ua ) ) { $o = 'iOS'; }
		elseif ( preg_match( '/Mac OS X|Macintosh/', $ua ) ) { $o = 'macOS'; }
		elseif ( preg_match( '/CrOS/', $ua ) ) { $o = 'ChromeOS'; }
		elseif ( preg_match( '/Linux|X11/', $ua ) ) { $o = 'Linux'; }

		$d = 'desktop';
		if ( preg_match( '/iPad|Tablet|PlayBook|Silk/', $ua ) || ( false !== strpos( $ua, 'Android' ) && false === strpos( $ua, 'Mobile' ) ) ) { $d = 'tablet'; }
		elseif ( preg_match( '/Mobi|iPhone|iPod|Android/', $ua ) ) { $d = 'mobile'; }
		return array( $b, $o, $d );
	}

	/** Search, social or a plain website, from the host that sent the visitor. */
	public static function source_of( $host ) {
		if ( '' === $host ) { return 'direct'; }
		if ( preg_match( '/(^|\.)(google|bing|duckduckgo|yahoo|baidu|yandex|ecosia|brave|startpage|ask|naver|qwant|search\.)[a-z.]*$/', $host ) || preg_match( '/^(www\.)?(google|bing|yahoo|duckduckgo|baidu|yandex|ecosia|ask|naver|qwant)\./', $host ) ) { return 'search'; }
		if ( preg_match( '/(facebook|fb\.com|instagram|twitter|t\.co|x\.com|linkedin|lnkd\.in|youtube|youtu\.be|pinterest|reddit|whatsapp|tiktok|snapchat|telegram|threads\.net|tumblr|quora)/', $host ) ) { return 'social'; }
		return 'referral';
	}
}
