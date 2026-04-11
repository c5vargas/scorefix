<?php
/**
 * Links with visible / aria names that are generic ("read more", "click here", …).
 *
 * @package ScoreFix
 */

namespace ScoreFix\Scanner\Rules;

use ScoreFix\Scanner\HtmlScanHelpers;

defined( 'ABSPATH' ) || exit;

/**
 * Class LinkGenericTextRule
 */
class LinkGenericTextRule {

	const ROOT = "//*[@id='scorefix-root']";

	/**
	 * Normalized phrases (lowercase) treated as non-descriptive link text.
	 *
	 * @return array<int, string>
	 */
	protected static function generic_phrases() {
		$phrases = array(
			'click here',
			'read more',
			'read more…',
			'learn more',
			'more',
			'more info',
			'here',
			'continue',
			'next',
			'previous',
			'prev',
			'leer más',
			'más información',
			'más info',
			'clic aquí',
			'haz clic',
			'haz clic aquí',
			'ver más',
			'siguiente',
			'anterior',
			'pincha aquí',
		);
		return apply_filters( 'scorefix_scan_link_generic_phrases', $phrases );
	}

	/**
	 * @param \DOMXPath $xpath   Document.
	 * @param int       $post_id Post ID.
	 * @param callable  $issue   Issue factory.
	 * @return array<int, array<string, mixed>>
	 */
	public static function collect( \DOMXPath $xpath, $post_id, callable $issue ) {
		$out   = array();
		$max   = (int) apply_filters( 'scorefix_scan_max_link_generic_issues', 25, $post_id );
		$max   = max( 1, min( 100, $max ) );
		$set   = array_fill_keys( self::generic_phrases(), true );
		$links = $xpath->query( self::ROOT . '//a[@href]' );
		if ( ! $links ) {
			return $out;
		}

		foreach ( $links as $a ) {
			if ( ! $a instanceof \DOMElement ) {
				continue;
			}
			if ( ! HtmlScanHelpers::element_has_accessible_name( $a ) ) {
				continue;
			}
			$name = HtmlScanHelpers::link_display_name_for_generic_check( $a );
			if ( null === $name ) {
				continue;
			}
			if ( '' === $name ) {
				continue;
			}
			if ( ! isset( $set[ $name ] ) ) {
				continue;
			}
			if ( count( $out ) >= $max ) {
				break;
			}
			$href = substr( (string) $a->getAttribute( 'href' ), 0, 160 );
			$out[] = $issue(
				'link_generic_text',
				'medium',
				array(
					'post_id'    => (int) $post_id,
					'context'    => 'content',
					'impact'     => 'conversion',
					'link_text'  => substr( $name, 0, 80 ),
					'href'       => $href,
				)
			);
		}

		return $out;
	}
}
