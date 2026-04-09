<?php
/**
 * ScoreFix dashboard template.
 *
 * @package ScoreFix
 *
 * Variables: $score, $issues, $scanned, $fixes_on, $notice, $metrics, $show_metric_trend_hint, $perf_copy
 */

defined( 'ABSPATH' ) || exit;

use ScoreFix\Admin\DashboardPage;
use ScoreFix\Admin\ActionsController;

?>
<div class="wrap scorefix-dashboard">

	<?php if ( 'scan_done' === $notice || 'fixes_on' === $notice || 'fixes_off' === $notice ) : ?>
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
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<header class="scorefix-page-header">
		<h1 class="scorefix-page-title"><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<p class="scorefix-lead">
			<?php esc_html_e( 'Fix your Lighthouse score and improve your UX in minutes. No coding required.', 'scorefix' ); ?>
		</p>
	</header>

	<div class="scorefix-grid scorefix-grid--hero">
		<div class="scorefix-card scorefix-card--performance">
			<p class="scorefix-performance__kicker"><?php esc_html_e( 'Site health', 'scorefix' ); ?></p>
			<div class="scorefix-performance__top">
				<div class="scorefix-performance__intro">
					<p class="scorefix-performance__headline" role="heading" aria-level="2"><?php echo esc_html( $perf_copy['headline'] ); ?></p>
					<p class="scorefix-performance__sub"><?php echo esc_html( $perf_copy['sub'] ); ?></p>
				</div>
				<div class="scorefix-donut-wrap">
					<?php
					$scorefix_donut_pct   = null !== $score ? max( 0, min( 100, (int) $score ) ) : 0;
					$scorefix_donut_tone  = DashboardPage::donut_score_tone_slug( $score );
					$scorefix_donut_class = 'scorefix-donut';
					if ( null === $score ) {
						$scorefix_donut_class .= ' scorefix-donut--empty';
					} elseif ( null !== $scorefix_donut_tone ) {
						$scorefix_donut_class .= ' scorefix-donut--tone-' . $scorefix_donut_tone;
					}
					?>
					<div
						class="<?php echo esc_attr( $scorefix_donut_class ); ?>"
						style="<?php echo null !== $score ? '--scorefix-donut-pct: ' . (int) $scorefix_donut_pct . ';' : ''; ?>"
						role="img"
						aria-label="<?php echo esc_attr( null !== $score ? sprintf( /* translators: %d: score */ __( 'Overall score %d out of 100', 'scorefix' ), (int) $score ) : __( 'No score yet', 'scorefix' ) ); ?>"
					>
						<div class="scorefix-donut__hole"></div>
						<div class="scorefix-donut__content">
							<?php if ( null === $score ) : ?>
								<span class="scorefix-donut__value scorefix-donut__value--na">&mdash;</span>
							<?php else : ?>
								<span class="scorefix-donut__value" aria-hidden="true"><?php echo esc_html( (string) $score ); ?></span>
							<?php endif; ?>
							<span class="scorefix-donut__label"><?php esc_html_e( 'Overall score', 'scorefix' ); ?></span>
						</div>
					</div>
				</div>
			</div>

			<?php
			$scorefix_metric_layout = 'performance-row';
			include SCOREFIX_PLUGIN_DIR . 'admin/views/partials/metric-cards.php';
			?>
		</div>

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
	</div>

	<div class="scorefix-card scorefix-card--issues">
		<h2><?php esc_html_e( 'Issues found', 'scorefix' ); ?></h2>
		<?php if ( empty( $issues ) ) : ?>
			<p class="scorefix-muted"><?php esc_html_e( 'No scan results yet, or no issues detected. Run a scan to populate this list.', 'scorefix' ); ?></p>
		<?php else : ?>
			<table class="widefat striped scorefix-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Issue', 'scorefix' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Why it matters', 'scorefix' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Context', 'scorefix' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					$scorefix_issues_shown = 0;
					foreach ( $issues as $scorefix_issue ) {
						if ( ! is_array( $scorefix_issue ) ) {
							continue;
						}
						if ( $scorefix_issues_shown >= 50 ) {
							break;
						}
						++$scorefix_issues_shown;
						list( $scorefix_issue_title, $scorefix_issue_desc ) = DashboardPage::describe_issue( $scorefix_issue );
						$scorefix_ctx_parts = array();
						if ( ! empty( $scorefix_issue['post_id'] ) ) {
							$scorefix_ctx_parts[] = sprintf(
								/* translators: %d: post or attachment ID */
								__( 'ID %d', 'scorefix' ),
								(int) $scorefix_issue['post_id']
							);
						}
						if ( ! empty( $scorefix_issue['context'] ) ) {
							$scorefix_ctx_parts[] = sanitize_text_field( (string) $scorefix_issue['context'] );
						}
						$scorefix_ctx = implode( ' · ', $scorefix_ctx_parts );
						?>
						<tr>
							<td><?php echo esc_html( $scorefix_issue_title ); ?></td>
							<td><?php echo esc_html( $scorefix_issue_desc ); ?></td>
							<td><?php echo esc_html( $scorefix_ctx ); ?></td>
						</tr>
					<?php } ?>
				</tbody>
			</table>
			<?php if ( count( $issues ) > 50 ) : ?>
				<p class="scorefix-muted"><?php esc_html_e( 'Showing the first 50 issues. Future versions will add filtering and exports.', 'scorefix' ); ?></p>
			<?php endif; ?>
		<?php endif; ?>
	</div>

	<div class="scorefix-card scorefix-card--limits">
		<h2><?php esc_html_e( 'What ScoreFix does not replace', 'scorefix' ); ?></h2>
		<ul class="scorefix-list">
			<li><?php esc_html_e( 'Perfect editorial ALT text for every image (you may still want to refine wording for SEO).', 'scorefix' ); ?></li>
			<li><?php esc_html_e( 'Full WCAG audit or legal compliance guarantee.', 'scorefix' ); ?></li>
			<li><?php esc_html_e( 'Problems inside heavily customized JavaScript UIs without server-rendered HTML.', 'scorefix' ); ?></li>
		</ul>
	</div>
</div>
