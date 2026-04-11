<?php
/**
 * Heading outline heuristics (fragment / post content).
 *
 * @package ScoreFix
 */

namespace ScoreFix\Scanner\Rules;

defined( 'ABSPATH' ) || exit;

/**
 * Class HeadingsRule
 */
class HeadingsRule {

	const ROOT_XPATH = "//*[@id='scorefix-root']";

	/**
	 * @param \DOMXPath $xpath   Document.
	 * @param int       $post_id Post ID.
	 * @param callable  $issue   Issue factory.
	 * @return array<int, array<string, mixed>>
	 */
	public static function collect( \DOMXPath $xpath, $post_id, callable $issue ) {
		$out = array();

		$h1_nodes = $xpath->query( self::ROOT_XPATH . '//h1' );
		$h1_count  = $h1_nodes ? $h1_nodes->length : 0;
		if ( $h1_count > 1 ) {
			$out[] = $issue(
				'heading_multiple_h1',
				'medium',
				array(
					'post_id'   => (int) $post_id,
					'context'   => 'content',
					'impact'    => 'readability',
					'h1_count'  => (int) $h1_count,
				)
			);
		}

		$heading_path = self::ROOT_XPATH . '//*[self::h1 or self::h2 or self::h3 or self::h4 or self::h5 or self::h6]';
		$nodes        = $xpath->query( $heading_path );
		if ( ! $nodes || $nodes->length < 2 ) {
			return $out;
		}

		$max_skips = (int) apply_filters( 'scorefix_scan_max_heading_level_skips', 20, $post_id );
		$max_skips = max( 1, min( 100, $max_skips ) );
		$skips     = 0;
		$prev      = null;

		foreach ( $nodes as $node ) {
			if ( ! $node instanceof \DOMElement ) {
				continue;
			}
			$tag = strtolower( $node->tagName );
			if ( ! preg_match( '/^h([1-6])$/', $tag, $m ) ) {
				continue;
			}
			$level = (int) $m[1];
			if ( null !== $prev && $level > $prev['level'] + 1 ) {
				$out[] = $issue(
					'heading_level_skip',
					'medium',
					array(
						'post_id'  => (int) $post_id,
						'context'  => 'content',
						'impact'   => 'readability',
						'from_tag' => 'h' . (int) $prev['level'],
						'to_tag'   => 'h' . $level,
					)
				);
				++$skips;
				if ( $skips >= $max_skips ) {
					break;
				}
			}
			$prev = array( 'level' => $level );
		}

		return $out;
	}
}
