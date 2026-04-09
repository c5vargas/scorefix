<?php
/**
 * Site content scanner and ScoreFix Score (0–100).
 *
 * @package ScoreFix
 */

namespace ScoreFix\Scanner;

use ScoreFix\Fixes\FixEngine;

defined( 'ABSPATH' ) || exit;

/**
 * Class Scanner
 */
class Scanner {

	const OPTION_LAST_SCAN = 'scorefix_last_scan';

	/**
	 * Severity weights for scoring (sum capped).
	 */
	const PENALTY = array(
		'image_no_alt'      => 3,
		'link_no_text'      => 3,
		'button_no_text'    => 4,
		'input_no_label'    => 4,
		'contrast_risk'     => 2,
	);

	/**
	 * Max total penalty before score hits 0.
	 */
	const PENALTY_CAP = 100;

	/**
	 * Run full scan and persist snapshot.
	 *
	 * @return array<string, mixed> Snapshot including optional comparison vs previous scan.
	 */
	public function run() {
		$previous   = get_option( self::OPTION_LAST_SCAN, null );
		$had_prior  = is_array( $previous ) && isset( $previous['scanned_at'] );
		$prev_issues = array();
		if ( $had_prior && isset( $previous['issues'] ) && is_array( $previous['issues'] ) ) {
			$prev_issues = $previous['issues'];
		}

		$issues = array();

		$post_types = apply_filters( 'scorefix_scan_post_types', array( 'post', 'page', 'product' ) );
		$post_types = array_filter( array_map( 'sanitize_key', $post_types ) );
		if ( empty( $post_types ) ) {
			$post_types = array( 'post', 'page' );
		}
		$post_types = array_values( array_filter( $post_types, 'post_type_exists' ) );
		if ( empty( $post_types ) ) {
			$post_types = array( 'post', 'page' );
		}

		$query = new \WP_Query(
			array(
				'post_type'              => $post_types,
				'post_status'            => 'publish',
				'posts_per_page'         => apply_filters( 'scorefix_scan_posts_per_batch', 200 ),
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$posts_scanned = 0;
		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				++$posts_scanned;
				$post_id = get_the_ID();
				$html    = $this->prepare_html_for_scan( get_post_field( 'post_content', $post_id ) );
				$issues  = array_merge( $issues, $this->scan_html( $html, $post_id ) );
			}
			wp_reset_postdata();
		}

		$attachment_issues = $this->scan_attachment_images();
		$issues            = array_merge( $issues, $attachment_issues );

		$score = $this->calculate_score( $issues );

		$snapshot = array(
			'score'           => $score,
			'issues'          => $issues,
			'scanned_at'      => gmdate( 'c' ),
			'version'         => SCOREFIX_VERSION,
			'posts_scanned'   => $posts_scanned,
			'comparison'      => ScanComparison::build( $had_prior, $prev_issues, $issues ),
		);

		update_option( self::OPTION_LAST_SCAN, $snapshot, false );

		return $snapshot;
	}

	/**
	 * When automatic fixes are on, mirror runtime processing so the score matches the live site.
	 *
	 * @param string $html Raw post_content.
	 * @return string HTML to analyse.
	 */
	protected function prepare_html_for_scan( $html ) {
		if ( ! $this->fixes_enabled() ) {
			return (string) $html;
		}
		$html = (string) $html;
		if ( '' === trim( $html ) ) {
			return $html;
		}
		// Skip when stored content is not HTML (e.g. builder JSON/shortcodes only): fixes apply at render time.
		if ( ! preg_match( '/<\s*[a-zA-Z!]/', $html ) ) {
			return $html;
		}
		$engine = new FixEngine();
		return $engine->process_content( $html );
	}

	/**
	 * Whether automatic fixes are enabled (same option as frontend).
	 *
	 * @return bool
	 */
	protected function fixes_enabled() {
		$settings = get_option( 'scorefix_settings', array() );
		return is_array( $settings ) && ! empty( $settings['fixes_enabled'] );
	}

