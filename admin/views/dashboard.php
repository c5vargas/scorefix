<?php
/**
 * ScoreFix dashboard template.
 *
 * @package ScoreFix
 *
 * Variables: $score, $issues, $scanned, $fixes_on, $notice, $metrics, $show_metric_trend_hint, $perf_copy, $scorefix_settings, $scorefix_issues_view, $render_scan_state, $comparison, $impact_estimate, $score_history, $deferred_meta, $last_settings_event
 */

defined( 'ABSPATH' ) || exit;

?>
<div class="wrap scorefix-dashboard">
	<h1 style="font-size: 0px;margin:0;padding:0;"><?php echo esc_html( get_admin_page_title() ); ?></h1>
	<?php include SCOREFIX_PLUGIN_DIR . 'admin/views/partials/dashboard-notices.php'; ?>
	<?php include SCOREFIX_PLUGIN_DIR . 'admin/views/partials/dashboard-pending-reminder.php'; ?>

	<header class="scorefix-page-header">
		<h2 class="scorefix-page-title"><?php echo esc_html( get_admin_page_title() ); ?></h2>
		<p class="scorefix-lead">
			<?php esc_html_e( 'Fix your Lighthouse score and improve your UX in minutes. No coding required.', 'scorefix' ); ?>
		</p>
	</header>

	

	<div class="scorefix-dashboard-layout">
		<div class="scorefix-dashboard-main">
			<?php include SCOREFIX_PLUGIN_DIR . 'admin/views/partials/dashboard-render-scan-progress.php'; ?>
			<?php include SCOREFIX_PLUGIN_DIR . 'admin/views/partials/dashboard-site-health.php'; ?>
			<?php include SCOREFIX_PLUGIN_DIR . 'admin/views/partials/dashboard-issues.php'; ?>
		</div>
		<div class="scorefix-dashboard-aside">
			<?php include SCOREFIX_PLUGIN_DIR . 'admin/views/partials/dashboard-automation.php'; ?>
			<?php include SCOREFIX_PLUGIN_DIR . 'admin/views/partials/dashboard-reminders.php'; ?>
			<?php include SCOREFIX_PLUGIN_DIR . 'admin/views/partials/dashboard-seo.php'; ?>
			<?php include SCOREFIX_PLUGIN_DIR . 'admin/views/partials/dashboard-score-timeline.php'; ?>
		</div>
	</div>
</div>
