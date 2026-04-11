<?php
/**
 * Scan inline styles for possible contrast problems.
 *
 * @package ScoreFix
 */

namespace ScoreFix\Scanner\Rules;

use ScoreFix\Scanner\ContrastStyleAnalyzer;

defined( 'ABSPATH' ) || exit;

/**
 * Class ContrastInlineRule
 */
class ContrastInlineRule {

	/**
	 * Collect contrast_risk issues from @style attributes.
	 *
	 * @param \DOMXPath $xpath   Bound document.
	 * @param int       $post_id Post ID.
	 * @param callable  $issue   Issue factory.
	 * @return array<int, array<string, mixed>>
	 */
	public static function collect( \DOMXPath $xpath, $post_id, callable $issue ) {
		$out = array();
		foreach ( $xpath->query( '//*[@style]' ) as $el ) {
			if ( ! $el instanceof \DOMElement ) {
				continue;
			}
			$raw_style = (string) $el->getAttribute( 'style' );
			$contrast  = ContrastStyleAnalyzer::analyze( $raw_style );
			if ( null === $contrast ) {
				continue;
			}
			$extra = array(
				'post_id'       => (int) $post_id,
				'impact'        => 'readability',
				'hint'          => isset( $contrast['hint'] ) ? (string) $contrast['hint'] : 'unknown',
				'style_snippet' => substr( preg_replace( '/\s+/', ' ', $raw_style ), 0, 120 ),
			);
			if ( isset( $contrast['ratio'] ) ) {
				$extra['ratio'] = $contrast['ratio'];
			}
			if ( isset( $contrast['detail'] ) ) {
				$extra['detail'] = (string) $contrast['detail'];
			}
			$out[] = $issue( 'contrast_risk', 'medium', $extra );
		}
		return $out;
	}
}
