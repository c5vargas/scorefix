<?php
/**
 * Scan button elements for missing accessible names.
 *
 * @package ScoreFix
 */

namespace ScoreFix\Scanner\Rules;

use ScoreFix\Scanner\HtmlScanHelpers;

defined( 'ABSPATH' ) || exit;

/**
 * Class ButtonsRule
 */
class ButtonsRule {

	/**
	 * Collect button_no_text issues.
	 *
	 * @param \DOMXPath $xpath   Bound document.
	 * @param int       $post_id Post ID.
	 * @param callable  $issue   Issue factory.
	 * @return array<int, array<string, mixed>>
	 */
	public static function collect( \DOMXPath $xpath, $post_id, callable $issue ) {
		$out = array();
		foreach ( $xpath->query( '//button' ) as $btn ) {
			if ( ! $btn instanceof \DOMElement ) {
				continue;
			}
			if ( ! HtmlScanHelpers::element_has_accessible_name( $btn ) ) {
				$out[] = $issue(
					'button_no_text',
					'high',
					array(
						'post_id' => (int) $post_id,
						'impact'  => 'conversion',
					)
				);
			}
		}
		return $out;
	}
}
