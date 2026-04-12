<?php
/**
 * Admin dashboard page.
 *
 * @package ScoreFix
 */

namespace ScoreFix\Admin;

use ScoreFix\Scanner\IssueGlossary;
use ScoreFix\Scanner\RenderScanQueue;
use ScoreFix\Scanner\ScanComparison;
use ScoreFix\Scanner\Scanner;
use ScoreFix\Scanner\ScoreHistory;

defined( 'ABSPATH' ) || exit;

/**
 * Class DashboardPage
 */
class DashboardPage {

	/**
	 * Register AJAX handlers (must run on init — admin-ajax.php does not fire admin_menu).
	 *
	 * @return void
	 */
	public function register_ajax_handlers() {
		add_action( 'wp_ajax_scorefix_render_scan_status', array( $this, 'ajax_render_scan_status' ) );
	}

	/**
	 * Register top-level menu under Settings or Tools — use Settings submenu for simplicity.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_menu_page(
			__( 'ScoreFix', 'scorefix' ),
			__( 'ScoreFix', 'scorefix' ),
			'manage_options',
			'scorefix',
			array( $this, 'render_page' ),
			'dashicons-chart-line',
			59
		);
	}

	/**
	 * AJAX: background render-queue state for dashboard polling.
	 *
	 * @return void
	 */
	public function ajax_render_scan_status() {
		check_ajax_referer( 'scorefix_render_status', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Forbidden' ), 403 );
		}
		if ( RenderScanQueue::is_background_scan_running() ) {
			RenderScanQueue::process_tick( 1 );
		}
		wp_send_json_success( RenderScanQueue::get_background_scan_state() );
	}

	/**
	 * Enqueue admin assets on our page only.
	 *
	 * @param string $hook_suffix Current hook.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( 'toplevel_page_scorefix' !== $hook_suffix ) {
			return;
		}
		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style(
			'scorefix-admin',
			SCOREFIX_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			SCOREFIX_VERSION
		);

		wp_enqueue_script(
			'scorefix-admin-dashboard',
			SCOREFIX_PLUGIN_URL . 'assets/js/admin-dashboard.js',
			array(),
			SCOREFIX_VERSION,
			true
		);
		wp_localize_script(
			'scorefix-admin-dashboard',
			'scorefixDashboard',
			array(
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( 'scorefix_render_status' ),
				'renderScan'      => RenderScanQueue::get_background_scan_state(),
				'renderCountTpl'  => __( '%1$d of %2$d rendered URLs processed', 'scorefix' ),
			)
		);
	}

	/**
	 * Render dashboard.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$scan    = Scanner::get_last_scan();
		$score   = is_array( $scan ) && isset( $scan['score'] ) ? (int) $scan['score'] : null;
		$issues  = is_array( $scan ) && isset( $scan['issues'] ) && is_array( $scan['issues'] ) ? $scan['issues'] : array();
		$scanned = is_array( $scan ) && isset( $scan['scanned_at'] ) ? $scan['scanned_at'] : '';

		$scorefix_settings = get_option( 'scorefix_settings', array() );
		$fixes_on          = is_array( $scorefix_settings ) && ! empty( $scorefix_settings['fixes_enabled'] );

		$metrics                = DashboardMetrics::for_dashboard( $scan );
		$show_metric_trend_hint = DashboardMetrics::should_show_next_scan_hint( $scan );

		$notice = '';
		$scorefix_scan_q = filter_input( INPUT_GET, 'scorefix_scan', FILTER_UNSAFE_RAW );
		if ( null !== $scorefix_scan_q && false !== $scorefix_scan_q && 'done' === sanitize_key( wp_unslash( $scorefix_scan_q ) ) ) {
			$notice = 'scan_done';
		}
		$scorefix_fixes_q = filter_input( INPUT_GET, 'scorefix_fixes', FILTER_UNSAFE_RAW );
		if ( null !== $scorefix_fixes_q && false !== $scorefix_fixes_q ) {
			$notice = 'on' === sanitize_key( wp_unslash( $scorefix_fixes_q ) ) ? 'fixes_on' : 'fixes_off';
		}
		$scorefix_reminders_q = filter_input( INPUT_GET, 'scorefix_reminders', FILTER_UNSAFE_RAW );
		if ( null !== $scorefix_reminders_q && false !== $scorefix_reminders_q && 'saved' === sanitize_key( wp_unslash( $scorefix_reminders_q ) ) ) {
			$notice = 'reminders_saved';
		}
		$scorefix_meta_q = filter_input( INPUT_GET, 'scorefix_meta', FILTER_UNSAFE_RAW );
		if ( null !== $scorefix_meta_q && false !== $scorefix_meta_q && 'saved' === sanitize_key( wp_unslash( $scorefix_meta_q ) ) ) {
			$notice = 'meta_saved';
		}

		$perf_err  = isset( $metrics['active_errors']['value'] ) && null !== $metrics['active_errors']['value'] ? (int) $metrics['active_errors']['value'] : 0;
		$perf_warn = isset( $metrics['warnings']['value'] ) && null !== $metrics['warnings']['value'] ? (int) $metrics['warnings']['value'] : 0;
		$perf_copy = self::performance_card_copy( $score, count( $issues ), $perf_err, $perf_warn );

		if ( ! is_array( $scorefix_settings ) ) {
			$scorefix_settings = array();
		}

		$scorefix_issues_view = self::build_issues_table_view( $issues );

		$render_scan_state = RenderScanQueue::get_background_scan_state();

		$comparison = ( is_array( $scan ) && isset( $scan['comparison'] ) && is_array( $scan['comparison'] ) )
			? $scan['comparison']
			: array();
		$impact_estimate     = ConversionImpactEstimate::for_last_scan( $scan );
		$score_history       = ScoreHistory::get_entries();
		$deferred_meta       = DeferredScanScheduler::get_pending_meta();
		$last_settings_event = DeferredScanScheduler::get_last_settings_event();

		include SCOREFIX_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	/**
	 * Label for a deferred-scan reason slug (fixes / SEO).
	 *
	 * @param string $slug Reason key.
	 * @return string
	 */
	public static function settings_impact_reason_label( $slug ) {
		$slug = sanitize_key( (string) $slug );
		switch ( $slug ) {
			case 'fixes_on':
				return __( 'Automatic fixes enabled', 'scorefix' );
			case 'fixes_off':
				return __( 'Automatic fixes disabled', 'scorefix' );
			case 'meta_description':
				return __( 'SEO meta description setting saved', 'scorefix' );
			default:
				return __( 'Settings updated', 'scorefix' );
		}
	}