	/**
	 * Scan attachment library for images missing alt text.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function scan_attachment_images() {
		$issues = array();
		if ( $this->fixes_enabled() ) {
			// Runtime fills missing alt via wp_get_attachment_image_attributes; do not penalise DB meta.
			return $issues;
		}
		$q      = new \WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => 'image',
				'posts_per_page' => apply_filters( 'scorefix_scan_attachment_limit', 300 ),
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		foreach ( $q->posts as $att_id ) {
			$alt = get_post_meta( $att_id, '_wp_attachment_image_alt', true );
			$alt = is_string( $alt ) ? trim( $alt ) : '';
			if ( '' === $alt ) {
				$issues[] = $this->make_issue(
					'image_no_alt',
					'high',
					array(
						'post_id'   => (int) $att_id,
						'context'   => 'media_library',
						'title'     => get_the_title( $att_id ),
						'impact'    => 'business',
					)
				);
			}
		}

		return $issues;
	}

	/**
	 * Parse HTML and collect issues for one post.
	 *
	 * @param string $html    Post content HTML.
	 * @param int    $post_id Post ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function scan_html( $html, $post_id ) {
		$issues = array();
		if ( '' === trim( (string) $html ) ) {
			return $issues;
		}

		libxml_use_internal_errors( true );
		$dom = new \DOMDocument();
		$wrapped = '<div id="scorefix-root">' . $html . '</div>';
		$dom->loadHTML( '<?xml encoding="utf-8"?>' . $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();

		$xpath = new \DOMXPath( $dom );

		// Images: missing or empty alt (decorative heuristic: role="presentation" skipped).
		foreach ( $xpath->query( '//img' ) as $img ) {
			if ( ! $img instanceof \DOMElement ) {
				continue;
			}
			$role = strtolower( (string) $img->getAttribute( 'role' ) );
			if ( 'presentation' === $role || 'none' === $role ) {
				continue;
			}
			if ( ! $img->hasAttribute( 'alt' ) || trim( $img->getAttribute( 'alt' ) ) === '' ) {
				$issues[] = $this->make_issue(
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

		// Links without accessible name.
		foreach ( $xpath->query( '//a[@href]' ) as $a ) {
			if ( ! $a instanceof \DOMElement ) {
				continue;
			}
			if ( ! $this->element_has_accessible_name( $a ) ) {
				$issues[] = $this->make_issue(
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

		// Buttons without accessible name.
		foreach ( $xpath->query( '//button' ) as $btn ) {
			if ( ! $btn instanceof \DOMElement ) {
				continue;
			}
			if ( ! $this->element_has_accessible_name( $btn ) ) {
				$issues[] = $this->make_issue(
					'button_no_text',
					'high',
					array(
						'post_id' => (int) $post_id,
						'impact'  => 'conversion',
					)
				);
			}
		}

		// Inputs: text-like without label.
		$input_types = array( 'text', 'email', 'tel', 'url', 'search', 'number', 'password' );
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
			if ( $this->input_has_label( $input, $dom ) ) {
				continue;
			}
			$issues[] = $this->make_issue(
				'input_no_label',
				'high',
				array(
					'post_id' => (int) $post_id,
					'type'    => $type,
					'impact'  => 'conversion',
				)
			);
		}

		// Contrast heuristics on inline styles (scanner-only; see ContrastStyleAnalyzer).
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
				'post_id'        => (int) $post_id,
				'impact'         => 'readability',
				'hint'           => isset( $contrast['hint'] ) ? (string) $contrast['hint'] : 'unknown',
				'style_snippet'  => substr( preg_replace( '/\s+/', ' ', $raw_style ), 0, 120 ),
			);
			if ( isset( $contrast['ratio'] ) ) {
				$extra['ratio'] = $contrast['ratio'];
			}
			if ( isset( $contrast['detail'] ) ) {
				$extra['detail'] = (string) $contrast['detail'];
			}
			$issues[] = $this->make_issue( 'contrast_risk', 'medium', $extra );
		}

		return $issues;
	}

	/**
	 * Whether interactive element has accessible name.
	 *
	 * @param \DOMElement $el Element.
	 * @return bool
	 */
	protected function element_has_accessible_name( \DOMElement $el ) {
		$label = trim( $el->textContent ?? '' );
		if ( $label !== '' ) {
			return true;
		}
		$aria = trim( (string) $el->getAttribute( 'aria-label' ) );
		if ( $aria !== '' ) {
			return true;
		}
		$labelledby = trim( (string) $el->getAttribute( 'aria-labelledby' ) );
		if ( $labelledby !== '' ) {
			return true;
		}
		$title = trim( (string) $el->getAttribute( 'title' ) );
		if ( $title !== '' ) {
			return true;
		}
		// Image inside link/button may provide name.
		$imgs = $el->getElementsByTagName( 'img' );
		if ( $imgs->length > 0 ) {
			$img = $imgs->item( 0 );
			if ( $img instanceof \DOMElement ) {
				$alt = trim( (string) $img->getAttribute( 'alt' ) );
				if ( $alt !== '' ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Whether input is associated with a label.
	 *
	 * @param \DOMElement   $input Input element.
	 * @param \DOMDocument $dom   Document.
	 * @return bool
	 */
	protected function input_has_label( \DOMElement $input, \DOMDocument $dom ) {
		$id = trim( (string) $input->getAttribute( 'id' ) );
		if ( $id !== '' ) {
			$xpath = new \DOMXPath( $dom );
			$labels = $xpath->query( '//label[@for="' . self::escape_xpath_literal( $id ) . '"]' );
			if ( $labels && $labels->length > 0 ) {
				return true;
			}
		}
		$aria = trim( (string) $input->getAttribute( 'aria-label' ) );
		if ( $aria !== '' ) {
			return true;
		}
		$alb = trim( (string) $input->getAttribute( 'aria-labelledby' ) );
		if ( $alb !== '' ) {
			return true;
		}
		$placeholder = trim( (string) $input->getAttribute( 'placeholder' ) );
		// Placeholder alone is weak; still flag as partial for MVP we require label or aria.
		if ( $placeholder !== '' ) {
			return apply_filters( 'scorefix_count_placeholder_as_label', false, $input );
		}
		return false;
	}

	/**
	 * Escape string for XPath literal.
	 *
	 * @param string $s String.
	 * @return string
	 */
	protected static function escape_xpath_literal( $s ) {
		if ( false === strpos( $s, "'" ) ) {
			return "'" . $s . "'";
		}
		if ( false === strpos( $s, '"' ) ) {
			return '"' . $s . '"';
		}
		return "concat('" . str_replace( "'", "', \"'\", '", $s ) . "')";
	}

	/**
	 * Build issue row.
	 *
	 * @param string               $type     Issue type key.
	 * @param string               $severity Severity slug.
	 * @param array<string, mixed> $extra    Extra data.
	 * @return array<string, mixed>
	 */
	protected function make_issue( $type, $severity, array $extra ) {
		$extra['type']     = $type;
		$extra['severity'] = $severity;
		$extra['id']       = wp_generate_password( 8, false, false );
		return $extra;
	}

	/**
	 * Calculate ScoreFix Score from issues.
	 *
	 * @param array<int, array<string, mixed>> $issues Issues.
	 * @return int
	 */
	public function calculate_score( array $issues ) {
		$penalty = 0;
		foreach ( $issues as $issue ) {
			$type = isset( $issue['type'] ) ? (string) $issue['type'] : '';
			$p    = isset( self::PENALTY[ $type ] ) ? (int) self::PENALTY[ $type ] : 2;
			$penalty += $p;
		}
		$penalty = min( self::PENALTY_CAP, $penalty );
		return (int) max( 0, 100 - $penalty );
	}

	/**
	 * Get last scan snapshot.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function get_last_scan() {
		$data = get_option( self::OPTION_LAST_SCAN, null );
		return is_array( $data ) ? $data : null;
	}
}
