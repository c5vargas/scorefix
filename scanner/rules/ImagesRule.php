<?php
/**
 * Scan images for missing ALT (content fragments).
 *
 * @package ScoreFix
 */

namespace ScoreFix\Scanner\Rules;

defined( 'ABSPATH' ) || exit;

/**
 * Class ImagesRule
 */
class ImagesRule {

	/**
	 * Collect image_no_alt issues.
	 *
	 * @param \DOMXPath $xpath   Bound document.
	 * @param int       $post_id Post ID.
	 * @param callable  $issue   function( string $type, string $severity, array $extra ): array
	 * @return array<int, array<string, mixed>>
	 */
	public static function collect( \DOMXPath $xpath, $post_id, callable $issue ) {
		$out = array();
		foreach ( $xpath->query( '//img' ) as $img ) {
			if ( ! $img instanceof \DOMElement ) {
				continue;
			}
			$role = strtolower( (string) $img->getAttribute( 'role' ) );
			if ( 'presentation' === $role || 'none' === $role ) {
				continue;
			}
			if ( ! $img->hasAttribute( 'alt' ) || trim( $img->getAttribute( 'alt' ) ) === '' ) {
				$out[] = $issue(
					'image_no_alt',
					'high',
					array(
						'post_id' => (int) $post_id,
						'context' => 'content',
						'src'     => substr( (string) $img->getAttribute( 'src' ), 0, 120 ),
						'impact'  => 'readability',
					)
				);
			}
		}
		return $out;
	}
}
