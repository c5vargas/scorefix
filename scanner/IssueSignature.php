<?php
/**
 * Stable fingerprint for scan issues (ignores per-run random id).
 *
 * @package ScoreFix
 */

namespace ScoreFix\Scanner;

defined( 'ABSPATH' ) || exit;

/**
 * Class IssueSignature
 */
class IssueSignature {

	/**
	 * Build a stable signature for diffing issues between scans.
	 *
	 * @param array<string, mixed> $issue Issue row from scanner.
	 * @return string
	 */
	public static function from_issue( array $issue ) {
		$type = isset( $issue['type'] ) ? (string) $issue['type'] : '';
		$post = isset( $issue['post_id'] ) ? (int) $issue['post_id'] : 0;
		$ctx  = isset( $issue['context'] ) ? (string) $issue['context'] : '';

		if ( 'contrast_risk' === $type ) {
			$hint    = isset( $issue['hint'] ) ? (string) $issue['hint'] : '';
			$snippet = isset( $issue['style_snippet'] ) ? (string) $issue['style_snippet'] : '';
			$detail  = isset( $issue['detail'] ) ? (string) $issue['detail'] : '';
			$hash    = md5( $hint . "\n" . $snippet . "\n" . $detail );
			return $type . '|' . $post . '|' . $ctx . '|' . $hash;
		}

		$discriminator = '';
		switch ( $type ) {
			case 'link_no_text':
				$discriminator = isset( $issue['href'] ) ? substr( (string) $issue['href'], 0, 160 ) : '';
				break;
			case 'image_no_alt':
				$discriminator = isset( $issue['src'] ) ? substr( (string) $issue['src'], 0, 160 ) : '';
				break;
			case 'input_no_label':
				if ( isset( $issue['input_type'] ) ) {
					$discriminator = (string) $issue['input_type'];
				} elseif ( isset( $issue['type'] ) && 'input_no_label' !== (string) $issue['type'] ) {
					// Legacy rows before input_type existed (type held the HTML input type).
					$discriminator = (string) $issue['type'];
				} else {
					$discriminator = '';
				}
				break;
			default:
				$discriminator = '';
				break;
		}

		return $type . '|' . $post . '|' . $ctx . '|' . $discriminator;
	}
}
