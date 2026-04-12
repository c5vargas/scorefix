<?php
/**
 * Lightweight score timeline (last N completed scans / merge steps).
 *
 * @package ScoreFix
 */

namespace ScoreFix\Scanner;

defined( 'ABSPATH' ) || exit;

/**
 * Class ScoreHistory
 */
class ScoreHistory {

	const OPTION_KEY = 'scorefix_score_history';

	const MAX_ENTRIES = 24;

	/** Dashboard timeline: newest-first row cap (option may store up to MAX_ENTRIES). */
	const DISPLAY_ENTRIES = 10;

	/**
	 * Append a row from a full scan snapshot (sync phase).
	 *
	 * @param array<string, mixed> $snapshot Scanner::OPTION_LAST_SCAN payload.
	 * @return void
	 */
	public static function record_from_snapshot( array $snapshot ) {
		if ( ! isset( $snapshot['score'], $snapshot['scanned_at'] ) ) {
			return;
		}
		$trigger = isset( $snapshot['scan_trigger'] ) ? sanitize_key( (string) $snapshot['scan_trigger'] ) : 'scan';
		self::push(
			array(
				'score'   => (int) $snapshot['score'],
				'at'      => (string) $snapshot['scanned_at'],
				'trigger' => '' !== $trigger ? $trigger : 'scan',
			)
		);
	}

	/**
	 * Record a score update that does not replace the main snapshot flow (e.g. rendered URLs merged).
	 *
	 * @param int         $score   Score 0–100.
	 * @param string      $trigger Short slug.
	 * @param string|null $at      ISO datetime; defaults to now.
	 * @return void
	 */
	public static function record_event( $score, $trigger, $at = null ) {
		$trigger = sanitize_key( (string) $trigger );
		if ( '' === $trigger ) {
			$trigger = 'event';
		}
		$iso = $at ? (string) $at : gmdate( 'c' );
		self::push(
			array(
				'score'   => (int) $score,
				'at'      => $iso,
				'trigger' => $trigger,
			)
		);
	}

	/**
	 * Newest-first entries for the dashboard (last DISPLAY_ENTRIES only).
	 *
	 * @return array<int, array{score: int, at: string, trigger: string}>
	 */
	public static function get_entries() {
		$raw = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out = array();
		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['score'], $row['at'] ) ) {
				continue;
			}
			$out[] = array(
				'score'   => (int) $row['score'],
				'at'      => (string) $row['at'],
				'trigger' => isset( $row['trigger'] ) ? sanitize_key( (string) $row['trigger'] ) : 'scan',
			);
		}
		$n = max( 1, (int) self::DISPLAY_ENTRIES );
		$tail = array_slice( $out, -$n );

		return array_reverse( $tail );
	}

	/**
	 * @param array{score: int, at: string, trigger: string} $row Row.
	 * @return void
	 */
	protected static function push( array $row ) {
		$list = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $list ) ) {
			$list = array();
		}
		$list[] = $row;
		if ( count( $list ) > self::MAX_ENTRIES ) {
			$list = array_slice( $list, -self::MAX_ENTRIES );
		}
		update_option( self::OPTION_KEY, $list, false );
	}
}
