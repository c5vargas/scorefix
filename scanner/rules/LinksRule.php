<?php
/**
 * Scan anchor elements for missing accessible names.
 *
 * @package ScoreFix
 */

namespace ScoreFix\Scanner\Rules;

use ScoreFix\Scanner\HtmlScanHelpers;

defined( 'ABSPATH' ) || exit;

/**
 * Class LinksRule
 */
class LinksRule {

	/**
	 * Collect link_no_text issues.
	 *
	 * @param \DOMXPath $xpath   Bound document.
	 * @param int       $post_id Post ID.
	 * @param callable  $issue   Issue factory.
	 * @return array<int, array<string, mixed>>
	 */
	public static function collect( \DOMXPath $xpath, $post_id, callable $issue ) {
		$out = array();
		foreach ( $xpath->query( '//a[@href]' ) as $a ) {
			if ( ! $a instanceof \DOMElement ) {
				continue;
			}
			if ( ! HtmlScanHelpers::element_has_accessible_name( $a ) ) {
				$out[] = $issue(
					'link_no_text',
					'high',
					array(
						'post_id' => (int) $post_id,
						'href'    => substr( (string) $a->getAttribute( 'href' ), 0, 120 ),
						'impact'  => 'conversion',
					)
				);
			}
		}
		return $out;
	}
}
