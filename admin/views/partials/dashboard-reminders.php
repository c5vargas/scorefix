<?php
/**
 * Scan reminders card (same visual language as Automation).
 *
 * @package ScoreFix
 *
 * @var array<string, mixed> $settings Plugin settings array.
 */

defined( 'ABSPATH' ) || exit;

use ScoreFix\Admin\ActionsController;

if ( ! is_array( $settings ) ) {
	$settings = array();
}

$scorefix_reminders_on      = ! empty( $settings['reminders_enabled'] );
$scorefix_reminder_freq     = ( isset( $settings['reminder_frequency'] ) && '6months' === $settings['reminder_frequency'] ) ? '6months' : '3months';
$scorefix_reminder_email_on = ! empty( $settings['reminder_email'] );
?>
<div class="scorefix-card scorefix-card--automation scorefix-card--reminders">
	<p class="scorefix-automation__title scorefix-reminders__title" role="heading" aria-level="2"><?php esc_html_e( 'Scan reminders', 'scorefix' ); ?></p>
	<p class="scorefix-reminders__lead scorefix-muted">
		<?php esc_html_e( 'Nudges in the dashboard and optional email when it is time to scan again.', 'scorefix' ); ?>
	</p>

	<form method="post" class="scorefix-reminders-form">
		<?php wp_nonce_field( ActionsController::ACTION_SAVE_REMINDERS ); ?>
		<input type="hidden" name="scorefix_action" value="<?php echo esc_attr( ActionsController::ACTION_SAVE_REMINDERS ); ?>" />

		<div class="scorefix-automation__panel scorefix-reminders__panel">
			<div class="scorefix-automation__panel-text">
				<strong class="scorefix-automation__panel-label"><?php esc_html_e( 'Periodic reminders', 'scorefix' ); ?></strong>
				<span class="scorefix-automation__panel-hint"><?php esc_html_e( 'Turn on to schedule nudges every few months.', 'scorefix' ); ?></span>
			</div>
			<label class="scorefix-reminders__control">
				<input type="checkbox" name="scorefix_reminders_enabled" value="1" class="scorefix-reminders__checkbox" <?php checked( $scorefix_reminders_on ); ?> />
				<span class="screen-reader-text"><?php esc_html_e( 'Enable periodic scan reminders', 'scorefix' ); ?></span>
			</label>
		</div>

		<div class="scorefix-automation__panel scorefix-reminders__panel">
			<div class="scorefix-automation__panel-text">
				<strong class="scorefix-automation__panel-label"><?php esc_html_e( 'Frequency', 'scorefix' ); ?></strong>
				<span class="scorefix-automation__panel-hint"><?php esc_html_e( 'How often we should remind you.', 'scorefix' ); ?></span>
			</div>
			<div class="scorefix-reminders__control">
				<select name="scorefix_reminder_frequency" id="scorefix_reminder_frequency" class="scorefix-reminders__select">
					<option value="3months" <?php selected( $scorefix_reminder_freq, '3months' ); ?>><?php esc_html_e( 'Every 3 months', 'scorefix' ); ?></option>
					<option value="6months" <?php selected( $scorefix_reminder_freq, '6months' ); ?>><?php esc_html_e( 'Every 6 months', 'scorefix' ); ?></option>
				</select>
			</div>
		</div>

		<div class="scorefix-automation__panel scorefix-reminders__panel">
			<div class="scorefix-automation__panel-text">
				<strong class="scorefix-automation__panel-label"><?php esc_html_e( 'Email', 'scorefix' ); ?></strong>
				<span class="scorefix-automation__panel-hint"><?php esc_html_e( 'Also notify the site admin email address.', 'scorefix' ); ?></span>
			</div>
			<label class="scorefix-reminders__control">
				<input type="checkbox" name="scorefix_reminder_email" value="1" class="scorefix-reminders__checkbox" <?php checked( $scorefix_reminder_email_on ); ?> />
				<span class="screen-reader-text"><?php esc_html_e( 'Also send an email to the site admin address', 'scorefix' ); ?></span>
			</label>
		</div>

		<p class="scorefix-automation__footer scorefix-muted scorefix-reminders__footer-note">
			<?php esc_html_e( 'Dismissing the admin notice or running a scan clears the reminder until the next interval.', 'scorefix' ); ?>
		</p>

		<div class="scorefix-reminders__submit">
			<button type="submit" class="button button-primary scorefix-reminders__save"><?php esc_html_e( 'Save reminder settings', 'scorefix' ); ?></button>
		</div>
	</form>
</div>
