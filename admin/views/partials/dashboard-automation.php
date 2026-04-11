<?php
/**
 * Automation sidebar card: run scan, automatic fixes, last scan footer.
 *
 * @package ScoreFix
 *
 * @var bool   $fixes_on
 * @var string $scanned ISO datetime or empty.
 * @var array<string, mixed> $deferred_meta Pending deferred scan meta (optional).
 * @var array{reason: string, at: string} $last_settings_event Last settings change (optional).
 */

defined( 'ABSPATH' ) || exit;

use ScoreFix\Admin\ActionsController;
use ScoreFix\Admin\DashboardPage;

if ( ! isset( $deferred_meta ) || ! is_array( $deferred_meta ) ) {
	$deferred_meta = array();
}
if ( ! isset( $last_settings_event ) || ! is_array( $last_settings_event ) ) {
	$last_settings_event = array( 'reason' => '', 'at' => '' );
}

?>
<div class="scorefix-card scorefix-card--automation">
	<div class="scorefix-automation__header">
		<p class="scorefix-automation__title" role="heading" aria-level="2"><?php esc_html_e( 'Automation', 'scorefix' ); ?></p>
		<form method="post" class="scorefix-scan-form">
			<?php wp_nonce_field( ActionsController::ACTION_RUN_SCAN ); ?>
			<input type="hidden" name="scorefix_action" value="<?php echo esc_attr( ActionsController::ACTION_RUN_SCAN ); ?>" />
			<button type="submit" class="button button-primary scorefix-btn-scan">
				<span class="scorefix-btn-scan__icon dashicons dashicons-update" aria-hidden="true"></span>
				<span class="scorefix-btn-scan__text"><?php esc_html_e( 'Run scan', 'scorefix' ); ?></span>
			</button>
		</form>
	</div>
	<div class="scorefix-automation__panel">
		<div class="scorefix-automation__panel-text">
			<strong class="scorefix-automation__panel-label"><?php esc_html_e( 'Automatic fixes', 'scorefix' ); ?></strong>
			<span class="scorefix-automation__panel-hint"><?php esc_html_e( 'Repair issues in rendered HTML on the front end.', 'scorefix' ); ?></span>
		</div>
		<div class="scorefix-automation__toggle-slot">
			<?php if ( ! $fixes_on ) : ?>
				<form method="post" class="scorefix-automation-form">
					<?php wp_nonce_field( ActionsController::ACTION_APPLY ); ?>
					<input type="hidden" name="scorefix_action" value="<?php echo esc_attr( ActionsController::ACTION_APPLY ); ?>" />
					<button type="submit" class="scorefix-toggle" aria-pressed="false" aria-label="<?php esc_attr_e( 'Enable automatic fixes', 'scorefix' ); ?>">
						<span class="scorefix-toggle__track"><span class="scorefix-toggle__thumb"></span></span>
					</button>
				</form>
			<?php else : ?>
				<form method="post" class="scorefix-automation-form">
					<?php wp_nonce_field( ActionsController::ACTION_DISABLE ); ?>
					<input type="hidden" name="scorefix_action" value="<?php echo esc_attr( ActionsController::ACTION_DISABLE ); ?>" />
					<button type="submit" class="scorefix-toggle scorefix-toggle--on" aria-pressed="true" aria-label="<?php esc_attr_e( 'Disable automatic fixes', 'scorefix' ); ?>">
						<span class="scorefix-toggle__track"><span class="scorefix-toggle__thumb"></span></span>
					</button>
				</form>
			<?php endif; ?>
		</div>
	</div>

	<div class="scorefix-automation__chart" aria-hidden="true">
		<div class="scorefix-automation__chart-inner"></div>
	</div>
	<?php if ( ! empty( $deferred_meta['run_after'] ) ) : ?>
		<p class="scorefix-deferred-notice scorefix-muted" role="status">
			<span class="dashicons dashicons-clock" aria-hidden="true"></span>
			<?php esc_html_e( 'A validation scan is scheduled to refresh your score after the latest settings change.', 'scorefix' ); ?>
		</p>
	<?php elseif ( ! empty( $last_settings_event['at'] ) && ! empty( $last_settings_event['reason'] ) ) : ?>
		<p class="scorefix-settings-event scorefix-muted">
			<?php
			$scorefix_ev_at = strtotime( (string) $last_settings_event['at'] );
			$scorefix_ev_label = $scorefix_ev_at
				? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $scorefix_ev_at )
				: '';
			echo esc_html(
				sprintf(
					/* translators: 1: human reason, 2: formatted datetime */
					__( 'Last settings change: %1$s (%2$s)', 'scorefix' ),
					DashboardPage::settings_impact_reason_label( (string) $last_settings_event['reason'] ),
					$scorefix_ev_label
				)
			);
			?>
		</p>
	<?php endif; ?>
	<p class="scorefix-automation__footer scorefix-muted">
		<?php
		if ( $scanned ) {
			echo esc_html(
				sprintf(
					/* translators: %s: formatted date/time of last scan */
					__( 'Last system scan: %s', 'scorefix' ),
					wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $scanned ) )
				)
			);
		} else {
			esc_html_e( 'Run a scan to establish a baseline for this site.', 'scorefix' );
		}
		?>
	</p>
</div>
