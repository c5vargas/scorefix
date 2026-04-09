<?php
/**
 * Periodic scan reminders (dashboard notice + optional email).
 *
 * @package ScoreFix
 */

namespace ScoreFix\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Class ReminderScheduler
 */
class ReminderScheduler {

	const OPTION_PENDING = 'scorefix_reminder_pending';

	const CRON_HOOK = 'scorefix_reminder_cron';

	const NONCE_DISMISS = 'scorefix_reminder_dismiss';

	/** @var string */
	const SETTINGS_SCREEN_ID = 'settings_page_scorefix';

	/**
	 * Register hooks.
	 *
	 * @param \ScoreFix\Core\Loader $loader Loader.
	 * @return void
	 */
	public function register( $loader ) {
		$loader->add_filter( 'cron_schedules', $this, 'add_cron_schedules', 10, 1 );
		$loader->add_action( self::CRON_HOOK, $this, 'on_cron', 10, 0 );
		$loader->add_action( 'admin_init', $this, 'maybe_merge_settings_defaults', 5, 0 );
		$loader->add_action( 'admin_init', $this, 'handle_dismiss', 20, 0 );
		$loader->add_action( 'admin_notices', $this, 'maybe_show_notice', 10, 0 );
	}

	/**
	 * @param array<string, array<string, int|string>> $schedules Schedules.
	 * @return array<string, array<string, int|string>>
	 */
	public function add_cron_schedules( $schedules ) {
		if ( ! is_array( $schedules ) ) {
			$schedules = array();
		}
		$schedules['scorefix_every_3_months'] = array(
			'interval' => 90 * DAY_IN_SECONDS,
			/* translators: Plugin cron schedule label (admin). */
			'display'  => __( 'Every 3 months (ScoreFix)', 'scorefix' ),
		);
		$schedules['scorefix_every_6_months'] = array(
			'interval' => 180 * DAY_IN_SECONDS,
			/* translators: Plugin cron schedule label (admin). */
			'display'  => __( 'Every 6 months (ScoreFix)', 'scorefix' ),
		);
		return $schedules;
	}

	/**
	 * Cron callback: flag pending notice and optionally email admin.
	 *
	 * @return void
	 */
	public function on_cron() {
		update_option( self::OPTION_PENDING, time(), false );

		$settings = get_option( 'scorefix_settings', array() );
		if ( ! is_array( $settings ) || empty( $settings['reminder_email'] ) ) {
			return;
		}

		$to      = get_option( 'admin_email' );
		$subject = __( 'ScoreFix: time for a quick site check', 'scorefix' );
		$url     = admin_url( 'options-general.php?page=scorefix' );
		$body    = sprintf(
			/* translators: 1: site name, 2: URL to ScoreFix settings */
			__(
				"Hi,\n\nIt's been a while since you ran a ScoreFix scan on %1\$s. Open your dashboard and tap “Run scan” — it only takes a moment.\n\n%2\$s\n\nIf you've already scanned recently, you can ignore this message.\n\n— ScoreFix",
				'scorefix'
			),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			$url
		);
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		wp_mail( $to, $subject, $body, $headers );
	}

