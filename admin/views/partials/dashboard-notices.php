<?php
/**
 * Inline success/info notices after redirects.
 *
 * @package ScoreFix
 *
 * @var string $notice Notice slug or empty.
 */

defined( 'ABSPATH' ) || exit;

$scorefix_notice_slugs = array( 'scan_done', 'fixes_on', 'fixes_off', 'reminders_saved' );
if ( ! isset( $notice ) || ! is_string( $notice ) || ! in_array( $notice, $scorefix_notice_slugs, true ) ) {
	return;
}
?>
<div class="scorefix-notices">
	<?php if ( 'scan_done' === $notice ) : ?>
		<div class="notice notice-success is-dismissible scorefix-notice scorefix-notice--success">
			<p>
				<span class="scorefix-notice__icon dashicons dashicons-yes-alt" aria-hidden="true"></span>
				<span class="scorefix-notice__msg"><?php esc_html_e( 'Scan complete. Your ScoreFix Score and issue list are updated.', 'scorefix' ); ?></span>
			</p>
		</div>
	<?php elseif ( 'fixes_on' === $notice ) : ?>
		<div class="notice notice-success is-dismissible scorefix-notice scorefix-notice--success">
			<p>
				<span class="scorefix-notice__icon dashicons dashicons-admin-plugins" aria-hidden="true"></span>
				<span class="scorefix-notice__msg"><?php esc_html_e( 'Automatic fixes are now active on your public site.', 'scorefix' ); ?></span>
			</p>
		</div>
	<?php elseif ( 'fixes_off' === $notice ) : ?>
		<div class="notice notice-info is-dismissible scorefix-notice scorefix-notice--info">
			<p>
				<span class="scorefix-notice__icon dashicons dashicons-info" aria-hidden="true"></span>
				<span class="scorefix-notice__msg"><?php esc_html_e( 'Automatic fixes are turned off.', 'scorefix' ); ?></span>
			</p>
		</div>
	<?php elseif ( 'reminders_saved' === $notice ) : ?>
		<div class="notice notice-success is-dismissible scorefix-notice scorefix-notice--success">
			<p>
				<span class="scorefix-notice__icon dashicons dashicons-yes-alt" aria-hidden="true"></span>
				<span class="scorefix-notice__msg"><?php esc_html_e( 'Reminder settings saved.', 'scorefix' ); ?></span>
			</p>
		</div>
	<?php endif; ?>
</div>
