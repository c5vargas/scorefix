<?php
/**
 * Fixed tuning for same-host HTML capture + background queue (no admin UI).
 *
 * @package ScoreFix
 */

namespace ScoreFix\Scanner;

defined( 'ABSPATH' ) || exit;

/**
 * Class RenderCaptureConfig
 */
class RenderCaptureConfig {

	/** @var int Seconds for wp_remote_get loopback. */
	const LOOPBACK_TIMEOUT_SECONDS = 15;

	/** @var int Max URLs queued per scan (defaults + published permalinks), after dedupe. */
	const QUEUE_MAX_URLS = 200;

	/** @var int URLs processed per cron / admin tick. */
	const QUEUE_BATCH_SIZE = 4;
}
