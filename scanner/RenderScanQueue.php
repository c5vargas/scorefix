<?php
/**
 * Background rendered-URL scan: chunked loopback fetches, merge into last snapshot.
 *
 * @package ScoreFix
 */

namespace ScoreFix\Scanner;

use ScoreFix\Scanner\Rules\HeadSeoRule;
use ScoreFix\Scanner\Rules\JsonLdSeoRule;

defined( 'ABSPATH' ) || exit;

/**
 * Class RenderScanQueue
 */
class RenderScanQueue {

	const OPTION_QUEUE = 'scorefix_render_scan_queue';

	const CRON_HOOK = 'scorefix_render_queue_tick';

	const TRANSIENT_PROGRESS = 'scorefix_render_progress';

	const TRANSIENT_FETCH = 'scorefix_render_fetch_token';

	/**
	 * Register WP hooks (call from Plugin::init).
	 *
	 * @param \ScoreFix\Core\Loader $loader Loader.
	 * @return void
	 */
	public static function register( $loader ) {
		$loader->add_action( self::CRON_HOOK, __CLASS__, 'process_tick', 10, 0 );
		$loader->add_action( 'load-settings_page_scorefix', __CLASS__, 'maybe_tick_on_dashboard', 5, 0 );
	}

	/**
	 * Process a few URLs when admin opens ScoreFix (fallback if wp-cron delayed).
	 *
	 * @return void
	 */
	public static function maybe_tick_on_dashboard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		self::process_tick();
	}

	/**
	 * Cron / dashboard worker: drain queue in small batches.
	 *
	 * @param int|null $max_urls Optional. When set (e.g. 1 from dashboard AJAX poll), process at most this many URLs per call; when null, use batch filter (cron / admin load).
	 * @return void
	 */
	public static function process_tick( $max_urls = null ) {
		if ( ! self::is_allowed_runner() ) {
			return;
		}

		$queue = get_option( self::OPTION_QUEUE, null );
		if ( ! is_array( $queue ) || empty( $queue['pending'] ) || ! is_array( $queue['pending'] ) ) {
			self::clear_scheduled_ticks();
			return;
		}

		$token = isset( $queue['fetch_token'] ) ? (string) $queue['fetch_token'] : '';
		if ( '' === $token ) {
			self::abort_queue();
			return;
		}

		$default_batch = (int) apply_filters(
			'scorefix_render_queue_batch_size',
			max( 1, min( 10, (int) RenderCaptureConfig::QUEUE_BATCH_SIZE ) )
		);
		$batch         = null !== $max_urls ? max( 1, (int) $max_urls ) : $default_batch;

		$scanner    = new Scanner();
		$new_issues = isset( $queue['issues'] ) && is_array( $queue['issues'] ) ? $queue['issues'] : array();
		$total      = isset( $queue['total'] ) ? (int) $queue['total'] : 0;

		for ( $i = 0; $i < $batch; $i++ ) {
			if ( empty( $queue['pending'] ) ) {
				break;
			}
			$url = array_shift( $queue['pending'] );
			$url = esc_url_raw( (string) $url );
			if ( '' === $url ) {
				$queue['issues'] = $new_issues;
				update_option( self::OPTION_QUEUE, $queue, false );
				self::persist_progress_transient( $queue );
				if ( empty( $queue['pending'] ) ) {
					self::complete_queue_run( $queue, $total );
					return;
				}
				continue;
			}

			$html = UrlHtmlCapture::fetch( $url, $token );
			if ( ! is_wp_error( $html ) ) {
				$html_str = (string) $html;
				$maker    = function ( $t, $s, $e ) use ( $scanner, $url ) {
					$e['post_id']     = 0;
					$e['source']      = 'rendered_url';
					$e['capture_url'] = $url;
					if ( ! isset( $e['context'] ) || '' === (string) $e['context'] ) {
						$e['context'] = 'rendered';
					}
					return $scanner->create_issue( $t, $s, $e );
				};
				$new_issues = array_merge( $new_issues, HeadSeoRule::collect( $html_str, $maker, $url ) );
				$new_issues = array_merge( $new_issues, JsonLdSeoRule::collect( $html_str, $maker, $url ) );
				$inner      = UrlHtmlCapture::extract_body_inner_html( $html_str );
				if ( '' !== trim( $inner ) ) {
					$new_issues = array_merge( $new_issues, $scanner->scan_html( $inner, 0, $maker ) );
				}
			}

			$queue['issues'] = $new_issues;
			update_option( self::OPTION_QUEUE, $queue, false );
			self::persist_progress_transient( $queue );

			if ( empty( $queue['pending'] ) ) {
				self::complete_queue_run( $queue, $total );
				return;
			}
		}

		update_option( self::OPTION_QUEUE, $queue, false );
		self::schedule_next_tick();
	}

	/**
	 * Write progress transient from current queue row (after each URL for live UI).
	 *
	 * @param array<string, mixed> $queue Queue row.
	 * @return void
	 */
	protected static function persist_progress_transient( array $queue ) {
		$total = isset( $queue['total'] ) ? (int) $queue['total'] : 0;
		$pend  = isset( $queue['pending'] ) && is_array( $queue['pending'] ) ? count( $queue['pending'] ) : 0;
		$done  = $total > 0 ? max( 0, $total - $pend ) : 0;
		set_transient(
			self::TRANSIENT_PROGRESS,
			array(
				'done'  => $done,
				'total' => max( 0, $total ),
			),
			2 * HOUR_IN_SECONDS
		);
	}

	/**
	 * Merge render issues into snapshot, clear queue, set idle progress.
	 *
	 * @param array<string, mixed> $queue Queue row.
	 * @param int                  $total Total URLs from original run.
	 * @return void
	 */
	protected static function complete_queue_run( array $queue, $total ) {
		$total = max( 0, (int) $total );
		self::finalize( $queue );
		delete_option( self::OPTION_QUEUE );
		delete_transient( self::TRANSIENT_FETCH );
		self::clear_scheduled_ticks();
		set_transient(
			self::TRANSIENT_PROGRESS,
			array(
				'done'  => $total,
				'total' => $total,
				'idle'  => true,
			),
			300
		);
	}

	/**
	 * Start queue after a sync scan (posts + attachments snapshot already saved).
	 *
	 * @param bool  $had_prior_scan Whether a scan existed before this run.
	 * @param array $prev_issues    Issues from snapshot before this run (for comparison on finalize).
	 * @return void
	 */
	public static function start_after_sync_scan( $had_prior_scan, array $prev_issues ) {
		if ( apply_filters( 'scorefix_skip_render_url_scan', false ) ) {
			return;
		}

		self::clear_scheduled_ticks();
		delete_option( self::OPTION_QUEUE );
		delete_transient( self::TRANSIENT_FETCH );

		$urls = RenderUrlCollector::collect();
		if ( empty( $urls ) ) {
			return;
		}

		$token = wp_generate_password( 64, false, false );
		set_transient( self::TRANSIENT_FETCH, $token, 2 * HOUR_IN_SECONDS );

		$queue = array(
			'pending'        => $urls,
			'issues'         => array(),
			'total'          => count( $urls ),
			'fetch_token'    => $token,
			'had_prior_scan' => (bool) $had_prior_scan,
			'prev_issues'    => $prev_issues,
		);
		update_option( self::OPTION_QUEUE, $queue, false );

		set_transient(
			self::TRANSIENT_PROGRESS,
			array(
				'done'  => 0,
				'total' => count( $urls ),
			),
			2 * HOUR_IN_SECONDS
		);

		self::schedule_next_tick();
		if ( function_exists( 'spawn_cron' ) ) {
			spawn_cron();
		}
	}

	/**
	 * @return void
	 */
	protected static function schedule_next_tick() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_single_event( time() + 10, self::CRON_HOOK );
		}
	}

	/**
	 * @return void
	 */
	protected static function clear_scheduled_ticks() {
		while ( $t = wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_unschedule_event( $t, self::CRON_HOOK );
		}
	}

	/**
	 * @return void
	 */
	protected static function abort_queue() {
		delete_option( self::OPTION_QUEUE );
		delete_transient( self::TRANSIENT_FETCH );
		self::clear_scheduled_ticks();
	}

	/**
	 * @return bool
	 */
	protected static function is_allowed_runner() {
		if ( wp_doing_cron() ) {
			return true;
		}
		// Dashboard AJAX poll advances the queue while the admin keeps the ScoreFix screen open.
		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
			if ( 'scorefix_render_scan_status' === $action && current_user_can( 'manage_options' ) ) {
				return true;
			}
		}
		if ( is_admin() && current_user_can( 'manage_options' ) ) {
			// Prefer hook context: $GLOBALS['pagenow'] is not always set early on all hosts.
			if ( function_exists( 'doing_action' ) && doing_action( 'load-settings_page_scorefix' ) ) {
				return true;
			}
			$hook = isset( $GLOBALS['pagenow'] ) ? (string) $GLOBALS['pagenow'] : '';
			if ( 'options-general.php' === $hook || 'settings.php' === $hook ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Merge render issues into last scan option.
	 *
	 * @param array<string, mixed> $queue Final queue row.
	 * @return void
	 */
	protected static function finalize( array $queue ) {
		$scan = get_option( Scanner::OPTION_LAST_SCAN, null );
		if ( ! is_array( $scan ) || ! isset( $scan['issues'] ) || ! is_array( $scan['issues'] ) ) {
			return;
		}

		$render_issues = isset( $queue['issues'] ) && is_array( $queue['issues'] ) ? $queue['issues'] : array();

		$base = array();
		foreach ( $scan['issues'] as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$src = isset( $row['source'] ) ? sanitize_key( (string) $row['source'] ) : '';
			if ( 'rendered_url' === $src ) {
				continue;
			}
			$base[] = $row;
		}

		$merged = array_merge( $base, $render_issues );

		$score_context = array();
		if ( isset( $scan['scanned_post_ids'] ) && is_array( $scan['scanned_post_ids'] ) ) {
			$score_context['scanned_post_ids'] = $scan['scanned_post_ids'];
		}
		if ( isset( $scan['scanned_attachment_ids'] ) && is_array( $scan['scanned_attachment_ids'] ) ) {
			$score_context['scanned_attachment_ids'] = $scan['scanned_attachment_ids'];
		}
		$score = ( new Scanner() )->calculate_score( $merged, $score_context );

		$had_prior = ! empty( $queue['had_prior_scan'] );
		$prev      = isset( $queue['prev_issues'] ) && is_array( $queue['prev_issues'] ) ? $queue['prev_issues'] : array();

		$scan['issues']     = $merged;
		$scan['score']      = $score;
		$scan['comparison'] = ScanComparison::build( $had_prior, $prev, $merged );
		$scan['render_scan_completed_at'] = gmdate( 'c' );

		update_option( Scanner::OPTION_LAST_SCAN, $scan, false );
	}

	/**
	 * Progress for dashboard (done/total or idle).
	 *
	 * @return array<string, mixed>
	 */
	public static function get_progress() {
		$t = get_transient( self::TRANSIENT_PROGRESS );
		return is_array( $t ) ? $t : array();
	}

	/**
	 * Whether the async rendered-URL queue still has work (results not final yet).
	 *
	 * @return bool
	 */
	public static function is_background_scan_running() {
		$queue = get_option( self::OPTION_QUEUE, null );
		return is_array( $queue )
			&& ! empty( $queue['pending'] )
			&& is_array( $queue['pending'] );
	}

	/**
	 * State for dashboard UI + polling (running flag, counts, percent).
	 *
	 * @return array{running: bool, done: int, total: int, pct: int}
	 */
	public static function get_background_scan_state() {
		$running = self::is_background_scan_running();
		$done    = 0;
		$total   = 0;

		$prog = get_transient( self::TRANSIENT_PROGRESS );
		if ( $running && is_array( $prog ) && empty( $prog['idle'] ) ) {
			$done  = isset( $prog['done'] ) ? (int) $prog['done'] : 0;
			$total = isset( $prog['total'] ) ? (int) $prog['total'] : 0;
		}

		if ( $running && $total < 1 ) {
			$queue = get_option( self::OPTION_QUEUE, null );
			if ( is_array( $queue ) ) {
				$total = isset( $queue['total'] ) ? (int) $queue['total'] : 0;
				if ( $total < 1 && ! empty( $queue['pending'] ) && is_array( $queue['pending'] ) ) {
					$total = count( $queue['pending'] );
				}
				$pending_n = isset( $queue['pending'] ) && is_array( $queue['pending'] ) ? count( $queue['pending'] ) : 0;
				$done        = max( 0, $total - $pending_n );
			}
		}

		$pct = ( $total > 0 )
			? (int) min( 100, max( 0, (int) round( ( $done / (float) $total ) * 100 ) ) )
			: 0;

		return array(
			'running' => $running,
			'done'    => max( 0, $done ),
			'total'   => max( 0, $total ),
			'pct'     => $pct,
		);
	}
}
