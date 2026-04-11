<?php
/**
 * Background rendered-URL scan: chunked loopback fetches, merge into last snapshot.
 *
 * @package ScoreFix
 */

namespace ScoreFix\Scanner;

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
	 * @return void
	 */
	public static function process_tick() {
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

		$batch = (int) apply_filters(
			'scorefix_render_queue_batch_size',
			max( 1, min( 10, (int) RenderCaptureConfig::QUEUE_BATCH_SIZE ) )
		);


		$scanner = new Scanner();
		$new_issues = isset( $queue['issues'] ) && is_array( $queue['issues'] ) ? $queue['issues'] : array();

		for ( $i = 0; $i < $batch; $i++ ) {
			if ( empty( $queue['pending'] ) ) {
				break;
			}
			$url = array_shift( $queue['pending'] );
			$url = esc_url_raw( (string) $url );
			if ( '' === $url ) {
				continue;
			}

			$html = UrlHtmlCapture::fetch( $url, $token );
			if ( ! is_wp_error( $html ) ) {
				$inner = UrlHtmlCapture::extract_body_inner_html( (string) $html );
				if ( '' !== trim( $inner ) ) {
					$maker = function ( $t, $s, $e ) use ( $scanner, $url ) {
						$e['post_id']     = 0;
						$e['source']      = 'rendered_url';
						$e['capture_url'] = $url;
						if ( ! isset( $e['context'] ) || '' === (string) $e['context'] ) {
							$e['context'] = 'rendered';
						}
						return $scanner->create_issue( $t, $s, $e );
					};
					$new_issues = array_merge( $new_issues, $scanner->scan_html( $inner, 0, $maker ) );
				}
			}
		}

		$queue['issues'] = $new_issues;
		$done            = isset( $queue['total'] ) ? (int) $queue['total'] - count( $queue['pending'] ) : 0;
		$total           = isset( $queue['total'] ) ? (int) $queue['total'] : 0;

		set_transient(
			self::TRANSIENT_PROGRESS,
			array(
				'done'  => max( 0, $done ),
				'total' => max( 0, $total ),
			),
			2 * HOUR_IN_SECONDS
		);

		if ( empty( $queue['pending'] ) ) {
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
			return;
		}

		update_option( self::OPTION_QUEUE, $queue, false );
		self::schedule_next_tick();
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
		if ( is_admin() && current_user_can( 'manage_options' ) ) {
			$hook = isset( $GLOBALS['pagenow'] ) ? (string) $GLOBALS['pagenow'] : '';
			if ( 'options-general.php' === $hook ) {
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
		$score  = ( new Scanner() )->calculate_score( $merged );

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
}
