<?php
/**
 * Admin dashboard page.
 *
 * @package ScoreFix
 */

namespace ScoreFix\Admin;

use ScoreFix\Scanner\ScanComparison;
use ScoreFix\Scanner\Scanner;

defined( 'ABSPATH' ) || exit;

/**
 * Class DashboardPage
 */
class DashboardPage {

	/**
	 * Register top-level menu under Settings or Tools — use Settings submenu for simplicity.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_options_page(
			__( 'ScoreFix', 'scorefix' ),
			__( 'ScoreFix', 'scorefix' ),
			'manage_options',
			'scorefix',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue admin assets on our page only.
	 *
	 * @param string $hook_suffix Current hook.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( 'settings_page_scorefix' !== $hook_suffix ) {
			return;
		}
		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style(
			'scorefix-admin',
			SCOREFIX_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			SCOREFIX_VERSION
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

		$perf_err  = isset( $metrics['active_errors']['value'] ) && null !== $metrics['active_errors']['value'] ? (int) $metrics['active_errors']['value'] : 0;
		$perf_warn = isset( $metrics['warnings']['value'] ) && null !== $metrics['warnings']['value'] ? (int) $metrics['warnings']['value'] : 0;
		$perf_copy = self::performance_card_copy( $score, count( $issues ), $perf_err, $perf_warn );

		if ( ! is_array( $scorefix_settings ) ) {
			$scorefix_settings = array();
		}

		$scorefix_issues_view = self::build_issues_table_view( $issues );

		include SCOREFIX_PLUGIN_DIR . 'admin/views/dashboard.php';
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
				return array(
					__( 'Accessibility improvement', 'scorefix' ),
					__( 'Addressing this helps your Lighthouse accessibility score and user experience.', 'scorefix' ),
				);
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
		$per_page = (int) apply_filters( 'scorefix_issues_per_page', 20 );
		$per_page = max( 1, min( 100, $per_page ) );

		$raw = array_values(
			array_filter(
				$issues,
				static function ( $row ) {
					return is_array( $row );
				}
			)
		);

		$filter_q = filter_input( INPUT_GET, 'sf_issue_filter', FILTER_UNSAFE_RAW );
		$filter   = '';
		if ( null !== $filter_q && false !== $filter_q ) {
			$k = sanitize_key( wp_unslash( $filter_q ) );
			if ( in_array( $k, array( 'error', 'warning' ), true ) ) {
				$filter = $k;
			}
		}

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

		$filtered       = self::filter_issues_for_table( $raw, $filter );
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
			$pagination = self::issues_paginate_links( $current, $total_pages, $filter );
		}

		return array(
			'items'           => $items,
			'filter'          => $filter,
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
	 * @param array<int, array<string, mixed>> $issues Valid issue rows.
	 * @param string                             $filter '', 'error', or 'warning'.
	 * @return array<int, array<string, mixed>>
	 */
	public static function filter_issues_for_table( array $issues, $filter ) {
		if ( '' === $filter ) {
			return $issues;
		}
		$out = array();
		foreach ( $issues as $issue ) {
			$s = isset( $issue['severity'] ) ? (string) $issue['severity'] : '';
			if ( 'error' === $filter && ScanComparison::SEVERITY_ERROR === $s ) {
				$out[] = $issue;
			}
			if ( 'warning' === $filter && ( ScanComparison::SEVERITY_WARNING === $s || 'low' === $s ) ) {
				$out[] = $issue;
			}
		}
		return $out;
	}

	/**
	 * Pagination markup for the issues table.
	 *
	 * @param int    $current     Current page (1-based).
	 * @param int    $total_pages Total pages.
	 * @param string $filter      Active severity filter slug.
	 * @return string
	 */
	public static function issues_paginate_links( $current, $total_pages, $filter ) {
		$base = admin_url( 'options-general.php?page=scorefix' );
		if ( '' !== $filter ) {
			$base = add_query_arg( 'sf_issue_filter', $filter, $base );
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
		return $ctx;
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

		return $rows;
	}

	/**
	 * Default row actions (edit / view). Extensible via `scorefix_issue_actions`.
	 *
	 * @param array<string, mixed> $issue Issue row.
	 * @return array<string, array{label: string, url: string, attrs?: array<string, string>}>
	 */
	public static function issue_row_actions( array $issue ) {
		$post_id = isset( $issue['post_id'] ) ? (int) $issue['post_id'] : 0;
		$actions = array();

		if ( $post_id && current_user_can( 'edit_post', $post_id ) ) {
			$url = get_edit_post_link( $post_id, 'raw' );
			if ( is_string( $url ) && '' !== $url ) {
				$actions['edit'] = array(
					'label' => __( 'Edit', 'scorefix' ),
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
		 * @param array<string, array<string, mixed>> $actions Associative list of action definitions.
		 * @param array<string, mixed>              $issue   Issue row from scanner.
		 */
		$filtered = apply_filters( 'scorefix_issue_actions', $actions, $issue );
		return is_array( $filtered ) ? $filtered : $actions;
	}

	/**
	 * URL for issues table filter tabs.
	 *
	 * @param string $filter '', 'error', or 'warning'.
	 * @return string
	 */
	public static function issues_filter_tab_url( $filter ) {
		$args = array( 'page' => 'scorefix' );
		if ( '' !== $filter ) {
			$args['sf_issue_filter'] = $filter;
		}
		$keys = array( 'scorefix_scan', 'scorefix_fixes', 'scorefix_reminders' );
		foreach ( $keys as $key ) {
			$val = filter_input( INPUT_GET, $key, FILTER_UNSAFE_RAW );
			if ( null !== $val && false !== $val && '' !== (string) $val ) {
				$args[ $key ] = sanitize_text_field( wp_unslash( $val ) );
			}
		}
		return add_query_arg( $args, admin_url( 'options-general.php' ) );
	}
}
