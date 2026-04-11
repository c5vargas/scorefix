<?php
/**
 * Schedule a follow-up scan after settings that affect scoring (no user API keys).
 *
 * @package ScoreFix
 */

namespace ScoreFix\Admin;

use ScoreFix\Scanner\Scanner;

defined( 'ABSPATH' ) || exit;

/**
 * Class DeferredScanScheduler
 */
class DeferredScanScheduler {

	const CRON_HOOK = 'scorefix_deferred_validation_scan';

	const OPTION_META = 'scorefix_deferred_scan_meta';

	const OPTION_LAST_EVENT = 'scorefix_last_settings_impact_event';

	/**
	 * Register cron callback.
	 *
	 * @param \ScoreFix\Core\Loader $loader Loader.
	 * @return void
	 */
	public static function register( $loader ) {
		$loader->add_action( self::CRON_HOOK, __CLASS__, 'run_scheduled_scan', 10, 0 );
	}

	/**
	 * Queue a single deferred scan (replaces any previous pending run).
	 *
	 * @param string $reason_key Short slug for admin display (e.g. fixes_on).
	 * @return void
	 */
	public static function schedule_validation_scan( $reason_key ) {
		$reason_key = sanitize_key( (string) $reason_key );
		if ( '' === $reason_key ) {
			$reason_key = 'settings';
		}

		wp_clear_scheduled_hook( self::CRON_HOOK );

		$delay = (int) apply_filters( 'scorefix_deferred_scan_delay_seconds', 120 );
		$delay = max( 45, $delay );
		$run_at = time() + $delay;

		wp_schedule_single_event( $run_at, self::CRON_HOOK );

		update_option(
			self::OPTION_META,
			array(
				'reason'       => $reason_key,
				'scheduled_at' => gmdate( 'c' ),
				'run_after'    => gmdate( 'c', $run_at ),
			),
			false
		);

		update_option(
			self::OPTION_LAST_EVENT,
			array(
				'reason' => $reason_key,
				'at'     => gmdate( 'c' ),
			),
			false
		);

		if ( function_exists( 'spawn_cron' ) ) {
			spawn_cron();
		}
	}

	/**
	 * Cancel pending deferred scan (e.g. user ran a manual scan first).
	 *
	 * @return void
	 */
	public static function cancel_pending() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
		delete_option( self::OPTION_META );
	}

	/**
	 * Cron: run validation scan after settings change.
	 *
	 * @return void
	 */
	public static function run_scheduled_scan() {
		delete_option( self::OPTION_META );

		$scanner = new Scanner();
		$scanner->run( array( 'trigger' => 'after_settings_change' ) );

		ReminderScheduler::clear_pending();
	}

	/**
	 * Whether a deferred scan is still scheduled.
	 *
	 * @return bool
	 */
	public static function is_pending() {
		return (bool) wp_next_scheduled( self::CRON_HOOK );
	}

	/**
	 * Meta for dashboard notice (pending scheduled scan).
	 *
	 * @return array<string, mixed>
	 */
	public static function get_pending_meta() {
		if ( ! self::is_pending() ) {
			return array();
		}
		$m = get_option( self::OPTION_META, array() );
		return is_array( $m ) ? $m : array();
	}

	/**
	 * Last settings change that requested validation (fixes / SEO toggle).
	 *
	 * @return array{reason: string, at: string}
	 */
	public static function get_last_settings_event() {
		$m = get_option( self::OPTION_LAST_EVENT, array() );
		if ( ! is_array( $m ) || empty( $m['at'] ) ) {
			return array(
				'reason' => '',
				'at'     => '',
			);
		}
		return array(
			'reason' => isset( $m['reason'] ) ? sanitize_key( (string) $m['reason'] ) : '',
			'at'     => (string) $m['at'],
		);
	}
}