	/**
	 * Whether the current admin screen is the ScoreFix settings page.
	 *
	 * On that screen the reminder is rendered inside the plugin layout (below the page header),
	 * not via admin_notices — see render_dashboard_reminder_banner().
	 *
	 * @return bool
	 */
	public static function is_plugin_settings_screen() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}
		$screen = get_current_screen();
		return $screen && self::SETTINGS_SCREEN_ID === $screen->id;
	}

	/**
	 * Pending reminder flag for the current site (any user).
	 *
	 * @return bool
	 */
	public static function is_reminder_pending() {
		return (bool) get_option( self::OPTION_PENDING );
	}

	/**
	 * Dismiss + scan URLs for the pending reminder UI.
	 *
	 * @return array{dismiss: string, scan: string}
	 */
	private static function get_reminder_action_urls() {
		return array(
			'dismiss' => wp_nonce_url(
				admin_url( 'admin.php?scorefix_reminder_dismiss=1' ),
				self::NONCE_DISMISS
			),
			'scan'    => admin_url( 'options-general.php?page=scorefix' ),
		);
	}

	/**
	 * Show admin-wide notice when a reminder is pending (all admin except ScoreFix screen).
	 *
	 * WordPress prints admin_notices at the start of #wpbody-content; the .notice class does not
	 * control that order. We skip this hook on our settings screen and print inline instead.
	 *
	 * @return void
	 */
	public function maybe_show_notice() {
		if ( ! current_user_can( 'manage_options' ) || ! self::is_reminder_pending() ) {
			return;
		}

		if ( self::is_plugin_settings_screen() ) {
			return;
		}

		self::render_global_admin_notice();
	}

	/**
	 * Standard WP admin notice (other screens — no ScoreFix admin.css required).
	 *
	 * @return void
	 */
	private static function render_global_admin_notice() {
		$urls = self::get_reminder_action_urls();
		?>
		<div class="notice notice-info scorefix-reminder-notice--wp" data-scorefix-reminder="1">
			<p>
				<strong><?php esc_html_e( 'ScoreFix', 'scorefix' ); ?></strong>
				<?php
				esc_html_e(
					'It has been a while since you last ran a scan. One click keeps your score and issue list up to date.',
					'scorefix'
				);
				?>
			</p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( $urls['scan'] ); ?>"><?php esc_html_e( 'Open ScoreFix & scan', 'scorefix' ); ?></a>
				<a class="button button-link" href="<?php echo esc_url( $urls['dismiss'] ); ?>"><?php esc_html_e( 'Dismiss', 'scorefix' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Inline reminder below the ScoreFix page title (uses plugin admin styles).
	 *
	 * @return void
	 */
	public static function render_dashboard_reminder_banner() {
		if ( ! current_user_can( 'manage_options' ) || ! self::is_reminder_pending() ) {
			return;
		}

		$urls = self::get_reminder_action_urls();
		?>
		<div class="scorefix-reminder-banner" role="status" data-scorefix-reminder="1">
			<div class="scorefix-reminder-banner__text">
				<strong class="scorefix-reminder-banner__label"><?php esc_html_e( 'ScoreFix', 'scorefix' ); ?></strong>
				<span class="scorefix-reminder-banner__msg">
					<?php
					esc_html_e(
						'It has been a while since you last ran a scan. One click keeps your score and issue list up to date.',
						'scorefix'
					);
					?>
				</span>
			</div>
			<div class="scorefix-reminder-banner__actions">
				<a class="button button-primary" href="<?php echo esc_url( $urls['scan'] ); ?>"><?php esc_html_e( 'Run scan now', 'scorefix' ); ?></a>
				<a class="button button-link" href="<?php echo esc_url( $urls['dismiss'] ); ?>"><?php esc_html_e( 'Dismiss', 'scorefix' ); ?></a>
			</div>
		</div>
		<?php
	}

	/**
	 * Clear pending reminder (after scan or dismiss).
	 *
	 * @return void
	 */
	public static function clear_pending() {
		delete_option( self::OPTION_PENDING );
	}

	/**
	 * @return void
	 */
	public function handle_dismiss() {
		$raw = filter_input( INPUT_GET, 'scorefix_reminder_dismiss', FILTER_UNSAFE_RAW );
		if ( null === $raw || false === $raw ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		check_admin_referer( self::NONCE_DISMISS );

		self::clear_pending();

		wp_safe_redirect( admin_url() );
		exit;
	}

	/**
	 * Add default keys for existing installs.
	 *
	 * @return void
	 */
	public function maybe_merge_settings_defaults() {
		$settings = get_option( 'scorefix_settings' );
		if ( ! is_array( $settings ) ) {
			return;
		}

		$defaults = array(
			'reminders_enabled'   => false,
			'reminder_frequency'  => '3months',
			'reminder_email'      => false,
		);

		$changed = false;
		foreach ( $defaults as $key => $value ) {
			if ( ! array_key_exists( $key, $settings ) ) {
				$settings[ $key ] = $value;
				$changed            = true;
			}
		}

		if ( $changed ) {
			update_option( 'scorefix_settings', $settings, false );
		}
	}

	/**
	 * Unschedule cron, then schedule if reminders are on.
	 *
	 * @return void
	 */
	public static function reschedule_from_settings() {
		wp_clear_scheduled_hook( self::CRON_HOOK );

		$settings = get_option( 'scorefix_settings', array() );
		if ( ! is_array( $settings ) || empty( $settings['reminders_enabled'] ) ) {
			return;
		}

		$interval = ( isset( $settings['reminder_frequency'] ) && '6months' === $settings['reminder_frequency'] )
			? 'scorefix_every_6_months'
			: 'scorefix_every_3_months';

		wp_schedule_event( time(), $interval, self::CRON_HOOK );
	}

	/**
	 * Ensure cron exists when reminders are enabled (e.g. after plugin update).
	 *
	 * @return void
	 */
	public static function ensure_cron_scheduled() {
		$settings = get_option( 'scorefix_settings', array() );
		if ( ! is_array( $settings ) || empty( $settings['reminders_enabled'] ) ) {
			return;
		}
		if ( wp_next_scheduled( self::CRON_HOOK ) ) {
			return;
		}
		$interval = ( isset( $settings['reminder_frequency'] ) && '6months' === $settings['reminder_frequency'] )
			? 'scorefix_every_6_months'
			: 'scorefix_every_3_months';
		wp_schedule_event( time(), $interval, self::CRON_HOOK );
	}

	/**
	 * @return void
	 */
	public function ensure_cron_on_load() {
		self::ensure_cron_scheduled();
	}
}
