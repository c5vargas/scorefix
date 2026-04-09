<?php
/**
 * View-ready metrics derived from last scan snapshot.
 *
 * @package ScoreFix
 */

namespace ScoreFix\Admin;

use ScoreFix\Scanner\ScanComparison;

defined( 'ABSPATH' ) || exit;

/**
 * Class DashboardMetrics
 */
class DashboardMetrics {

	/**
	 * Whether to show the “run another scan for trends” hint under metric cards.
	 *
	 * @param array<string, mixed>|null $scan Last scan option value.
	 * @return bool
	 */
	public static function should_show_next_scan_hint( $scan ) {
		if ( ! is_array( $scan ) || empty( $scan['scanned_at'] ) ) {
			return false;
		}
		if ( isset( $scan['comparison'] ) && is_array( $scan['comparison'] ) ) {
			return empty( $scan['comparison']['has_prior'] );
		}
		return true;
	}

	/**
	 * Build metric card data for the dashboard template.
	 *
	 * @param array<string, mixed>|null $scan Last scan option value.
	 * @return array<string, array<string, mixed>> Keys: active_errors, warnings, resolved.
	 */
	public static function for_dashboard( $scan ) {
		if ( ! is_array( $scan ) || ! isset( $scan['issues'] ) || ! is_array( $scan['issues'] ) ) {
			return self::empty_cards();
		}

		$issues = $scan['issues'];
		$comp   = isset( $scan['comparison'] ) && is_array( $scan['comparison'] )
			? $scan['comparison']
			: ScanComparison::build( false, array(), $issues );

		$has_prior       = ! empty( $comp['has_prior'] );
		$curr_err        = (int) ( $comp['current_errors'] ?? 0 );
		$curr_warn       = (int) ( $comp['current_warnings'] ?? 0 );
		$prior_err       = (int) ( $comp['prior_errors'] ?? 0 );
		$prior_warn      = (int) ( $comp['prior_warnings'] ?? 0 );
		$prior_total     = (int) ( $comp['prior_total'] ?? 0 );
		$resolved        = (int) ( $comp['resolved_count'] ?? 0 );

		return array(
			'active_errors' => self::bucket_metric( $has_prior, $curr_err, $prior_err ),
			'warnings'      => self::bucket_metric( $has_prior, $curr_warn, $prior_warn ),
			'resolved'      => self::resolved_metric( $has_prior, $resolved, $prior_total ),
		);
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	protected static function empty_cards() {
		$blank = array(
			'value'            => null,
			'has_prior'        => false,
			'show_trend'       => false,
			'trend_special'    => null,
			'trend_pct'        => null,
			'trend_good'       => null,
			'trend_direction'  => null,
		);
		return array(
			'active_errors' => $blank,
			'warnings'      => $blank,
			'resolved'      => $blank,
		);
	}

	/**
	 * Metric where lower current vs prior is better (errors, warnings).
	 *
	 * @param bool $has_prior Prior scan existed.
	 * @param int  $current   Current count.
	 * @param int  $prior     Prior count.
	 * @return array<string, mixed>
	 */
	protected static function bucket_metric( $has_prior, $current, $prior ) {
		$out = array(
			'value'           => $current,
			'has_prior'       => $has_prior,
			'show_trend'      => false,
			'trend_special'   => null,
			'trend_pct'       => null,
			'trend_good'      => null,
			'trend_direction' => null,
		);

		if ( ! $has_prior ) {
			return $out;
		}

		if ( 0 === $prior && 0 === $current ) {
			return $out;
		}

		if ( 0 === $prior && $current > 0 ) {
			$out['show_trend']    = true;
			$out['trend_special'] = 'new';
			$out['trend_good']    = false;
			return $out;
		}

		if ( $prior > 0 ) {
			$delta = $current - $prior;
			$pct   = ( $delta / (float) $prior ) * 100.0;
			$out['show_trend']       = true;
			$out['trend_pct']        = round( $pct, 1 );
			$out['trend_good']       = $delta < 0;
			$out['trend_direction']  = $delta > 0 ? 'up' : ( $delta < 0 ? 'down' : 'flat' );
			if ( 0 === $delta ) {
				$out['trend_direction'] = 'flat';
			}
		}

		return $out;
	}

	/**
	 * Resolved issues (signatures gone since prior scan).
	 *
	 * @param bool $has_prior   Prior scan existed.
	 * @param int  $resolved    Count resolved.
	 * @param int  $prior_total Prior issue rows count.
	 * @return array<string, mixed>
	 */
	protected static function resolved_metric( $has_prior, $resolved, $prior_total ) {
		$out = array(
			'value'           => $resolved,
			'has_prior'       => $has_prior,
			'show_trend'      => false,
			'trend_special'   => null,
			'trend_pct'       => null,
			'trend_good'      => null,
			'trend_direction' => null,
		);

		if ( ! $has_prior || $prior_total <= 0 ) {
			return $out;
		}

		$pct = ( $resolved / (float) $prior_total ) * 100.0;
		$out['show_trend']      = true;
		$out['trend_pct']       = round( $pct, 1 );
		$out['trend_good']      = $resolved > 0;
		$out['trend_direction'] = $resolved > 0 ? 'up' : 'flat';

		return $out;
	}

	/**
	 * Format a signed percentage for display (e.g. +4.2% / -12.0%).
	 *
	 * @param float $pct Raw percentage.
	 * @return string
	 */
	public static function format_signed_pct( $pct ) {
		return sprintf( '%+.1f%%', (float) $pct );
	}

	/**
	 * CSS classes for the trend row under a metric value.
	 *
	 * @param array<string, mixed> $m Single metric from for_dashboard().
	 * @return string
	 */
	public static function trend_row_classes( array $m ) {
		$classes = array( 'scorefix-metric__trend' );
		if ( empty( $m['show_trend'] ) ) {
			return implode( ' ', $classes );
		}
		if ( ! empty( $m['trend_special'] ) && 'new' === $m['trend_special'] ) {
			$classes[] = 'scorefix-metric__trend--bad';
			return implode( ' ', $classes );
		}
		if ( true === $m['trend_good'] ) {
			$classes[] = 'scorefix-metric__trend--good';
		} elseif ( false === $m['trend_good'] && isset( $m['trend_direction'] ) && 'flat' !== $m['trend_direction'] ) {
			$classes[] = 'scorefix-metric__trend--bad';
		} else {
			$classes[] = 'scorefix-metric__trend--neutral';
		}
		return implode( ' ', $classes );
	}

	/**
	 * Dashicons helper class for the trend icon.
	 *
	 * @param array<string, mixed> $m Single metric from for_dashboard().
	 * @return string
	 */
	public static function trend_icon_class( array $m ) {
		if ( empty( $m['show_trend'] ) ) {
			return '';
		}
		if ( ! empty( $m['trend_special'] ) && 'new' === $m['trend_special'] ) {
			return 'dashicons-chart-bar';
		}
		$dir = isset( $m['trend_direction'] ) ? (string) $m['trend_direction'] : 'flat';
		if ( 'up' === $dir ) {
			return 'dashicons-arrow-up-alt2';
		}
		if ( 'down' === $dir ) {
			return 'dashicons-arrow-down-alt2';
		}
		return 'dashicons-minus';
	}
}
