<?php
/**
 * Automation sidebar card: run scan, automatic fixes toggle, last scan footer.
 *
 * @package ScoreFix
 *
 * @var bool   $fixes_on
 * @var string $scanned ISO datetime or empty.
 * @var array  $scorefix_settings Plugin settings.
 */

defined( 'ABSPATH' ) || exit;

use ScoreFix\Admin\ActionsController;

$scorefix_settings    = isset( $scorefix_settings ) && is_array( $scorefix_settings ) ? $scorefix_settings : array();
$meta_description_on  = ! array_key_exists( 'meta_description_enabled', $scorefix_settings ) || ! empty( $scorefix_settings['meta_description_enabled'] );

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
	<div class="scorefix-automation__scope">
		<p class="scorefix-automation__scope-title"><?php esc_html_e( 'When automatic fixes are on, the plugin can:', 'scorefix' ); ?></p>
		<ul class="scorefix-automation__scope-list">
			<li><?php esc_html_e( 'Fill missing image ALT text (from the media library or filename).', 'scorefix' ); ?></li>
			<li><?php esc_html_e( 'Add accessible names to links and buttons that have none.', 'scorefix' ); ?></li>
			<li><?php esc_html_e( 'Add aria-label to unlabeled text fields, checkboxes, radios, selects, and textareas (when there is no label, wrapping label, or aria naming).', 'scorefix' ); ?></li>
		</ul>
		<p class="scorefix-automation__scope-note scorefix-muted"><?php esc_html_e( 'It does not fix color contrast, server speed, cache headers, or third-party scripts. Lighthouse may still report those separately.', 'scorefix' ); ?></p>
	</div>
	<div class="scorefix-automation__panel scorefix-automation__panel--stacked">
		<div class="scorefix-automation__panel-text">
			<strong class="scorefix-automation__panel-label"><?php esc_html_e( 'Fallback meta description', 'scorefix' ); ?></strong>
			<span class="scorefix-automation__panel-hint"><?php esc_html_e( 'Outputs a meta description on singular pages and the front page when none is set (Yoast / Rank Math custom descriptions are respected).', 'scorefix' ); ?></span>
		</div>
		<form method="post" class="scorefix-automation-form scorefix-meta-desc-form">
			<?php wp_nonce_field( ActionsController::ACTION_SAVE_META_DESC ); ?>
			<input type="hidden" name="scorefix_action" value="<?php echo esc_attr( ActionsController::ACTION_SAVE_META_DESC ); ?>" />
			<label class="scorefix-meta-desc-toggle">
				<input type="checkbox" name="scorefix_meta_description_enabled" value="1" <?php checked( $meta_description_on ); ?> />
				<span><?php esc_html_e( 'Enable fallback meta description', 'scorefix' ); ?></span>
			</label>
			<button type="submit" class="button button-secondary"><?php esc_html_e( 'Save', 'scorefix' ); ?></button>
		</form>
	</div>
	<div class="scorefix-automation__chart" aria-hidden="true">
		<div class="scorefix-automation__chart-inner"></div>
	</div>
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
