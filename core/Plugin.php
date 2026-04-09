<?php
/**
 * Plugin bootstrap.
 *
 * @package ScoreFix
 */

namespace ScoreFix\Core;

use ScoreFix\Admin\ActionsController;
use ScoreFix\Admin\DashboardPage;
use ScoreFix\Fixes\FixEngine;
use ScoreFix\Frontend\RenderHooks;

defined( 'ABSPATH' ) || exit;

/**
 * Class Plugin
 */
class Plugin {

	/**
	 * Loader instance.
	 *
	 * @var Loader|null
	 */
	private static $loader = null;

	/**
	 * Plugin activation: default options.
	 *
	 * @return void
	 */
	public static function activate() {
		$defaults = array(
			'fixes_enabled' => false,
			'version'       => SCOREFIX_VERSION,
		);
		if ( false === get_option( 'scorefix_settings' ) ) {
			add_option( 'scorefix_settings', $defaults );
		}
	}

	/**
	 * Initialize plugin.
	 *
	 * @return void
	 */
	public static function init() {
		self::$loader = new Loader();

		$dashboard = new DashboardPage();
		$actions   = new ActionsController();

		self::$loader->add_action( 'admin_menu', $dashboard, 'register_menu', 10, 0 );
		self::$loader->add_action( 'admin_enqueue_scripts', $dashboard, 'enqueue_assets', 10, 1 );
		self::$loader->add_action( 'admin_init', $actions, 'handle_actions', 10, 0 );

		$fix_engine = new FixEngine();
		$render     = new RenderHooks( $fix_engine );

		if ( self::fixes_enabled() ) {
			$render->register( self::$loader );
		}

		self::$loader->run();
	}

	/**
	 * Whether automatic fixes are enabled (runtime).
	 *
	 * @return bool
	 */
	public static function fixes_enabled() {
		$settings = get_option( 'scorefix_settings', array() );
		return ! empty( $settings['fixes_enabled'] );
	}

	/**
	 * Re-register frontend hooks when fixes toggled in same request (optional).
	 * For simplicity, user is redirected after toggle.
	 *
	 * @return Loader|null
	 */
	public static function get_loader() {
		return self::$loader;
	}
}
