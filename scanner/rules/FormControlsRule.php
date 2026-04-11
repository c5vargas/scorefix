<?php
/**
 * Scan form controls for missing labels.
 *
 * @package ScoreFix
 */

namespace ScoreFix\Scanner\Rules;

use ScoreFix\Scanner\HtmlScanHelpers;

defined( 'ABSPATH' ) || exit;

/**
 * Class FormControlsRule
 */
class FormControlsRule {

	/**
	 * Collect input_no_label issues for inputs, selects, textareas.
	 *
	 * @param \DOMXPath    $xpath   Bound document.
	 * @param \DOMDocument $dom     Same document as xpath.
	 * @param int          $post_id Post ID.
	 * @param callable     $issue   Issue factory.
	 * @return array<int, array<string, mixed>>
	 */
	public static function collect( \DOMXPath $xpath, \DOMDocument $dom, $post_id, callable $issue ) {
		$out       = array();
		$input_types = array( 'text', 'email', 'tel', 'url', 'search', 'number', 'password', 'checkbox', 'radio' );

		foreach ( $xpath->query( '//input' ) as $input ) {
			if ( ! $input instanceof \DOMElement ) {
				continue;
			}
			$type = strtolower( (string) $input->getAttribute( 'type' ) );
			if ( '' === $type ) {
				$type = 'text';
			}
			if ( ! in_array( $type, $input_types, true ) ) {
				continue;
			}
			if ( HtmlScanHelpers::form_control_has_label( $input, $dom ) ) {
				continue;
			}
			$out[] = $issue(
				'input_no_label',
				'high',
				array(
					'post_id'    => (int) $post_id,
					'input_type' => $type,
					'impact'     => 'conversion',
				)
			);
		}

		foreach ( $xpath->query( '//select' ) as $sel ) {
			if ( ! $sel instanceof \DOMElement ) {
				continue;
			}
			if ( HtmlScanHelpers::form_control_has_label( $sel, $dom ) ) {
				continue;
			}
			$out[] = $issue(
				'input_no_label',
				'high',
				array(
					'post_id'    => (int) $post_id,
					'input_type' => 'select',
					'impact'     => 'conversion',
				)
			);
		}

		foreach ( $xpath->query( '//textarea' ) as $ta ) {
			if ( ! $ta instanceof \DOMElement ) {
				continue;
			}
			if ( HtmlScanHelpers::form_control_has_label( $ta, $dom ) ) {
				continue;
			}
			$out[] = $issue(
				'input_no_label',
				'high',
				array(
					'post_id'    => (int) $post_id,
					'input_type' => 'textarea',
					'impact'     => 'conversion',
				)
			);
		}

		return $out;
	}
}
