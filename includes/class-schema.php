<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** JSON-LD structured data: Organization, WebSite, WebPage/Article, BreadcrumbList and FAQPage (found automatically). */
class Sidcraft_RankBoost_Schema {

	public static function init() {
		add_action( 'wp_head', array( __CLASS__, 'output' ), 30 );
	}

	private static function logo() {
		$id = (int) get_theme_mod( 'custom_logo' );
		if ( $id ) {
			$src = wp_get_attachment_image_src( $id, 'full' );
			if ( $src ) { return array( '@type' => 'ImageObject', 'url' => $src[0], 'width' => (int) $src[1], 'height' => (int) $src[2] ); }
		}
		$icon = get_site_icon_url( 512 );
		return $icon ? array( '@type' => 'ImageObject', 'url' => $icon, 'width' => 512, 'height' => 512 ) : null;
	}

	public static function graph() {
		if ( ! Sidcraft_RankBoost_Settings::on( 'schema' ) ) { return array(); }
		$home = home_url( '/' );
		$org  = array( '@type' => 'Organization', '@id' => $home . '#organization', 'name' => Sidcraft_RankBoost_Meta::site_name(), 'url' => $home );
		$logo = self::logo();
		if ( $logo ) { $org['logo'] = $logo; }
		$graph = array(
			$org,
			array(
				'@type'           => 'WebSite',
				'@id'             => $home . '#website',
				'url'             => $home,
				'name'            => Sidcraft_RankBoost_Meta::site_name(),
				'description'     => wp_strip_all_tags( get_bloginfo( 'description' ) ),
				'publisher'       => array( '@id' => $home . '#organization' ),
				'inLanguage'      => get_bloginfo( 'language' ),
				'potentialAction' => array( '@type' => 'SearchAction', 'target' => array( '@type' => 'EntryPoint', 'urlTemplate' => $home . '?s={search_term_string}' ), 'query-input' => 'required name=search_term_string' ),
			),
		);
		$c = Sidcraft_RankBoost_Meta::current();
		if ( is_singular() && $c['post'] ) {
			$post = $c['post'];
			$url  = $c['url'];
			$page = array(
				'@type'         => 'WebPage',
				'@id'           => $url . '#webpage',
				'url'           => $url,
				'name'          => wp_strip_all_tags( $c['title'] ),
				'description'   => $c['desc'],
				'isPartOf'      => array( '@id' => $home . '#website' ),
				'datePublished' => get_the_date( 'c', $post ),
				'dateModified'  => get_the_modified_date( 'c', $post ),
				'inLanguage'    => get_bloginfo( 'language' ),
			);
			if ( ! empty( $c['image']['url'] ) ) {
				$page['primaryImageOfPage'] = array( '@type' => 'ImageObject', 'url' => $c['image']['url'] );
			}
			if ( ! is_front_page() ) { $page['breadcrumb'] = array( '@id' => $url . '#breadcrumb' ); }
			$graph[] = $page;

			if ( is_singular( 'post' ) ) {
				$author = get_userdata( $post->post_author );
				$art = array(
					'@type'            => 'BlogPosting',
					'@id'              => $url . '#article',
					'isPartOf'         => array( '@id' => $url . '#webpage' ),
					'mainEntityOfPage' => array( '@id' => $url . '#webpage' ),
					'headline'         => Sidcraft_RankBoost_Util::truncate( get_the_title( $post ), 110 ),
					'datePublished'    => get_the_date( 'c', $post ),
					'dateModified'     => get_the_modified_date( 'c', $post ),
					'wordCount'        => Sidcraft_RankBoost_Util::word_count( Sidcraft_RankBoost_Util::post_text( $post ) ),
					'publisher'        => array( '@id' => $home . '#organization' ),
					'inLanguage'       => get_bloginfo( 'language' ),
				);
				if ( $author ) { $art['author'] = array( '@type' => 'Person', 'name' => $author->display_name, 'url' => get_author_posts_url( $author->ID ) ); }
				if ( ! empty( $c['image']['url'] ) ) { $art['image'] = array( $c['image']['url'] ); }
				$cats = get_the_category( $post->ID );
				if ( $cats ) { $art['articleSection'] = wp_list_pluck( $cats, 'name' ); }
				$graph[] = $art;
			}

			if ( ! is_front_page() ) { $graph[] = self::breadcrumbs( $post, $url ); }
			$faq = self::faq( $post );
			if ( $faq ) { $graph[] = $faq; }
		} elseif ( is_front_page() || is_home() ) {
			$graph[] = array( '@type' => 'WebPage', '@id' => $c['url'] . '#webpage', 'url' => $c['url'], 'name' => wp_strip_all_tags( $c['title'] ), 'isPartOf' => array( '@id' => $home . '#website' ) );
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$graph[] = array( '@type' => 'CollectionPage', '@id' => $c['url'] . '#webpage', 'url' => $c['url'], 'name' => wp_strip_all_tags( $c['title'] ), 'isPartOf' => array( '@id' => $home . '#website' ) );
		}
		return apply_filters( 'sidcraft_rankboost_schema_graph', $graph, $c );
	}

	private static function breadcrumbs( $post, $url ) {
		$items = array( array( 'name' => __( 'Home', 'sidcraft-rankboost' ), 'url' => home_url( '/' ) ) );
		$anc   = array_reverse( get_post_ancestors( $post ) );
		foreach ( $anc as $id ) { $items[] = array( 'name' => get_the_title( $id ), 'url' => get_permalink( $id ) ); }
		if ( 'post' === $post->post_type ) {
			$cats = get_the_category( $post->ID );
			if ( $cats ) { $items[] = array( 'name' => $cats[0]->name, 'url' => get_category_link( $cats[0] ) ); }
		}
		$items[] = array( 'name' => get_the_title( $post ), 'url' => $url );
		$list = array();
		foreach ( $items as $i => $it ) {
			$list[] = array( '@type' => 'ListItem', 'position' => $i + 1, 'name' => wp_strip_all_tags( $it['name'] ), 'item' => $it['url'] );
		}
		return array( '@type' => 'BreadcrumbList', '@id' => $url . '#breadcrumb', 'itemListElement' => $list );
	}

	/** FAQPage when at least two headings are questions that are followed by an answer. */
	private static function faq( $post ) {
		$content = $post->post_content;
		if ( ! preg_match_all( '#<h([2-6])[^>]*>([^<]{8,200}\?)\s*</h\1>\s*(<p[^>]*>.*?</p>)#is', $content, $m, PREG_SET_ORDER ) || count( $m ) < 2 ) { return null; }
		$qa = array();
		foreach ( $m as $row ) {
			$q = Sidcraft_RankBoost_Util::clean( wp_strip_all_tags( $row[2] ) );
			$a = Sidcraft_RankBoost_Util::clean( wp_strip_all_tags( $row[3] ) );
			if ( Sidcraft_RankBoost_Util::len( $a ) < 20 ) { continue; }
			$qa[] = array( '@type' => 'Question', 'name' => $q, 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => Sidcraft_RankBoost_Util::truncate( $a, 600 ) ) );
		}
		return count( $qa ) >= 2 ? array( '@type' => 'FAQPage', 'mainEntity' => $qa ) : null;
	}

	public static function output() {
		if ( is_feed() || is_embed() || is_404() || is_search() ) { return; }
		$graph = self::graph();
		if ( ! $graph ) { return; }
		$json = wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $graph ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP );
		if ( false === $json ) { return; }
		wp_print_inline_script_tag( $json, array( 'type' => 'application/ld+json', 'class' => 'sidcraft-rankboost-schema' ) );
	}
}
