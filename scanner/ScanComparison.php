<?php
/**
 * Compare two scan issue lists for dashboard metrics.
 *
 * @package ScoreFix
 */

namespace ScoreFix\Scanner;

defined( 'ABSPATH' ) || exit;

/**
 * Class ScanComparison
 */
class ScanComparison {

	/**
	 * Severities counted as "active errors" in the UI.
	 */
	const SEVERITY_ERROR = 'high';

	/**
	 * Severities counted as "warnings" in the UI.
	 */
	const SEVERITY_WARNING = 'medium';

	/**
	 * Build comparison payload for persistence and display.
	 *
	 * @param bool                      $had_prior_scan Whether a snapshot existed before this run.
	 * @param array<int, array<string, mixed>> $prev_issues Issues from the previous snapshot.
	 * @param array<int, array<string, mixed>> $curr_issues Issues from the current scan.
	 * @return array<string, mixed>
	 */
	public static function build( $had_prior_scan, array $prev_issues, array $curr_issues ) {
		$curr_errors    = self::count_severity( $curr_issues, self::SEVERITY_ERROR );
		$curr_warnings  = self::count_severity( $curr_issues, self::SEVERITY_WARNING );
		$prior_errors   = self::count_severity( $prev_issues, self::SEVERITY_ERROR );
		$prior_warnings = self::count_severity( $prev_issues, self::SEVERITY_WARNING );
		$prior_total    = count( $prev_issues );

		$prev_sigs = self::signature_set( $prev_issues );
		$curr_sigs = self::signature_set( $curr_issues );
		$resolved  = 0;
		foreach ( array_keys( $prev_sigs ) as $sig ) {
			if ( ! isset( $curr_sigs[ $sig ] ) ) {
				++$resolved;
			}
		}

		return array(
			'has_prior'       => (bool) $had_prior_scan,
			'current_errors'  => $curr_errors,
			'current_warnings'=> $curr_warnings,
			'prior_errors'    => $prior_errors,
			'prior_warnings'  => $prior_warnings,
			'prior_total'     => $prior_total,
			'resolved_count'  => $resolved,
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $issues Issues.
	 * @param string                           $severity Severity slug.
	 * @return int
	 */
	protected static function count_severity( array $issues, $severity ) {
		$n = 0;
		foreach ( $issues as $issue ) {
			if ( ! is_array( $issue ) ) {
				continue;
			}
			$s = isset( $issue['severity'] ) ? (string) $issue['severity'] : '';
			if ( $s === $severity ) {
				++$n;
			}
			// Forward-compatible: treat future "low" like warnings in the UI bucket.
			if ( self::SEVERITY_WARNING === $severity && 'low' === $s ) {
				++$n;
			}
		}
		return $n;
	}

	/**
	 * Map signature => true for quick lookup.
	 *
	 * @param array<int, array<string, mixed>> $issues Issues.
	 * @return array<string, bool>
	 */
	protected static function signature_set( array $issues ) {
		$set = array();
		foreach ( $issues as $issue ) {
			if ( ! is_array( $issue ) ) {
				continue;
			}
			$set[ IssueSignature::from_issue( $issue ) ] = true;
		}
		return $set;
	}
}
