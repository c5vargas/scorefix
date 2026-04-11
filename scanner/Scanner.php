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
use ScoreFix\Scanner\Rules\PerformanceHeuristicRule;
use ScoreFix\Scanner\Rules\SeoFragmentRule;
use ScoreFix\Scanner\Rules\TablesSemanticRule;
use ScoreFix\Scanner\ScanComparison;

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
		// Phase 5A — local performance heuristics (conservative weights).
		'perf_img_missing_dimensions'     => 1,
		'perf_img_missing_lazy'          => 1,
		'perf_many_external_scripts'     => 2,
		// Phase 2 SEO fragment heuristics (conservative).
		'seo_thin_content'               => 1,
		'seo_few_internal_links'         => 1,
		// Phase 3 — head audits on rendered HTML (conservative).
		'seo_head_title_missing'         => 2,
		'seo_head_title_length'          => 1,
		'seo_head_meta_description_missing' => 1,
		'seo_head_canonical_missing'     => 1,
		'seo_head_viewport_missing'      => 2,
		'seo_head_robots_noindex'        => 1,
		// Phase 4 — JSON-LD diagnostics (conservative).
		'seo_jsonld_invalid_json'             => 1,
		'seo_jsonld_missing_expected_type'    => 1,
	);

	/**
	 * Max total penalty before score hits 0 (per-bucket and legacy linear).
	 */
	const PENALTY_CAP = 100;

	/**
	 * Cumulative weighted penalty scale for the global volume component (higher = more issues needed to tank score).
	 */
	const SCORE_VOLUME_SCALE = 235;

	/**
	 * Weight of per-bucket average in final score; remainder comes from global volume (0–1).
	 */
	const SCORE_BUCKET_BLEND_WEIGHT = 0.36;

	/**
	 * Run full scan and persist snapshot.
	 *
	 * @param array<string, mixed> $args Optional. `trigger` (string): scan reason for history/UI.
	 * @return array<string, mixed> Snapshot including optional comparison vs previous scan.
	 */
	public function run( array $args = array() ) {
		$trigger = isset( $args['trigger'] ) ? sanitize_key( (string) $args['trigger'] ) : 'manual';
		if ( '' === $trigger ) {
			$trigger = 'manual';
		}

		$previous   = get_option( self::OPTION_LAST_SCAN, null );
		$had_prior  = is_array( $previous ) && isset( $previous['scanned_at'] );
		$prev_issues = array();
		$prior_score = null;
		if ( $had_prior && isset( $previous['issues'] ) && is_array( $previous['issues'] ) ) {
			$prev_issues = $previous['issues'];
		}
		if ( $had_prior && isset( $previous['score'] ) ) {
			$prior_score = (int) $previous['score'];
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

		$posts_scanned     = 0;
		$scanned_post_ids = array();
		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				++$posts_scanned;
				$post_id = get_the_ID();
				$scanned_post_ids[] = (int) $post_id;
				$html               = $this->prepare_html_for_scan( get_post_field( 'post_content', $post_id ) );
				$issues             = array_merge( $issues, $this->scan_html( $html, $post_id ) );
			}
			wp_reset_postdata();
		}

		$attachment_result  = $this->scan_attachment_images();
		$attachment_issues  = $attachment_result['issues'];
		$scanned_attachment_ids = $attachment_result['ids'];
		$issues               = array_merge( $issues, $attachment_issues );

		$score_context = array(
			'scanned_post_ids'       => $scanned_post_ids,
			'scanned_attachment_ids' => $scanned_attachment_ids,
		);
		$score         = $this->calculate_score( $issues, $score_context );

		$prev_for_compare = self::issues_without_rendered( $prev_issues );

		$snapshot = array(
			'score'                  => $score,
			'issues'                 => $issues,
			'scanned_at'             => gmdate( 'c' ),
			'version'                => SCOREFIX_VERSION,
			'posts_scanned'          => $posts_scanned,
			'scanned_post_ids'       => $scanned_post_ids,
			'scanned_attachment_ids' => $scanned_attachment_ids,
			'score_model'            => 'per_page_average_v3_blend',
			'comparison'             => ScanComparison::build( $had_prior, $prev_for_compare, $issues, $prior_score, $score ),
			'scan_trigger'           => $trigger,
		);

		update_option( self::OPTION_LAST_SCAN, $snapshot, false );

		ScoreHistory::record_from_snapshot( $snapshot );

		RenderScanQueue::start_after_sync_scan( $had_prior, $prev_issues, $prior_score );

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
	 * @return array{issues: array<int, array<string, mixed>>, ids: array<int, int>}
	 */
	protected function scan_attachment_images() {
		$issues = array();
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

		$ids = array();
		foreach ( is_array( $q->posts ) ? $q->posts : array() as $att_id ) {
			$ids[] = (int) $att_id;
		}

		if ( $this->fixes_enabled() ) {
			// Runtime fills missing alt via wp_get_attachment_image_attributes; do not penalise DB meta.
			return array(
				'issues' => array(),
				'ids'    => $ids,
			);
		}

		foreach ( $ids as $att_id ) {
			$alt = get_post_meta( $att_id, '_wp_attachment_image_alt', true );
			$alt = is_string( $alt ) ? trim( $alt ) : '';
			if ( '' === $alt ) {
				$issues[] = $this->create_issue(
					'image_no_alt',
					'high',
					array(
						'post_id' => (int) $att_id,
						'context' => 'media_library',
						'title'   => get_the_title( $att_id ),
						'impact'  => 'business',
						'source'  => 'attachment',
					)
				);
			}
		}

		return array(
			'issues' => $issues,
			'ids'    => $ids,
		);
	}

	/**
	 * Parse HTML and collect issues for one post.
	 *
	 * @param string        $html           Post content HTML.
	 * @param int           $post_id        Post ID (0 for rendered URL body).
	 * @param callable|null $issue_factory function( string $type, string $severity, array $extra ): array or null to use create_issue.
	 * @return array<int, array<string, mixed>>
	 */
	public function scan_html( $html, $post_id, callable $issue_factory = null ) {
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

		$maker = null !== $issue_factory ? $issue_factory : array( $this, 'create_issue' );
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
		$issues = array_merge( $issues, PerformanceHeuristicRule::collect( $xpath, $pid, $maker ) );
		$issues = array_merge( $issues, SeoFragmentRule::collect( $xpath, $pid, $maker ) );

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
	 * With {@see $context} `scanned_post_ids` (from last full scan), uses **per-bucket average**:
	 * each published post scanned = one bucket (0–100 from its non-attachment, non-rendered issues),
	 * the **whole media-library pass** = one bucket (sum of all attachment-source penalties, capped),
	 * each distinct rendered URL = one bucket.
	 * Final score blends: (1) round( average of bucket subscores ) and (2) a global component from cumulative
	 * weighted penalties across all issue rows (so large issue counts cannot hide behind “mostly clean pages”).
	 * Legacy snapshots omit context → linear sum model.
	 *
	 * Unknown `issue_type` keys fall back to a small default penalty via {@see penalty_for_issue()}; add explicit
	 * {@see self::PENALTY} entries for new rules so weighting stays intentional.
	 *
	 * @param array<int, array<string, mixed>>       $issues  Issues.
	 * @param array<string, mixed>                   $context Optional. Keys: `scanned_post_ids` int[], `scanned_attachment_ids` int[].
	 * @return int
	 */
	public function calculate_score( array $issues, array $context = array() ) {
		$context = apply_filters( 'scorefix_score_context', $context, $issues );

		if ( ! isset( $context['scanned_post_ids'] ) || ! is_array( $context['scanned_post_ids'] ) ) {
			$linear = $this->calculate_score_linear( $issues );
			return (int) apply_filters( 'scorefix_calculated_score', $linear, $issues, $context );
		}

		$post_ids = array_values(
			array_unique(
				array_filter(
					array_map(
						static function ( $id ) {
							return (int) $id > 0 ? (int) $id : 0;
						},
						$context['scanned_post_ids']
					)
				)
			)
		);

		$att_ids = array();
		if ( isset( $context['scanned_attachment_ids'] ) && is_array( $context['scanned_attachment_ids'] ) ) {
			$att_ids = array_values(
				array_unique(
					array_filter(
						array_map(
							static function ( $id ) {
								return (int) $id > 0 ? (int) $id : 0;
							},
							$context['scanned_attachment_ids']
						)
					)
				)
			);
		}

		$sum = 0.0;
		$cnt = 0;

		foreach ( $post_ids as $pid ) {
			$p = $this->sum_penalties_for_post_content_bucket( $issues, $pid );
			$sum += (float) max( 0, 100 - min( self::PENALTY_CAP, $p ) );
			++$cnt;
		}

		if ( ! empty( $att_ids ) ) {
			$p = $this->sum_penalties_media_library_bucket( $issues );
			$sum += (float) max( 0, 100 - min( self::PENALTY_CAP, $p ) );
			++$cnt;
		}

		$render_urls = array();
		foreach ( $issues as $iss ) {
			if ( ! is_array( $iss ) ) {
				continue;
			}
			$src = isset( $iss['source'] ) ? sanitize_key( (string) $iss['source'] ) : '';
			if ( 'rendered_url' !== $src ) {
				continue;
			}
			$u = isset( $iss['capture_url'] ) ? esc_url_raw( (string) $iss['capture_url'] ) : '';
			if ( '' !== $u ) {
				$render_urls[ $u ] = true;
			}
		}
		foreach ( array_keys( $render_urls ) as $url ) {
			$p = $this->sum_penalties_for_render_url_bucket( $issues, $url );
			$sum += (float) max( 0, 100 - min( self::PENALTY_CAP, $p ) );
			++$cnt;
		}

		if ( $cnt < 1 ) {
			$linear = $this->calculate_score_linear( $issues );
			return (int) apply_filters( 'scorefix_calculated_score', $linear, $issues, $context );
		}

		$bucket_score = (int) max( 0, min( 100, (int) round( $sum / $cnt ) ) );
		$volume_total = $this->sum_weighted_penalties( $issues );
		$scale        = (float) apply_filters( 'scorefix_score_volume_scale', self::SCORE_VOLUME_SCALE, $issues, $context );
		$scale        = $scale > 0 ? $scale : (float) self::SCORE_VOLUME_SCALE;
		$vol_score    = $this->volume_score_from_total( $volume_total, $scale );
		$blend_w      = (float) apply_filters( 'scorefix_score_bucket_blend_weight', self::SCORE_BUCKET_BLEND_WEIGHT, $issues, $context );
		$blend_w      = max( 0.0, min( 1.0, $blend_w ) );
		$score        = (int) max(
			0,
			min(
				100,
				(int) round( $blend_w * $bucket_score + ( 1.0 - $blend_w ) * $vol_score )
			)
		);
		return (int) apply_filters( 'scorefix_calculated_score', $score, $issues, $context );
	}

	/**
	 * Legacy: single global penalty cap (used when scan context is missing, e.g. old snapshots).
	 *
	 * @param array<int, array<string, mixed>> $issues Issues.
	 * @return int
	 */
	protected function calculate_score_linear( array $issues ) {
		$penalty = 0;
		foreach ( $issues as $issue ) {
			if ( ! is_array( $issue ) ) {
				continue;
			}
			$penalty += $this->penalty_for_issue( $issue );
		}
		$penalty = min( self::PENALTY_CAP, $penalty );
		return (int) max( 0, 100 - $penalty );
	}

	/**
	 * @param array<string, mixed> $issue Issue row.
	 * @return int
	 */
	protected function penalty_for_issue( array $issue ) {
		$type = isset( $issue['type'] ) ? (string) $issue['type'] : '';
		return isset( self::PENALTY[ $type ] ) ? (int) self::PENALTY[ $type ] : 2;
	}

	/**
	 * Per-issue penalty adjusted by UI severity (high-impact errors pull the global score down more).
	 *
	 * @param array<string, mixed> $issue Issue row.
	 * @return int
	 */
	protected function weighted_penalty_for_issue( array $issue ) {
		$base = $this->penalty_for_issue( $issue );
		$s    = isset( $issue['severity'] ) ? (string) $issue['severity'] : '';
		if ( ScanComparison::SEVERITY_ERROR === $s ) {
			return (int) max( 1, (int) round( $base * 1.38 ) );
		}
		if ( 'low' === $s ) {
			return (int) max( 1, (int) round( $base * 0.88 ) );
		}
		return $base;
	}

	/**
	 * Sum of weighted penalties for every issue row (matches dashboard issue list cardinality).
	 *
	 * @param array<int, array<string, mixed>> $issues Issues.
	 * @return int
	 */
	protected function sum_weighted_penalties( array $issues ) {
		$t = 0;
		foreach ( $issues as $issue ) {
			if ( ! is_array( $issue ) ) {
				continue;
			}
			$t += $this->weighted_penalty_for_issue( $issue );
		}
		return $t;
	}

	/**
	 * Map cumulative weighted penalty to a 0–100 subscore (100 = no volume penalty).
	 *
	 * @param int   $total_weighted Sum of weighted penalties.
	 * @param float $scale          Divisor (see SCORE_VOLUME_SCALE).
	 * @return float
	 */
	protected function volume_score_from_total( $total_weighted, $scale ) {
		$total_weighted = max( 0, (int) $total_weighted );
		$raw            = ( $total_weighted / $scale ) * 100.0;
		return max( 0.0, min( 100.0, 100.0 - min( 100.0, $raw ) ) );
	}

	/**
	 * Penalties for issues tied to a post's stored content (not attachment, not rendered URL pass).
	 *
	 * @param array<int, array<string, mixed>> $issues All issues.
	 * @param int                              $post_id Post ID.
	 * @return int
	 */
	protected function sum_penalties_for_post_content_bucket( array $issues, $post_id ) {
		$post_id = (int) $post_id;
		$p       = 0;
		foreach ( $issues as $issue ) {
			if ( ! is_array( $issue ) ) {
				continue;
			}
			if ( (int) ( $issue['post_id'] ?? 0 ) !== $post_id ) {
				continue;
			}
			$src = isset( $issue['source'] ) ? sanitize_key( (string) $issue['source'] ) : 'post_content';
			if ( 'attachment' === $src || 'rendered_url' === $src ) {
				continue;
			}
			$p += $this->penalty_for_issue( $issue );
		}
		return $p;
	}

	/**
	 * Sum penalties for all attachment-library issues (single score bucket so hundreds of clean files do not inflate the average).
	 *
	 * @param array<int, array<string, mixed>> $issues All issues.
	 * @return int
	 */
	protected function sum_penalties_media_library_bucket( array $issues ) {
		$p = 0;
		foreach ( $issues as $issue ) {
			if ( ! is_array( $issue ) ) {
				continue;
			}
			$src = isset( $issue['source'] ) ? sanitize_key( (string) $issue['source'] ) : '';
			if ( 'attachment' !== $src ) {
				continue;
			}
			$p += $this->penalty_for_issue( $issue );
		}
		return $p;
	}

	/**
	 * @param array<int, array<string, mixed>> $issues All issues.
	 * @param string                           $url    Normalized capture URL.
	 * @return int
	 */
	protected function sum_penalties_for_render_url_bucket( array $issues, $url ) {
		$p = 0;
		foreach ( $issues as $issue ) {
			if ( ! is_array( $issue ) ) {
				continue;
			}
			$src = isset( $issue['source'] ) ? sanitize_key( (string) $issue['source'] ) : '';
			if ( 'rendered_url' !== $src ) {
				continue;
			}
			$u = isset( $issue['capture_url'] ) ? esc_url_raw( (string) $issue['capture_url'] ) : '';
			if ( $u !== $url ) {
				continue;
			}
			$p += $this->penalty_for_issue( $issue );
		}
		return $p;
	}

	/**
	 * Strip issues from rendered HTML pass (used when comparing sync-scan snapshot vs prior scan).
	 *
	 * `source: rendered_url` issues arrive asynchronously; excluding them keeps ScanComparison deltas stable until
	 * the render queue finishes. Same PENALTY weights apply once those rows exist.
	 *
	 * @param array<int, array<string, mixed>> $issues Issues.
	 * @return array<int, array<string, mixed>>
	 */
	protected static function issues_without_rendered( array $issues ) {
		$out = array();
		foreach ( $issues as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$src = isset( $row['source'] ) ? sanitize_key( (string) $row['source'] ) : '';
			if ( 'rendered_url' === $src ) {
				continue;
			}
			$out[] = $row;
		}
		return $out;
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