	/**
	 * Short label for score history row trigger.
	 *
	 * @param string $slug Trigger slug.
	 * @return string
	 */
	public static function score_history_trigger_label( $slug ) {
		$slug = sanitize_key( (string) $slug );
		$map  = array(
			'manual'                => __( 'Manual scan', 'scorefix' ),
			'after_settings_change' => __( 'Validation after settings', 'scorefix' ),
			'render_urls_merged'    => __( 'Rendered URLs merged', 'scorefix' ),
		);
		return isset( $map[ $slug ] ) ? $map[ $slug ] : $slug;
	}

	/**
	 * Headline and subcopy for the global performance card.
	 *
	 * @param int|null $score          Score 0–100 or null if no scan.
	 * @param int      $total_issues   Count of issues in last scan.
	 * @param int      $error_count    High-severity count.
	 * @param int      $warning_count  Medium-severity count.
	 * @return array{headline: string, sub: string}
	 */
	public static function performance_card_copy( $score, $total_issues, $error_count, $warning_count ) {
		if ( null === $score ) {
			return array(
				'headline' => __( 'Run your first scan to see site health', 'scorefix' ),
				'sub'      => __( 'ScoreFix will analyze published content and list fixes you can apply without editing code.', 'scorefix' ),
			);
		}

		if ( $score < 50 ) {
			$headline = __( 'Your site health needs immediate attention', 'scorefix' );
		} elseif ( $score < 80 ) {
			$headline = __( 'Your site health is improving', 'scorefix' );
		} else {
			$headline = __( 'Your site health looks strong', 'scorefix' );
		}

		if ( $total_issues <= 0 ) {
			$sub = __( 'No issues were detected in the last scan. Keep running scans as you publish new content.', 'scorefix' );
		} else {
			$sub = sprintf(
				/* translators: 1: total issues, 2: active errors, 3: warnings */
				__( 'We identified %1$d high-impact issues in the last scan — %2$d active errors and %3$d warnings.', 'scorefix' ),
				$total_issues,
				$error_count,
				$warning_count
			);
		}

		return array(
			'headline' => $headline,
			'sub'      => $sub,
		);
	}

	/**
	 * Donut chart color band: red (0–49), orange (50–79), green (80–100).
	 *
	 * @param int|null $score Score 0–100 or null if no scan.
	 * @return string|null    One of bad|warn|good, or null when no score.
	 */
	public static function donut_score_tone_slug( $score ) {
		if ( null === $score ) {
			return null;
		}
		$s = max( 0, min( 100, (int) $score ) );
		if ( $s < 50 ) {
			return 'bad';
		}
		if ( $s < 80 ) {
			return 'warn';
		}
		return 'good';
	}

	/**
	 * Human-readable issue label and business hint.
	 *
	 * @param array<string, mixed> $issue Issue row.
	 * @return array{0: string, 1: string} Title and description.
	 */
	public static function describe_issue( array $issue ) {
		$type = isset( $issue['type'] ) ? (string) $issue['type'] : '';

		switch ( $type ) {
			case 'image_no_alt':
				return array(
					__( 'Image missing description (ALT)', 'scorefix' ),
					__( 'This issue may reduce conversions: search engines and assistive tech rely on image descriptions.', 'scorefix' ),
				);
			case 'link_no_text':
				return array(
					__( 'Link without visible text', 'scorefix' ),
					__( 'Fix this to improve readability and clicks — unclear links hurt trust and Lighthouse.', 'scorefix' ),
				);
			case 'button_no_text':
				return array(
					__( 'Button without a clear label', 'scorefix' ),
					__( 'This issue may reduce conversions: people may not understand what happens when they tap.', 'scorefix' ),
				);
			case 'input_no_label':
				$input_t = isset( $issue['input_type'] ) ? sanitize_key( (string) $issue['input_type'] ) : '';
				$title   = __( 'Form field without a label', 'scorefix' );
				if ( '' !== $input_t ) {
					$title = sprintf(
						/* translators: %s: HTML input type, e.g. email */
						__( 'Form field without a label (%s)', 'scorefix' ),
						$input_t
					);
				}
				return array(
					$title,
					__( 'Fix this to improve readability and form completion — unclear fields increase abandonment.', 'scorefix' ),
				);
			case 'contrast_risk':
				return self::describe_contrast_issue( $issue );
			default:
				$g = IssueGlossary::get_entry( $type );
				return array( $g['title'], $g['business'] );
		}
	}

