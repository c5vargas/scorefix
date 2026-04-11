<?php
/**
 * ScoreFix dashboard template.
 *
 * @package ScoreFix
 *
 * Variables: $score, $issues, $scanned, $fixes_on, $notice, $metrics, $show_metric_trend_hint, $perf_copy, $scorefix_settings, $scorefix_issues_view, $scorefix_score_hint
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
			<?php include SCOREFIX_PLUGIN_DIR . 'admin/views/partials/dashboard-site-health.php'; ?>
			<?php include SCOREFIX_PLUGIN_DIR . 'admin/views/partials/dashboard-issues.php'; ?>
		</div>
		<div class="scorefix-dashboard-aside">
			<?php include SCOREFIX_PLUGIN_DIR . 'admin/views/partials/dashboard-automation.php'; ?>
			<?php include SCOREFIX_PLUGIN_DIR . 'admin/views/partials/dashboard-reminders.php'; ?>
			<?php include SCOREFIX_PLUGIN_DIR . 'admin/views/partials/dashboard-seo.php'; ?>
		</div>
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
