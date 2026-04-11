<?php
/**
 * Site content scanner and ScoreFix Score (0–100).
 *
 * @package ScoreFix
 */

namespace ScoreFix\Scanner;

use ScoreFix\Fixes\FixEngine;
use ScoreFix\Scanner\Rules\ButtonsRule;
use ScoreFix\Scanner\Rules\ContrastInlineRule;
use ScoreFix\Scanner\Rules\EmbeddedMediaRule;
use ScoreFix\Scanner\Rules\FormControlsRule;
use ScoreFix\Scanner\Rules\FormsSemanticsRule;
use ScoreFix\Scanner\Rules\HeadingsRule;
use ScoreFix\Scanner\Rules\ImagesRule;
use ScoreFix\Scanner\Rules\LandmarksRule;
use ScoreFix\Scanner\Rules\LinkGenericTextRule;
use ScoreFix\Scanner\Rules\LinksRule;
use ScoreFix\Scanner\Rules\TablesSemanticRule;

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
		'image_no_alt'                   => 3,
		'link_no_text'                   => 3,
		'button_no_text'                 => 4,
		'input_no_label'                 => 4,
		'contrast_risk'                  => 2,
		// Reserved weights for upcoming rules (Phase 2); conservative defaults.
		'heading_multiple_h1'            => 2,
		'heading_level_skip'             => 2,
		'landmark_no_main'               => 1,
		'landmark_multiple_main'         => 2,
		'landmark_nav_unnamed'           => 1,
		'link_generic_text'              => 2,
		'form_radio_group_no_legend'    => 2,
		'form_required_no_error_hint'   => 1,
		'form_autocomplete_missing'      => 1,
		'video_no_text_track'            => 2,
		'audio_no_text_track'            => 2,
		'iframe_missing_title'           => 3,
		'table_missing_th'               => 2,
		'table_missing_caption'          => 1,
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
				$issues[] = $this->create_issue(
					'image_no_alt',
					'high',
					array(
						'post_id'   => (int) $att_id,
						'context'   => 'media_library',
						'title'     => get_the_title( $att_id ),
						'impact'    => 'business',
						'source'    => 'attachment',
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

		$maker = array( $this, 'create_issue' );
		$pid   = (int) $post_id;
		$issues = array_merge( $issues, HeadingsRule::collect( $xpath, $pid, $maker ) );
		$issues = array_merge( $issues, LandmarksRule::collect( $xpath, $pid, $maker ) );
		$issues = array_merge( $issues, ImagesRule::collect( $xpath, $pid, $maker ) );
		$issues = array_merge( $issues, LinksRule::collect( $xpath, $pid, $maker ) );
		$issues = array_merge( $issues, LinkGenericTextRule::collect( $xpath, $pid, $maker ) );
		$issues = array_merge( $issues, ButtonsRule::collect( $xpath, $pid, $maker ) );
		$issues = array_merge( $issues, FormControlsRule::collect( $xpath, $dom, $pid, $maker ) );
		$issues = array_merge( $issues, FormsSemanticsRule::collect( $xpath, $pid, $maker ) );
		$issues = array_merge( $issues, ContrastInlineRule::collect( $xpath, $pid, $maker ) );
		$issues = array_merge( $issues, EmbeddedMediaRule::collect( $xpath, $pid, $maker ) );
		$issues = array_merge( $issues, TablesSemanticRule::collect( $xpath, $pid, $maker ) );

		return $issues;
	}

	/**
	 * Public factory for scan rules (wraps make_issue, default metadata).
	 *
	 * @param string               $type     Issue type.
	 * @param string               $severity Severity slug.
	 * @param array<string, mixed> $extra    Extra fields.
	 * @return array<string, mixed>
	 */
	public function create_issue( $type, $severity, array $extra ) {
		if ( ! isset( $extra['source'] ) ) {
			$extra['source'] = 'post_content';
		}
		return $this->make_issue( $type, $severity, $extra );
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