	/**
	 * Labels for contrast_risk hints from ContrastStyleAnalyzer.
	 *
	 * @param array<string, mixed> $issue Issue row.
	 * @return array{0: string, 1: string}
	 */
	protected static function describe_contrast_issue( array $issue ) {
		$hint  = isset( $issue['hint'] ) ? (string) $issue['hint'] : '';
		$ratio = isset( $issue['ratio'] ) ? (float) $issue['ratio'] : null;
		$ratio_txt = ( null !== $ratio && $ratio > 0 )
			/* translators: %s: contrast ratio like 3.2 */
			? sprintf( __( 'Rough contrast is about %s:1; for normal text, 4.5:1 or higher is usually better.', 'scorefix' ), (string) $ratio )
			: '';

		switch ( $hint ) {
			case 'same_color':
				return array(
					__( 'Text and background look the same', 'scorefix' ),
					__( 'Readers may not see this text. In the editor, change the text color or the background so they are clearly different.', 'scorefix' )
					. ( $ratio_txt ? ' ' . $ratio_txt : '' ),
				);
			case 'low_ratio':
				$desc = __( 'The colors are too close together, so the text can be tiring or hard to read. Try a darker text color or a lighter (or stronger) background.', 'scorefix' );
				if ( $ratio_txt ) {
					$desc .= ' ' . $ratio_txt;
				}
				if ( ! empty( $issue['detail'] ) ) {
					$desc .= ' ' . sprintf(
						/* translators: %s: e.g. rgb(128,128,128) / rgb(127,127,127) */
						__( 'Colors we compared: %s.', 'scorefix' ),
						(string) $issue['detail']
					);
				}
				return array(
					__( 'Text may be hard to read', 'scorefix' ),
					$desc,
				);
			case 'low_contrast_assumed_page':
				$desc = __( 'This text may look weak on a white page. Darken the text a bit, or give it a soft background, so it stands out.', 'scorefix' );
				if ( $ratio_txt ) {
					$desc .= ' ' . $ratio_txt;
				}
				return array(
					__( 'Text may blend into the page', 'scorefix' ),
					$desc,
				);
			default:
				$fallback = __( 'Readers may struggle with this text on some screens. Adjust colors in the editor for clearer contrast.', 'scorefix' );
				if ( $ratio_txt ) {
					$fallback .= ' ' . $ratio_txt;
				}
				return array(
					__( 'Possible contrast problem', 'scorefix' ),
					$fallback,
				);
		}
	}

	/**
	 * View model for the issues table: filtering, pagination, counts.
	 *
	 * @param array<int, mixed> $issues Raw issues from last scan.
	 * @return array<string, mixed>
	 */
	public static function build_issues_table_view( array $issues ) {
		$per_page = (int) apply_filters( 'scorefix_issues_per_page', 10 );
		$per_page = max( 1, min( 100, $per_page ) );

		$raw = array_values(
			array_filter(
				$issues,
				static function ( $row ) {
					return is_array( $row );
				}
			)
		);

		$filter        = self::get_issues_severity_filter_from_request();
		$filter_family = self::get_issues_family_filter_from_request();

		$count_error   = 0;
		$count_warning = 0;
		foreach ( $raw as $iss ) {
			$s = isset( $iss['severity'] ) ? (string) $iss['severity'] : '';
			if ( ScanComparison::SEVERITY_ERROR === $s ) {
				++$count_error;
			} elseif ( ScanComparison::SEVERITY_WARNING === $s || 'low' === $s ) {
				++$count_warning;
			}
		}

		$by_severity_only = self::filter_issues_for_table( $raw, $filter, '' );
		$family_counts   = self::count_issues_by_family( $by_severity_only );

		$filtered       = self::filter_issues_for_table( $raw, $filter, $filter_family );
		$total_filtered = count( $filtered );

		$page_q  = filter_input( INPUT_GET, 'sf_issues_page', FILTER_VALIDATE_INT );
		$current = ( is_int( $page_q ) && $page_q > 0 ) ? $page_q : 1;

		$total_pages = max( 1, (int) ceil( $total_filtered / $per_page ) );
		if ( $current > $total_pages ) {
			$current = $total_pages;
		}
		$offset = ( $current - 1 ) * $per_page;
		$items  = array_slice( $filtered, $offset, $per_page );

		$pagination = '';
		if ( $total_pages > 1 ) {
			$pagination = self::issues_paginate_links( $current, $total_pages, $filter, $filter_family );
		}

		return array(
			'items'           => $items,
			'filter'          => $filter,
			'filter_family'   => $filter_family,
			'family_counts'   => $family_counts,
			'current_page'    => $current,
			'per_page'        => $per_page,
			'total_filtered'  => $total_filtered,
			'total_all'       => count( $raw ),
			'total_pages'     => $total_pages,
			'pagination_html' => $pagination,
			'count_error'     => $count_error,
			'count_warning'   => $count_warning,
			'display_from'    => $total_filtered > 0 ? $offset + 1 : 0,
			'display_to'      => $total_filtered > 0 ? min( $offset + $per_page, $total_filtered ) : 0,
		);
	}

