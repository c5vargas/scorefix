<?php
/**
 * On-page SEO heuristics on stored HTML (no head/meta): thin body text, internal link ratio.
 *
 * @package ScoreFix
 */

namespace ScoreFix\Scanner\Rules;

defined( 'ABSPATH' ) || exit;

/**
 * Class SeoFragmentRule
 */
class SeoFragmentRule {

	/**
	 * @param \DOMXPath $xpath   Bound document (wrapped in #scorefix-root).
	 * @param int       $post_id Post ID (0 skips — e.g. rendered URL body only).
	 * @param callable  $issue   Issue factory.
	 * @return array<int, array<string, mixed>>
	 */
	public static function collect( \DOMXPath $xpath, $post_id, callable $issue ) {
		$out = array();
		$pid = (int) $post_id;
		if ( $pid <= 0 ) {
			return $out;
		}

		$post = get_post( $pid );
		if ( ! $post instanceof \WP_Post || 'publish' !== $post->post_status ) {
			return $out;
		}

		$root = $xpath->query( "//*[@id='scorefix-root']" )->item( 0 );
		if ( ! $root instanceof \DOMElement ) {
			return $out;
		}

		$word_count = self::count_words_utf8( (string) $root->textContent );

		if ( apply_filters( 'scorefix_seo_thin_content_enabled', true, $post, $word_count ) ) {
			$thin_types = apply_filters( 'scorefix_seo_thin_content_post_types', array( 'post', 'page' ) );
			if ( is_array( $thin_types ) ) {
				$thin_types = array_map( 'sanitize_key', $thin_types );
				if ( in_array( $post->post_type, $thin_types, true ) ) {
					$max_words = (int) apply_filters( 'scorefix_seo_thin_content_max_words', 150 );
					if ( $max_words > 0 && $word_count < $max_words ) {
						$out[] = $issue(
							'seo_thin_content',
							'low',
							array(
								'post_id'    => $pid,
								'context'    => 'content',
								'impact'     => 'business',
								'word_count' => $word_count,
							)
						);
					}
				}
			}
		}

		if ( ! apply_filters( 'scorefix_seo_internal_links_enabled', true, $post, $word_count ) ) {
			return $out;
		}

		$ilink_types = apply_filters( 'scorefix_seo_internal_links_post_types', array( 'post', 'page' ) );
		if ( ! is_array( $ilink_types ) ) {
			return $out;
		}
		$ilink_types = array_map( 'sanitize_key', $ilink_types );
		if ( ! in_array( $post->post_type, $ilink_types, true ) ) {
			return $out;
		}

		$min_words = (int) apply_filters( 'scorefix_seo_internal_links_min_words', 280 );
		if ( $word_count < max( 1, $min_words ) ) {
			return $out;
		}

		$site_host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$site_host = is_string( $site_host ) ? strtolower( $site_host ) : '';

		$internal = 0;
		$external = 0;
		$links    = $xpath->query( "//*[@id='scorefix-root']//a[@href]" );
		if ( $links ) {
			foreach ( $links as $a ) {
				if ( ! $a instanceof \DOMElement ) {
					continue;
				}
				$class = self::classify_href( (string) $a->getAttribute( 'href' ), $site_host );
				if ( 'internal' === $class ) {
					++$internal;
				} elseif ( 'external' === $class ) {
					++$external;
				}
			}
		}

		$min_external = (int) apply_filters( 'scorefix_seo_internal_links_min_external', 2 );
		$min_external = max( 1, $min_external );

		if ( $internal > 0 || $external < $min_external ) {
			return $out;
		}

		$out[] = $issue(
			'seo_few_internal_links',
			'low',
			array(
				'post_id'              => $pid,
				'context'              => 'content',
				'impact'               => 'business',
				'word_count'           => $word_count,
				'internal_link_count'  => $internal,
				'external_link_count'  => $external,
			)
		);

		return $out;
	}

	/**
	 * Word count for i18n body text (whitespace-separated tokens).
	 *
	 * @param string $text Raw text.
	 * @return int
	 */
	protected static function count_words_utf8( $text ) {
		$text = preg_replace( '/\s+/u', ' ', trim( html_entity_decode( (string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
		if ( '' === $text ) {
			return 0;
		}
		$parts = preg_split( '/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY );
		return is_array( $parts ) ? count( $parts ) : 0;
	}

	/**
	 * @param string $href      Raw href.
	 * @param string $site_host Lowercase host from home_url (no port normalization).
	 * @return string internal|external|skip
	 */
	protected static function classify_href( $href, $site_host ) {
		$href = trim( (string) $href );
		if ( '' === $href ) {
			return 'skip';
		}

		if ( preg_match( '/^(mailto|tel|javascript|data):/i', $href ) ) {
			return 'skip';
		}

		$parts = wp_parse_url( $href );
		if ( ! is_array( $parts ) ) {
			return 'skip';
		}

		if ( empty( $parts['host'] ) ) {
			return 'internal';
		}

		$h = strtolower( (string) $parts['host'] );
		if ( '' !== $site_host && $h === $site_host ) {
			return 'internal';
		}

		return 'external';
	}
}
