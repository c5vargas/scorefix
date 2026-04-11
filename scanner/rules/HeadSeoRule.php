<?php
/**
 * SEO checks on captured full-document HTML: title, meta description, canonical, viewport (rendered URL pass).
 *
 * @package ScoreFix
 */

namespace ScoreFix\Scanner\Rules;

defined( 'ABSPATH' ) || exit;

/**
 * Class HeadSeoRule
 */
class HeadSeoRule {

	/**
	 * Run head-level checks on HTML returned by URL capture (expects a full or partial document).
	 *
	 * @param string   $document_html Raw HTML.
	 * @param callable $issue         Issue factory (same as render queue: sets source rendered_url, capture_url).
	 * @param string   $capture_url   URL being audited (for filters / optional heuristics).
	 * @return array<int, array<string, mixed>>
	 */
	public static function collect( $document_html, callable $issue, $capture_url = '' ) {
		$out = array();
		$capture_url = (string) $capture_url;

		if ( ! apply_filters( 'scorefix_head_seo_enabled', true, $capture_url ) ) {
			return $out;
		}

		$html = (string) $document_html;
		if ( '' === trim( $html ) ) {
			return $out;
		}

		$dom    = self::parse_html_document( $html );
		$loaded = null !== $dom;
		if ( ! $loaded ) {
			return $out;
		}

		$xpath = new \DOMXPath( $dom );

		$title_text = '';
		// Prefer document <head> title; //title can match SVG <title> in body.
		$titles     = $xpath->query( '//head/title' );
		if ( ! $titles || $titles->length < 1 ) {
			$titles = $xpath->query( '//title' );
		}
		if ( $titles && $titles->length > 0 ) {
			$first = $titles->item( 0 );
			if ( $first instanceof \DOMElement ) {
				$title_text = trim( html_entity_decode( (string) $first->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
			}
		}

		if ( '' === $title_text ) {
			$out[] = $issue(
				'seo_head_title_missing',
				'medium',
				array(
					'context'      => 'rendered',
					'impact'       => 'business',
					'capture_hint' => 'document-title',
				)
			);
		} else {
			$min_len = (int) apply_filters( 'scorefix_head_seo_title_min_chars', 15, $capture_url );
			$max_len = (int) apply_filters( 'scorefix_head_seo_title_max_chars', 70, $capture_url );
			$min_len = max( 1, $min_len );
			$max_len = max( $min_len + 1, $max_len );

			$len = self::mb_len( $title_text );
			if ( $len < $min_len || $len > $max_len ) {
				$out[] = $issue(
					'seo_head_title_length',
					'low',
					array(
						'context'       => 'rendered',
						'impact'        => 'business',
						'title_length'  => $len,
						'title_min'     => $min_len,
						'title_max'     => $max_len,
						'capture_hint'  => 'document-title',
					)
				);
			}
		}

		if ( apply_filters( 'scorefix_head_seo_require_meta_description', true, $capture_url ) ) {
			if ( ! self::has_meta_description( $dom ) ) {
				$out[] = $issue(
					'seo_head_meta_description_missing',
					'low',
					array(
						'context'      => 'rendered',
						'impact'       => 'business',
						'capture_hint' => 'meta-description',
					)
				);
			}
		}

		if ( apply_filters( 'scorefix_head_seo_require_canonical', true, $capture_url ) ) {
			if ( ! self::has_canonical_link( $xpath ) ) {
				$out[] = $issue(
					'seo_head_canonical_missing',
					'low',
					array(
						'context'      => 'rendered',
						'impact'       => 'business',
						'capture_hint' => 'canonical',
					)
				);
			}
		}

		if ( apply_filters( 'scorefix_head_seo_require_viewport', true, $capture_url ) ) {
			if ( ! self::has_viewport_meta( $dom ) ) {
				$out[] = $issue(
					'seo_head_viewport_missing',
					'medium',
					array(
						'context'      => 'rendered',
						'impact'       => 'business',
						'capture_hint' => 'viewport',
					)
				);
			}
		}

		if ( apply_filters( 'scorefix_head_seo_report_noindex_in_html', false, $capture_url ) ) {
			$robots = self::get_meta_robots_content( $dom );
			if ( is_string( $robots ) && '' !== $robots && preg_match( '/\bnoindex\b/i', $robots ) ) {
				$out[] = $issue(
					'seo_head_robots_noindex',
					'low',
					array(
						'context'        => 'rendered',
						'impact'         => 'business',
						'robots_content' => $robots,
						'capture_hint'   => 'robots',
					)
				);
			}
		}

		return $out;
	}

	/**
	 * Parse full HTML documents for head checks. Avoid LIBXML_HTML_NOIMPLIED — it can break tree on large WP/Elementor output.
	 *
	 * @param string $html Raw HTML.
	 * @return \DOMDocument|null
	 */
	protected static function parse_html_document( $html ) {
		libxml_use_internal_errors( true );
		$dom = new \DOMDocument();
		$ok  = $dom->loadHTML( '<?xml encoding="utf-8"?>' . $html );
		libxml_clear_errors();
		if ( ! $ok ) {
			return null;
		}
		return $dom;
	}

	/**
	 * @param string $s UTF-8 string.
	 * @return int
	 */
	protected static function mb_len( $s ) {
		if ( function_exists( 'mb_strlen' ) ) {
			return (int) mb_strlen( (string) $s, 'UTF-8' );
		}
		return strlen( (string) $s );
	}

	/**
	 * @param \DOMDocument $dom Document.
	 * @return bool
	 */
	protected static function has_meta_description( \DOMDocument $dom ) {
		$metas = $dom->getElementsByTagName( 'meta' );
		if ( ! $metas ) {
			return false;
		}
		for ( $i = 0; $i < $metas->length; $i++ ) {
			$m = $metas->item( $i );
			if ( ! $m instanceof \DOMElement ) {
				continue;
			}
			$content = trim( (string) $m->getAttribute( 'content' ) );
			if ( '' === $content ) {
				continue;
			}
			$name = strtolower( (string) $m->getAttribute( 'name' ) );
			$prop = strtolower( (string) $m->getAttribute( 'property' ) );
			if ( 'description' === $name || 'og:description' === $prop ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param \DOMXPath $xpath XPath.
	 * @return bool
	 */
	protected static function has_canonical_link( \DOMXPath $xpath ) {
		$links = $xpath->query( '//link[@href]' );
		if ( ! $links ) {
			return false;
		}
		foreach ( $links as $link ) {
			if ( ! $link instanceof \DOMElement ) {
				continue;
			}
			$rel = strtolower( trim( (string) $link->getAttribute( 'rel' ) ) );
			if ( '' === $rel ) {
				continue;
			}
			$rels = preg_split( '/\s+/', $rel, -1, PREG_SPLIT_NO_EMPTY );
			if ( ! is_array( $rels ) ) {
				continue;
			}
			if ( in_array( 'canonical', $rels, true ) && '' !== trim( (string) $link->getAttribute( 'href' ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param \DOMDocument $dom Document.
	 * @return bool
	 */
	protected static function has_viewport_meta( \DOMDocument $dom ) {
		$metas = $dom->getElementsByTagName( 'meta' );
		if ( ! $metas ) {
			return false;
		}
		for ( $i = 0; $i < $metas->length; $i++ ) {
			$m = $metas->item( $i );
			if ( ! $m instanceof \DOMElement ) {
				continue;
			}
			if ( 'viewport' !== strtolower( trim( (string) $m->getAttribute( 'name' ) ) ) ) {
				continue;
			}
			if ( '' !== trim( (string) $m->getAttribute( 'content' ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param \DOMDocument $dom Document.
	 * @return string|null
	 */
	protected static function get_meta_robots_content( \DOMDocument $dom ) {
		$metas = $dom->getElementsByTagName( 'meta' );
		if ( ! $metas ) {
			return null;
		}
		for ( $i = 0; $i < $metas->length; $i++ ) {
			$m = $metas->item( $i );
			if ( ! $m instanceof \DOMElement ) {
				continue;
			}
			if ( 'robots' !== strtolower( trim( (string) $m->getAttribute( 'name' ) ) ) ) {
				continue;
			}
			$c = trim( (string) $m->getAttribute( 'content' ) );
			return $c;
		}
		return null;
	}
}
