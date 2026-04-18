<?php
/**
 * Plugin Name:       ScoreFix – Boost Lighthouse & Improve UX
 * Description:       Fix the issues hurting your Lighthouse score and conversions in one click. No coding required.
 * Version:           1.0.6
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Carles Vargas
 * Author URI:        https://carvar.es/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       scorefix
 * Domain Path:       /languages
 *
 * @package ScoreFix
 */

defined( 'ABSPATH' ) || exit;

define( 'SCOREFIX_VERSION', '1.0.6' );
define( 'SCOREFIX_PLUGIN_FILE', __FILE__ );
define( 'SCOREFIX_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SCOREFIX_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SCOREFIX_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once SCOREFIX_PLUGIN_DIR . 'core/Autoload.php';

ScoreFix\Core\Autoload::register( SCOREFIX_PLUGIN_DIR );

register_activation_hook( __FILE__, array( 'ScoreFix\Core\Plugin', 'activate' ) );

add_action( 'plugins_loaded', array( 'ScoreFix\Scanner\CaptureRequest', 'bootstrap' ), 2 );
add_action( 'plugins_loaded', array( 'ScoreFix\Core\Plugin', 'init' ), 5 );
