<?php
/**
 * Scan reminders card (same visual language as Automation).
 *
 * @package ScoreFix
 *
 * @var array<string, mixed> $scorefix_settings Plugin settings array.
 */

defined( 'ABSPATH' ) || exit;

use ScoreFix\Admin\ActionsController;

if ( ! is_array( $scorefix_settings ) ) {
	$scorefix_settings = array();
}

$scorefix_reminders_on      = ! empty( $scorefix_settings['reminders_enabled'] );
$scorefix_reminder_freq     = ( isset( $scorefix_settings['reminder_frequency'] ) && '6months' === $scorefix_settings['reminder_frequency'] ) ? '6months' : '3months';
$scorefix_reminder_email_on = ! empty( $scorefix_settings['reminder_email'] );
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
			<div class="scorefix-automation__toggle-slot">
				<label class="scorefix-toggle scorefix-toggle--form">
					<input
						type="checkbox"
						name="scorefix_reminders_enabled"
						value="1"
						class="scorefix-toggle__input"
						aria-label="<?php esc_attr_e( 'Enable periodic scan reminders', 'scorefix' ); ?>"
						<?php checked( $scorefix_reminders_on ); ?>
					/>
					<span class="scorefix-toggle__track" aria-hidden="true"><span class="scorefix-toggle__thumb"></span></span>
				</label>
			</div>
		</div>

		<div class="scorefix-automation__panel scorefix-reminders__panel">
			<div class="scorefix-automation__panel-text">
				<strong class="scorefix-automation__panel-label"><?php esc_html_e( 'Frequency', 'scorefix' ); ?></strong>
				<span class="scorefix-automation__panel-hint"><?php esc_html_e( 'How often we should remind you.', 'scorefix' ); ?></span>
			</div>
			<div
				class="scorefix-segmented scorefix-reminders__frequency"
				role="radiogroup"
				aria-label="<?php esc_attr_e( 'Reminder frequency', 'scorefix' ); ?>"
			>
				<input
					type="radio"
					name="scorefix_reminder_frequency"
					id="scorefix_reminder_frequency_3"
					value="3months"
					class="scorefix-segmented__input"
					<?php checked( $scorefix_reminder_freq, '3months' ); ?>
				/>
				<label class="scorefix-segmented__pill" for="scorefix_reminder_frequency_3"><?php esc_html_e( '3 months', 'scorefix' ); ?></label>
				<input
					type="radio"
					name="scorefix_reminder_frequency"
					id="scorefix_reminder_frequency_6"
					value="6months"
					class="scorefix-segmented__input"
					<?php checked( $scorefix_reminder_freq, '6months' ); ?>
				/>
				<label class="scorefix-segmented__pill" for="scorefix_reminder_frequency_6"><?php esc_html_e( '6 months', 'scorefix' ); ?></label>
			</div>
		</div>

		<div class="scorefix-automation__panel scorefix-reminders__panel">
			<div class="scorefix-automation__panel-text">
				<strong class="scorefix-automation__panel-label"><?php esc_html_e( 'Email', 'scorefix' ); ?></strong>
				<span class="scorefix-automation__panel-hint"><?php esc_html_e( 'Also notify the site admin email address.', 'scorefix' ); ?></span>
			</div>
			<div class="scorefix-automation__toggle-slot">
				<label class="scorefix-toggle scorefix-toggle--form">
					<input
						type="checkbox"
						name="scorefix_reminder_email"
						value="1"
						class="scorefix-toggle__input"
						aria-label="<?php esc_attr_e( 'Also send an email to the site admin address', 'scorefix' ); ?>"
						<?php checked( $scorefix_reminder_email_on ); ?>
					/>
					<span class="scorefix-toggle__track" aria-hidden="true"><span class="scorefix-toggle__thumb"></span></span>
				</label>
			</div>
		</div>

		<p class="scorefix-automation__footer scorefix-muted scorefix-reminders__footer-note">
			<?php esc_html_e( 'Dismissing the admin notice or running a scan clears the reminder until the next interval.', 'scorefix' ); ?>
		</p>

		<div class="scorefix-reminders__submit">
			<button type="submit" class="button button-primary scorefix-reminders__save"><?php esc_html_e( 'Save reminder settings', 'scorefix' ); ?></button>
		</div>
	</form>
</div>