	/**
	 * Active severity tab from request.
	 *
	 * @return string '', 'error', or 'warning'.
	 */
	public static function get_issues_severity_filter_from_request() {
		$filter_q = filter_input( INPUT_GET, 'sf_issue_filter', FILTER_UNSAFE_RAW );
		if ( null === $filter_q || false === $filter_q ) {
			return '';
		}
		$k = sanitize_key( wp_unslash( $filter_q ) );
		return in_array( $k, array( 'error', 'warning' ), true ) ? $k : '';
	}

	/**
	 * Allowed issue family filter slugs (matches issue_family_slug()).
	 *
	 * @return array<int, string>
	 */
	public static function issue_family_filter_slugs() {
		return array( 'seo', 'performance', 'accessibility', 'other' );
	}

	/**
	 * Active family tab from request.
	 *
	 * @return string '', or a slug from issue_family_filter_slugs().
	 */
	public static function get_issues_family_filter_from_request() {
		$q = filter_input( INPUT_GET, 'sf_issue_family', FILTER_UNSAFE_RAW );
		if ( null === $q || false === $q ) {
			return '';
		}
		$k = sanitize_key( wp_unslash( $q ) );
		return in_array( $k, self::issue_family_filter_slugs(), true ) ? $k : '';
	}

	/**
	 * @param array<int, array<string, mixed>> $issues Valid issue rows (already severity-filtered when building family tab counts).
	 * @return array<string, int> Counts keyed by family slug.
	 */
	protected static function count_issues_by_family( array $issues ) {
		$counts = array_fill_keys( self::issue_family_filter_slugs(), 0 );
		foreach ( $issues as $issue ) {
			$slug = self::issue_family_slug( $issue );
			if ( isset( $counts[ $slug ] ) ) {
				++$counts[ $slug ];
			} else {
				++$counts['other'];
			}
		}
		return $counts;
	}

