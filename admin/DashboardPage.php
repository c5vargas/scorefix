<?php
/**
 * Admin dashboard page.
 *
 * @package ScoreFix
 */

namespace ScoreFix\Admin;

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

		$settings = get_option( 'scorefix_settings', array() );
		$fixes_on = is_array( $settings ) && ! empty( $settings['fixes_enabled'] );

		$metrics                = DashboardMetrics::for_dashboard( $scan );
		$show_metric_trend_hint = DashboardMetrics::should_show_next_scan_hint( $scan );

		$notice = '';
		if ( isset( $_GET['scorefix_scan'] ) && 'done' === $_GET['scorefix_scan'] ) {
			$notice = 'scan_done';
		}
		if ( isset( $_GET['scorefix_fixes'] ) ) {
			$notice = 'on' === $_GET['scorefix_fixes'] ? 'fixes_on' : 'fixes_off';
		}

		$perf_err  = isset( $metrics['active_errors']['value'] ) && null !== $metrics['active_errors']['value'] ? (int) $metrics['active_errors']['value'] : 0;
		$perf_warn = isset( $metrics['warnings']['value'] ) && null !== $metrics['warnings']['value'] ? (int) $metrics['warnings']['value'] : 0;
		$perf_copy = self::performance_card_copy( $score, count( $issues ), $perf_err, $perf_warn );

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
				return array(
					__( 'Form field without a label', 'scorefix' ),
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
			? sprintf( __( 'Estimated contrast ratio: %s:1 (WCAG suggests at least 4.5:1 for normal text).', 'scorefix' ), (string) $ratio )
			: '';

		switch ( $hint ) {
			case 'same_color':
				return array(
					__( 'Contrast: text and background use the same color (inline)', 'scorefix' ),
					__( 'The text is invisible or nearly invisible. Change either the text color or the background in your editor.', 'scorefix' )
					. ( $ratio_txt ? ' ' . $ratio_txt : '' ),
				);
			case 'low_ratio':
				$desc = __( 'Foreground and background colors in the inline style likely fail WCAG contrast for normal text. Adjust colors in the block or custom CSS.', 'scorefix' );
				if ( $ratio_txt ) {
					$desc .= ' ' . $ratio_txt;
				}
				if ( ! empty( $issue['detail'] ) ) {
					$desc .= ' ' . sprintf(
						/* translators: %s: e.g. rgb(128,128,128) / rgb(127,127,127) */
						__( 'Colors checked: %s.', 'scorefix' ),
						(string) $issue['detail']
					);
				}
				return array(
					__( 'Contrast: low color contrast (inline styles)', 'scorefix' ),
					$desc,
				);
			case 'low_contrast_assumed_page':
				$desc = __( 'Text color is hard to read against a typical white page background (scanner assumes a white page when no solid background is set on this element). Set an explicit background or darken the text.', 'scorefix' );
				if ( $ratio_txt ) {
					$desc .= ' ' . $ratio_txt;
				}
				return array(
					__( 'Contrast: text may be hard to read on the page', 'scorefix' ),
					$desc,
				);
			default:
				$fallback = __( 'Fix this to improve readability — low contrast makes content harder to read on some screens.', 'scorefix' );
				if ( $ratio_txt ) {
					$fallback .= ' ' . $ratio_txt;
				}
				if ( ! empty( $issue['style_snippet'] ) ) {
					$fallback .= ' ' . sprintf(
						/* translators: %s: shortened style attribute */
						__( 'Style: %s', 'scorefix' ),
						(string) $issue['style_snippet']
					);
				}
				return array(
					__( 'Possible contrast issue (inline styles)', 'scorefix' ),
					$fallback,
				);
		}
	}
}
