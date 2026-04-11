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
			$base    = $type . '|' . $post . '|' . $ctx . '|' . $hash;
			if ( ! empty( $issue['capture_url'] ) ) {
				$base .= '|u:' . md5( (string) $issue['capture_url'] );
			}
			return $base;
		}

		$discriminator = '';
		switch ( $type ) {
			case 'link_no_text':
				$discriminator = isset( $issue['href'] ) ? substr( (string) $issue['href'], 0, 160 ) : '';
				break;
			case 'link_generic_text':
				$discriminator = ( isset( $issue['link_text'] ) ? (string) $issue['link_text'] : '' ) . '|' .
					( isset( $issue['href'] ) ? substr( (string) $issue['href'], 0, 120 ) : '' );
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
			case 'heading_multiple_h1':
				$discriminator = isset( $issue['h1_count'] ) ? (string) (int) $issue['h1_count'] : 'multi';
				break;
			case 'heading_level_skip':
				$discriminator = ( isset( $issue['from_tag'] ) ? (string) $issue['from_tag'] : '' ) . '>' .
					( isset( $issue['to_tag'] ) ? (string) $issue['to_tag'] : '' );
				break;
			case 'landmark_multiple_main':
				$discriminator = isset( $issue['main_count'] ) ? (string) (int) $issue['main_count'] : '';
				break;
			case 'landmark_no_main':
				$discriminator = 'no-main';
				break;
			case 'landmark_nav_unnamed':
				$discriminator = ( isset( $issue['nav_total'] ) ? (string) (int) $issue['nav_total'] : '0' ) . '/' .
					( isset( $issue['nav_unnamed'] ) ? (string) (int) $issue['nav_unnamed'] : '0' );
				break;
			case 'form_radio_group_no_legend':
				$discriminator = isset( $issue['group_name'] ) ? (string) $issue['group_name'] : '';
				break;
			case 'form_required_no_error_hint':
				$discriminator = ( isset( $issue['control_tag'] ) ? (string) $issue['control_tag'] : '' ) . '|' .
					( isset( $issue['input_type'] ) ? (string) $issue['input_type'] : '' );
				break;
			case 'form_autocomplete_missing':
				$discriminator = ( isset( $issue['input_type'] ) ? (string) $issue['input_type'] : '' ) . '|' .
					( isset( $issue['field_hint'] ) ? (string) $issue['field_hint'] : '' );
				break;
			case 'video_no_text_track':
			case 'audio_no_text_track':
				$discriminator = isset( $issue['element_key'] ) ? (string) $issue['element_key'] : '';
				break;
			case 'iframe_missing_title':
				$discriminator = isset( $issue['element_key'] ) ? (string) $issue['element_key'] :
					( isset( $issue['src'] ) ? substr( (string) $issue['src'], 0, 160 ) : '' );
				break;
			case 'table_missing_th':
			case 'table_missing_caption':
				$discriminator = isset( $issue['table_ordinal'] ) ? (string) (int) $issue['table_ordinal'] : '';
				break;
			case 'perf_img_missing_dimensions':
			case 'perf_img_missing_lazy':
				$discriminator = isset( $issue['src'] ) ? substr( (string) $issue['src'], 0, 160 ) : '';
				break;
			case 'perf_many_external_scripts':
				$discriminator = isset( $issue['script_src_count'] ) ? (string) (int) $issue['script_src_count'] : '';
				break;
			default:
				$discriminator = '';
				break;
		}

		$base = $type . '|' . $post . '|' . $ctx . '|' . $discriminator;
		if ( ! empty( $issue['capture_url'] ) ) {
			$base .= '|u:' . md5( (string) $issue['capture_url'] );
		}
		return $base;
	}
}
