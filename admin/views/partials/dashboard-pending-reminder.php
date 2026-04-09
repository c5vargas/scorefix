<?php
/**
 * Scan reminder when cron flagged pending (inline, below ScoreFix header).
 *
 * @package ScoreFix
 */

defined( 'ABSPATH' ) || exit;

use ScoreFix\Admin\ReminderScheduler;

ReminderScheduler::render_dashboard_reminder_banner();
