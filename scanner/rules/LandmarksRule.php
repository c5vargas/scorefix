<?php
/**
 * Landmark heuristics inside scanned HTML fragment.
 *
 * @package ScoreFix
 */

namespace ScoreFix\Scanner\Rules;

use ScoreFix\Scanner\HtmlScanHelpers;

defined( 'ABSPATH' ) || exit;

/**
 * Class LandmarksRule
 */
class LandmarksRule {

	const ROOT = "//*[@id='scorefix-root']";

	/**
	 * @param \DOMXPath $xpath   Document.
	 * @param int       $post_id Post ID.
	 * @param callable  $issue   Issue factory.
	 * @return array<int, array<string, mixed>>
	 */
	public static function collect( \DOMXPath $xpath, $post_id, callable $issue ) {
		$out = array();

		$main_nodes = $xpath->query(
			self::ROOT . '//main | ' . self::ROOT . '//*[translate(@role,"MAIN","main")="main"]'
		);
		$mains = array();
		if ( $main_nodes ) {
			foreach ( $main_nodes as $n ) {
				if ( $n instanceof \DOMElement ) {
					$mains[ spl_object_id( $n ) ] = $n;
				}
			}
		}
		if ( count( $mains ) > 1 ) {
			$out[] = $issue(
				'landmark_multiple_main',
				'medium',
				array(
					'post_id'    => (int) $post_id,
					'context'    => 'content',
					'impact'     => 'readability',
					'main_count' => count( $mains ),
				)
			);
		}

		if ( apply_filters( 'scorefix_scan_landmark_no_main_in_content_fragment', false ) ) {
			$chrome = $xpath->query( self::ROOT . '//nav | ' . self::ROOT . '//header | ' . self::ROOT . '//footer' );
			$chrome_count = $chrome ? $chrome->length : 0;
			if ( 0 === count( $mains ) && $chrome_count > 0 ) {
				$out[] = $issue(
					'landmark_no_main',
					'low',
					array(
						'post_id' => (int) $post_id,
						'context' => 'content',
						'impact'  => 'readability',
					)
				);
			}
		}

		$navs = $xpath->query( self::ROOT . '//nav' );
		if ( ! $navs || $navs->length < 2 ) {
			return $out;
		}
		$unnamed = 0;
		foreach ( $navs as $nav ) {
			if ( ! $nav instanceof \DOMElement ) {
				continue;
			}
			if ( ! HtmlScanHelpers::nav_has_accessible_name( $nav ) ) {
				++$unnamed;
			}
		}
		if ( $unnamed > 0 ) {
			$out[] = $issue(
				'landmark_nav_unnamed',
				'low',
				array(
					'post_id'       => (int) $post_id,
					'context'       => 'content',
					'impact'        => 'readability',
					'nav_total'     => (int) $navs->length,
					'nav_unnamed'   => (int) $unnamed,
				)
			);
		}

		return $out;
	}
}