	/**
	 * @param array<int, array<string, mixed>> $issues Valid issue rows.
	 * @param string                             $severity_filter '', 'error', or 'warning'.
	 * @param string                             $family_filter   '', or slug from issue_family_filter_slugs().
	 * @return array<int, array<string, mixed>>
	 */
	public static function filter_issues_for_table( array $issues, $severity_filter, $family_filter = '' ) {
		$out = array();
		foreach ( $issues as $issue ) {
			if ( ! self::issue_matches_severity_filter( $issue, $severity_filter ) ) {
				continue;
			}
			if ( ! self::issue_matches_family_filter( $issue, $family_filter ) ) {
				continue;
			}
			$out[] = $issue;
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $issue           Issue row.
	 * @param string               $severity_filter '', 'error', or 'warning'.
	 * @return bool
	 */
	protected static function issue_matches_severity_filter( array $issue, $severity_filter ) {
		if ( '' === $severity_filter ) {
			return true;
		}
		$s = isset( $issue['severity'] ) ? (string) $issue['severity'] : '';
		if ( 'error' === $severity_filter ) {
			return ScanComparison::SEVERITY_ERROR === $s;
		}
		if ( 'warning' === $severity_filter ) {
			return ScanComparison::SEVERITY_WARNING === $s || 'low' === $s;
		}
		return true;
	}

	/**
	 * @param array<string, mixed> $issue         Issue row.
	 * @param string               $family_filter '', or family slug.
	 * @return bool
	 */
	protected static function issue_matches_family_filter( array $issue, $family_filter ) {
		if ( '' === $family_filter ) {
			return true;
		}
		return self::issue_family_slug( $issue ) === $family_filter;
	}

	/**
	 * Pagination markup for the issues table.
	 *
	 * @param int    $current     Current page (1-based).
	 * @param int    $total_pages Total pages.
	 * @param string $filter      Active severity filter slug.
	 * @param string $family      Active family filter slug.
	 * @return string
	 */
	public static function issues_paginate_links( $current, $total_pages, $filter, $family = '' ) {
		$base = admin_url( 'admin.php?page=scorefix' );
		if ( '' !== $filter ) {
			$base = add_query_arg( 'sf_issue_filter', $filter, $base );
		}
		if ( '' !== $family ) {
			$base = add_query_arg( 'sf_issue_family', $family, $base );
		}
		$keys = array( 'scorefix_scan', 'scorefix_fixes', 'scorefix_reminders' );
		foreach ( $keys as $key ) {
			$val = filter_input( INPUT_GET, $key, FILTER_UNSAFE_RAW );
			if ( null !== $val && false !== $val && '' !== (string) $val ) {
				$base = add_query_arg( $key, sanitize_text_field( wp_unslash( $val ) ), $base );
			}
		}
		// paginate_links() requires a literal %#% placeholder; add_query_arg() would encode it.
		$base .= ( false !== strpos( $base, '?' ) ? '&' : '?' ) . 'sf_issues_page=%#%';

		return (string) paginate_links(
			array(
				'base'      => $base,
				'format'    => '',
				'current'   => max( 1, (int) $current ),
				'total'     => max( 1, (int) $total_pages ),
				'type'      => 'plain',
				'prev_text' => __( '&laquo; Previous', 'scorefix' ),
				'next_text' => __( 'Next &raquo;', 'scorefix' ),
			)
		);
	}

	/**
	 * Severity tone for UI (matches metric chip themes).
	 *
	 * @param array<string, mixed> $issue Issue row.
	 * @return string error|warning
	 */
	public static function issue_severity_tone( array $issue ) {
		$s = isset( $issue['severity'] ) ? (string) $issue['severity'] : '';
		if ( ScanComparison::SEVERITY_ERROR === $s ) {
			return 'error';
		}
		return 'warning';
	}

	/**
	 * @param array<string, mixed> $issue Issue row.
	 * @return string
	 */
	public static function issue_severity_label( array $issue ) {
		return 'error' === self::issue_severity_tone( $issue )
			? __( 'Error', 'scorefix' )
			: __( 'Warning', 'scorefix' );
	}

	/**
	 * Human-readable source of the issue.
	 *
	 * @param array<string, mixed> $issue Issue row.
	 * @return string
	 */
	public static function issue_context_label( array $issue ) {
		$src = isset( $issue['source'] ) ? sanitize_key( (string) $issue['source'] ) : '';
		if ( 'attachment' === $src ) {
			return __( 'Media library', 'scorefix' );
		}
		if ( 'rendered_url' === $src ) {
			return __( 'Rendered page (public HTML)', 'scorefix' );
		}
		$ctx = isset( $issue['context'] ) ? sanitize_text_field( (string) $issue['context'] ) : '';
		if ( '' === $ctx ) {
			return __( 'Post / page content', 'scorefix' );
		}
		if ( 'content' === $ctx ) {
			return __( 'Post / page content', 'scorefix' );
		}
		if ( 'media_library' === $ctx ) {
			return __( 'Media library', 'scorefix' );
		}
		if ( 'performance' === $ctx ) {
			return __( 'Performance (HTML heuristic)', 'scorefix' );
		}
		return $ctx;
	}

	/**
	 * Primary line for the “Where” column: captured URL for rendered pass, post/media title for stored content.
	 *
	 * @param array<string, mixed> $issue Issue row.
	 * @return string
	 */
	public static function issue_where_primary_label( array $issue ) {
		$url = isset( $issue['capture_url'] ) ? esc_url_raw( (string) $issue['capture_url'] ) : '';
		if ( '' !== $url ) {
			$max = (int) apply_filters( 'scorefix_issue_where_url_max_length', 72, $issue );
			$max = max( 20, min( 200, $max ) );
			if ( strlen( $url ) > $max ) {
				return substr( $url, 0, $max - 3 ) . '...';
			}
			return $url;
		}
		$pid = isset( $issue['post_id'] ) ? (int) $issue['post_id'] : 0;
		if ( $pid > 0 ) {
			$t = get_the_title( $pid );
			if ( is_string( $t ) && '' !== $t ) {
				return $t;
			}
		}
		return __( '(No title)', 'scorefix' );
	}

	/**
	 * Whether to show the post/media ID under the primary “Where” line (not for rendered URL rows).
	 *
	 * @param array<string, mixed> $issue Issue row.
	 * @return bool
	 */
	public static function issue_where_show_post_id( array $issue ) {
		if ( ! empty( $issue['capture_url'] ) ) {
			return false;
		}
		$pid = isset( $issue['post_id'] ) ? (int) $issue['post_id'] : 0;
		return $pid > 0;
	}

	/**
	 * Issue family slug for UI (SEO, performance, accessibility).
	 *
	 * @param array<string, mixed> $issue Issue row.
	 * @return string seo|performance|accessibility|other
	 */
	public static function issue_family_slug( array $issue ) {
		$resolved = self::resolve_issue_family_slug( $issue );
		return (string) apply_filters( 'scorefix_issue_family_slug', $resolved, $issue );
	}

	/**
	 * Localized short label for the family badge.
	 *
	 * @param array<string, mixed> $issue Issue row.
	 * @return string
	 */
	public static function issue_family_label( array $issue ) {
		return self::issue_family_label_for_slug( self::issue_family_slug( $issue ), $issue );
	}

	/**
	 * Slug + label for the issues table (single resolution of filters).
	 *
	 * @param array<string, mixed> $issue Issue row.
	 * @return array{slug: string, label: string}
	 */
	public static function issue_family_display( array $issue ) {
		$slug = self::issue_family_slug( $issue );
		return array(
			'slug'  => $slug,
			'label' => self::issue_family_label_for_slug( $slug, $issue ),
		);
	}

	/**
	 * Localized label for a family filter tab (issue-independent).
	 *
	 * @param string $slug Family slug.
	 * @return string
	 */
	public static function issue_family_filter_tab_label( $slug ) {
		return self::issue_family_label_for_slug( sanitize_key( (string) $slug ), array() );
	}

	/**
	 * @param string               $slug  Family slug.
	 * @param array<string, mixed> $issue Issue row (for filters).
	 * @return string
	 */
	protected static function issue_family_label_for_slug( $slug, array $issue = array() ) {
		$slug = sanitize_key( (string) $slug );
		$labels = array(
			'seo'           => __( 'SEO', 'scorefix' ),
			'performance'   => __( 'Performance', 'scorefix' ),
			'accessibility' => __( 'Accessibility', 'scorefix' ),
			'other'         => __( 'Other', 'scorefix' ),
		);
		if ( isset( $labels[ $slug ] ) ) {
			return $labels[ $slug ];
		}
		return (string) apply_filters( 'scorefix_issue_family_label', $labels['other'], $slug, $issue );
	}

	/**
	 * @param array<string, mixed> $issue Issue row.
	 * @return string
	 */
	protected static function resolve_issue_family_slug( array $issue ) {
		$type = isset( $issue['type'] ) ? sanitize_key( (string) $issue['type'] ) : '';
		if ( '' === $type ) {
			return 'other';
		}
		if ( 'link_generic_text' === $type ) {
			return 'seo';
		}
		if ( 0 === strpos( $type, 'seo_' ) ) {
			return 'seo';
		}
		if ( 0 === strpos( $type, 'perf_' ) ) {
			return 'performance';
		}
		return 'accessibility';
	}

	/**
	 * Technical preview lines for the issue (scanner fields only).
	 *
	 * @param array<string, mixed> $issue Issue row.
	 * @return array<int, array{key: string, label: string, value: string}>
	 */
	public static function issue_preview_fields( array $issue ) {
		$rows  = array();
		$itype = isset( $issue['type'] ) ? (string) $issue['type'] : '';

		if ( ! empty( $issue['source'] ) ) {
			$rows[] = array(
				'key'   => 'source',
				'label' => __( 'Scan source', 'scorefix' ),
				'value' => sanitize_key( (string) $issue['source'] ),
			);
		}
		if ( ! empty( $issue['src'] ) ) {
			$rows[] = array(
				'key'   => 'src',
				'label' => __( 'Image URL', 'scorefix' ),
				'value' => (string) $issue['src'],
			);
		}
		if ( ! empty( $issue['href'] ) ) {
			$rows[] = array(
				'key'   => 'href',
				'label' => __( 'Link URL', 'scorefix' ),
				'value' => (string) $issue['href'],
			);
		}
		if ( 'input_no_label' === $itype && ! empty( $issue['input_type'] ) ) {
			$rows[] = array(
				'key'   => 'input_type',
				'label' => __( 'Field type', 'scorefix' ),
				'value' => sanitize_key( (string) $issue['input_type'] ),
			);
		}
		if ( ! empty( $issue['style_snippet'] ) ) {
			$rows[] = array(
				'key'   => 'style_snippet',
				'label' => __( 'Inline style (excerpt)', 'scorefix' ),
				'value' => (string) $issue['style_snippet'],
			);
		}
		if ( isset( $issue['ratio'] ) && (float) $issue['ratio'] > 0 ) {
			$rows[] = array(
				'key'   => 'ratio',
				'label' => __( 'Contrast ratio (estimate)', 'scorefix' ),
				'value' => (string) (float) $issue['ratio'] . ':1',
			);
		}
		if ( ! empty( $issue['detail'] ) ) {
			$rows[] = array(
				'key'   => 'detail',
				'label' => __( 'Color detail', 'scorefix' ),
				'value' => (string) $issue['detail'],
			);
		}
		if ( ! empty( $issue['hint'] ) && 'contrast_risk' === $itype ) {
			$rows[] = array(
				'key'   => 'hint',
				'label' => __( 'Detection hint', 'scorefix' ),
				'value' => sanitize_text_field( (string) $issue['hint'] ),
			);
		}
		if ( ! empty( $issue['title'] ) && 'image_no_alt' === $itype ) {
			$rows[] = array(
				'key'   => 'title',
				'label' => __( 'Media title', 'scorefix' ),
				'value' => (string) $issue['title'],
			);
		}
		if ( 'heading_multiple_h1' === $itype && isset( $issue['h1_count'] ) ) {
			$rows[] = array(
				'key'   => 'h1_count',
				'label' => __( 'H1 count', 'scorefix' ),
				'value' => (string) (int) $issue['h1_count'],
			);
		}
		if ( 'heading_level_skip' === $itype ) {
			if ( ! empty( $issue['from_tag'] ) ) {
				$rows[] = array(
					'key'   => 'from_tag',
					'label' => __( 'Previous heading', 'scorefix' ),
					'value' => (string) $issue['from_tag'],
				);
			}
			if ( ! empty( $issue['to_tag'] ) ) {
				$rows[] = array(
					'key'   => 'to_tag',
					'label' => __( 'Skipped to', 'scorefix' ),
					'value' => (string) $issue['to_tag'],
				);
			}
		}
		if ( 'landmark_multiple_main' === $itype && isset( $issue['main_count'] ) ) {
			$rows[] = array(
				'key'   => 'main_count',
				'label' => __( 'Main regions', 'scorefix' ),
				'value' => (string) (int) $issue['main_count'],
			);
		}
		if ( 'landmark_nav_unnamed' === $itype ) {
			if ( isset( $issue['nav_total'] ) ) {
				$rows[] = array(
					'key'   => 'nav_total',
					'label' => __( 'Nav elements', 'scorefix' ),
					'value' => (string) (int) $issue['nav_total'],
				);
			}
			if ( isset( $issue['nav_unnamed'] ) ) {
				$rows[] = array(
					'key'   => 'nav_unnamed',
					'label' => __( 'Without accessible name', 'scorefix' ),
					'value' => (string) (int) $issue['nav_unnamed'],
				);
			}
		}
		if ( 'link_generic_text' === $itype && ! empty( $issue['link_text'] ) ) {
			$rows[] = array(
				'key'   => 'link_text',
				'label' => __( 'Link text', 'scorefix' ),
				'value' => (string) $issue['link_text'],
			);
		}
		if ( 'seo_thin_content' === $itype && isset( $issue['word_count'] ) ) {
			$rows[] = array(
				'key'   => 'word_count',
				'label' => __( 'Word count (body text)', 'scorefix' ),
				'value' => (string) (int) $issue['word_count'],
			);
		}
		if ( 'seo_few_internal_links' === $itype ) {
			if ( isset( $issue['word_count'] ) ) {
				$rows[] = array(
					'key'   => 'word_count',
					'label' => __( 'Word count (body text)', 'scorefix' ),
					'value' => (string) (int) $issue['word_count'],
				);
			}
			if ( isset( $issue['internal_link_count'] ) ) {
				$rows[] = array(
					'key'   => 'internal_link_count',
					'label' => __( 'Internal links (same site)', 'scorefix' ),
					'value' => (string) (int) $issue['internal_link_count'],
				);
			}
			if ( isset( $issue['external_link_count'] ) ) {
				$rows[] = array(
					'key'   => 'external_link_count',
					'label' => __( 'External links (other hosts)', 'scorefix' ),
					'value' => (string) (int) $issue['external_link_count'],
				);
			}
		}
		if ( 'seo_head_title_length' === $itype ) {
			if ( isset( $issue['title_length'] ) ) {
				$rows[] = array(
					'key'   => 'title_length',
					'label' => __( 'Title length (characters)', 'scorefix' ),
					'value' => (string) (int) $issue['title_length'],
				);
			}
			if ( isset( $issue['title_min'] ) ) {
				$rows[] = array(
					'key'   => 'title_min',
					'label' => __( 'Expected minimum', 'scorefix' ),
					'value' => (string) (int) $issue['title_min'],
				);
			}
			if ( isset( $issue['title_max'] ) ) {
				$rows[] = array(
					'key'   => 'title_max',
					'label' => __( 'Expected maximum', 'scorefix' ),
					'value' => (string) (int) $issue['title_max'],
				);
			}
		}
		if ( 'seo_head_robots_noindex' === $itype && ! empty( $issue['robots_content'] ) ) {
			$rows[] = array(
				'key'   => 'robots_content',
				'label' => __( 'Robots meta content', 'scorefix' ),
				'value' => (string) $issue['robots_content'],
			);
		}
		if ( 'seo_jsonld_invalid_json' === $itype ) {
			if ( isset( $issue['ld_json_block_index'] ) ) {
				$rows[] = array(
					'key'   => 'ld_json_block_index',
					'label' => __( 'JSON-LD block index (page order)', 'scorefix' ),
					'value' => (string) (int) $issue['ld_json_block_index'],
				);
			}
			if ( ! empty( $issue['json_error'] ) ) {
				$rows[] = array(
					'key'   => 'json_error',
					'label' => __( 'JSON error', 'scorefix' ),
					'value' => (string) $issue['json_error'],
				);
			}
		}
		if ( 'seo_jsonld_missing_expected_type' === $itype ) {
			if ( ! empty( $issue['expected_schema'] ) ) {
				$rows[] = array(
					'key'   => 'expected_schema',
					'label' => __( 'Expected schema.org type', 'scorefix' ),
					'value' => (string) $issue['expected_schema'],
				);
			}
			if ( ! empty( $issue['expect_reason'] ) ) {
				$rows[] = array(
					'key'   => 'expect_reason',
					'label' => __( 'Expectation context', 'scorefix' ),
					'value' => (string) $issue['expect_reason'],
				);
			}
		}
		if ( 'form_radio_group_no_legend' === $itype && ! empty( $issue['group_name'] ) ) {
			$rows[] = array(
				'key'   => 'group_name',
				'label' => __( 'Radio group name', 'scorefix' ),
				'value' => (string) $issue['group_name'],
			);
		}
		if ( 'form_required_no_error_hint' === $itype ) {
			if ( ! empty( $issue['control_tag'] ) ) {
				$rows[] = array(
					'key'   => 'control_tag',
					'label' => __( 'Element', 'scorefix' ),
					'value' => sanitize_key( (string) $issue['control_tag'] ),
				);
			}
			if ( ! empty( $issue['input_type'] ) ) {
				$rows[] = array(
					'key'   => 'input_type',
					'label' => __( 'Control type', 'scorefix' ),
					'value' => sanitize_key( (string) $issue['input_type'] ),
				);
			}
		}
		if ( 'form_autocomplete_missing' === $itype ) {
			if ( ! empty( $issue['input_type'] ) ) {
				$rows[] = array(
					'key'   => 'input_type',
					'label' => __( 'Control type', 'scorefix' ),
					'value' => sanitize_key( (string) $issue['input_type'] ),
				);
			}
			if ( ! empty( $issue['field_hint'] ) ) {
				$rows[] = array(
					'key'   => 'field_hint',
					'label' => __( 'Field id/name hint', 'scorefix' ),
					'value' => (string) $issue['field_hint'],
				);
			}
		}
		if ( isset( $issue['table_ordinal'] ) && ( 'table_missing_th' === $itype || 'table_missing_caption' === $itype ) ) {
			$rows[] = array(
				'key'   => 'table_ordinal',
				'label' => __( 'Table index', 'scorefix' ),
				'value' => (string) (int) $issue['table_ordinal'],
			);
		}
		if ( ! empty( $issue['capture_url'] ) ) {
			$rows[] = array(
				'key'   => 'capture_url',
				'label' => __( 'Scanned URL', 'scorefix' ),
				'value' => (string) $issue['capture_url'],
			);
		}
		if ( 'perf_many_external_scripts' === $itype ) {
			if ( isset( $issue['script_src_count'] ) ) {
				$rows[] = array(
					'key'   => 'script_src_count',
					'label' => __( 'External script tags', 'scorefix' ),
					'value' => (string) (int) $issue['script_src_count'],
				);
			}
			if ( isset( $issue['script_src_threshold'] ) ) {
				$rows[] = array(
					'key'   => 'script_src_threshold',
					'label' => __( 'Threshold used', 'scorefix' ),
					'value' => (string) (int) $issue['script_src_threshold'],
				);
			}
		}

		return $rows;
	}

	/**
	 * Default row actions (edit / view). Extensible via `scorefix_issue_actions`.
	 *
	 * @param array<string, mixed> $issue Issue row.
	 * @return array<string, array{label: string, url: string, title?: string, icon?: string, attrs?: array<string, string>}>
	 */
	public static function issue_row_actions( array $issue ) {
		$post_id = isset( $issue['post_id'] ) ? (int) $issue['post_id'] : 0;
		$actions = array();

		if ( ! empty( $issue['capture_url'] ) && is_string( $issue['capture_url'] ) ) {
			$capture = esc_url_raw( $issue['capture_url'] );
			if ( '' !== $capture ) {
				$actions['view_capture'] = array(
					'label' => __( 'Open scanned URL', 'scorefix' ),
					'title' => __( 'Open this URL on the live site (new tab)', 'scorefix' ),
					'icon'  => 'dashicons-admin-site-alt3',
					'url'   => $capture,
					'attrs' => array(
						'target' => '_blank',
						'rel'    => 'noopener noreferrer',
					),
				);
			}
		}

		if ( $post_id && current_user_can( 'edit_post', $post_id ) ) {
			$url = get_edit_post_link( $post_id, 'raw' );
			if ( is_string( $url ) && '' !== $url ) {
				$actions['edit'] = array(
					'label' => __( 'Edit', 'scorefix' ),
					'title' => __( 'Edit this post or page in the admin', 'scorefix' ),
					'icon'  => 'dashicons-edit',
					'url'   => $url,
				);
			}
		}

		if ( $post_id ) {
			$post = get_post( $post_id );
			if ( $post instanceof \WP_Post ) {
				if ( 'attachment' === $post->post_type ) {
					$file = wp_get_attachment_url( $post_id );
					if ( is_string( $file ) && '' !== $file ) {
						$actions['view_file'] = array(
							'label' => __( 'View file', 'scorefix' ),
							'title' => __( 'Open the media file in a new tab', 'scorefix' ),
							'icon'  => 'dashicons-format-image',
							'url'   => $file,
							'attrs' => array(
								'target' => '_blank',
								'rel'    => 'noopener noreferrer',
							),
						);
					}
				} elseif ( in_array( $post->post_status, array( 'publish', 'private' ), true ) ) {
					$url = get_permalink( $post_id );
					if ( is_string( $url ) && '' !== $url ) {
						$actions['view'] = array(
							'label' => __( 'View on site', 'scorefix' ),
							'title' => __( 'View this content on the live site (new tab)', 'scorefix' ),
							'icon'  => 'dashicons-visibility',
							'url'   => $url,
							'attrs' => array(
								'target' => '_blank',
								'rel'    => 'noopener noreferrer',
							),
						);
					}
				}
			}
		}

		/**
		 * Filter actions shown for an issue row in the dashboard table.
		 *
		 * Each action may include: label (required), url (required), title (optional tooltip, defaults to label),
		 * icon (optional Dashicons suffix e.g. dashicons-edit), attrs (optional link attributes).
		 *
		 * @param array<string, array<string, mixed>> $actions Associative list of action definitions.
		 * @param array<string, mixed>              $issue   Issue row from scanner.
		 */
		$filtered = apply_filters( 'scorefix_issue_actions', $actions, $issue );
		return is_array( $filtered ) ? $filtered : $actions;
	}

	/**
	 * URL for issues table severity filter tabs (preserves active family filter).
	 *
	 * @param string $filter '', 'error', or 'warning'.
	 * @return string
	 */
	public static function issues_filter_tab_url( $filter ) {
		$args = array_merge(
			array( 'page' => 'scorefix' ),
			self::issues_dashboard_preserve_get_args()
		);
		if ( '' !== $filter ) {
			$args['sf_issue_filter'] = $filter;
		}
		$fam = self::get_issues_family_filter_from_request();
		if ( '' !== $fam ) {
			$args['sf_issue_family'] = $fam;
		}
		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * URL for issues table category (family) filter tabs (preserves active severity filter).
	 *
	 * @param string $family '', or slug from issue_family_filter_slugs().
	 * @return string
	 */
	public static function issues_family_filter_tab_url( $family ) {
		$args = array_merge(
			array( 'page' => 'scorefix' ),
			self::issues_dashboard_preserve_get_args()
		);
		$sev = self::get_issues_severity_filter_from_request();
		if ( '' !== $sev ) {
			$args['sf_issue_filter'] = $sev;
		}
		if ( '' !== $family ) {
			$args['sf_issue_family'] = $family;
		}
		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Optional dashboard query args to carry across issue filter navigations.
	 *
	 * @return array<string, string>
	 */
	protected static function issues_dashboard_preserve_get_args() {
		$out   = array();
		$keys  = array( 'scorefix_scan', 'scorefix_fixes', 'scorefix_reminders' );
		foreach ( $keys as $key ) {
			$val = filter_input( INPUT_GET, $key, FILTER_UNSAFE_RAW );
			if ( null !== $val && false !== $val && '' !== (string) $val ) {
				$out[ $key ] = sanitize_text_field( wp_unslash( $val ) );
			}
		}
		return $out;
	}
}
