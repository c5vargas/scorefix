<?php
/**
 * Admin POST actions: scan, toggle fixes.
 *
 * @package ScoreFix
 */

namespace ScoreFix\Admin;

use ScoreFix\Scanner\Scanner;

defined( 'ABSPATH' ) || exit;

/**
 * Class ActionsController
 */
class ActionsController {

	const ACTION_RUN_SCAN       = 'scorefix_run_scan';
	const ACTION_APPLY          = 'scorefix_apply_fixes';
	const ACTION_DISABLE        = 'scorefix_disable_fixes';
	const ACTION_SAVE_REMINDERS = 'scorefix_save_reminders';

	/**
	 * Handle admin actions.
	 *
	 * @return void
	 */
	public function handle_actions() {
		if ( ! isset( $_POST['scorefix_action'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$action = sanitize_key( wp_unslash( $_POST['scorefix_action'] ) );

		if ( self::ACTION_RUN_SCAN === $action ) {
			check_admin_referer( self::ACTION_RUN_SCAN );
			$scanner = new Scanner();
			$scanner->run();
			ReminderScheduler::clear_pending();
			$this->redirect_with_arg( 'scorefix_scan', 'done' );
		}

		if ( self::ACTION_APPLY === $action ) {
			check_admin_referer( self::ACTION_APPLY );
			$settings = get_option( 'scorefix_settings', array() );
			if ( ! is_array( $settings ) ) {
				$settings = array();
			}
			$settings['fixes_enabled'] = true;
			$settings['version']       = SCOREFIX_VERSION;
			update_option( 'scorefix_settings', $settings, false );
			$this->redirect_with_arg( 'scorefix_fixes', 'on' );
		}

		if ( self::ACTION_DISABLE === $action ) {
			check_admin_referer( self::ACTION_DISABLE );
			$settings = get_option( 'scorefix_settings', array() );
			if ( ! is_array( $settings ) ) {
				$settings = array();
			}
			$settings['fixes_enabled'] = false;
			update_option( 'scorefix_settings', $settings, false );
			$this->redirect_with_arg( 'scorefix_fixes', 'off' );
		}

		if ( self::ACTION_SAVE_REMINDERS === $action ) {
			check_admin_referer( self::ACTION_SAVE_REMINDERS );
			$settings = get_option( 'scorefix_settings', array() );
			if ( ! is_array( $settings ) ) {
				$settings = array();
			}
			$settings['reminders_enabled']  = isset( $_POST['scorefix_reminders_enabled'] );
			$freq_raw                       = isset( $_POST['scorefix_reminder_frequency'] ) ? sanitize_key( wp_unslash( $_POST['scorefix_reminder_frequency'] ) ) : '3months';
			$settings['reminder_frequency'] = ( '6months' === $freq_raw ) ? '6months' : '3months';
			$settings['reminder_email']     = isset( $_POST['scorefix_reminder_email'] );
			$settings['version']            = SCOREFIX_VERSION;
			update_option( 'scorefix_settings', $settings, false );
			ReminderScheduler::reschedule_from_settings();
			$this->redirect_with_arg( 'scorefix_reminders', 'saved' );
		}
	}

	/**
	 * Redirect back to dashboard with notice slug.
	 *
	 * @param string $key   Query key.
	 * @param string $value Query value.
	 * @return void
	 */
	protected function redirect_with_arg( $key, $value ) {
		$url = add_query_arg(
			array(
				'page' => 'scorefix',
				$key   => $value,
			),
			admin_url( 'options-general.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}
}
