<?php
/**
 * PSR-4 style autoloader for ScoreFix classes.
 *
 * @package ScoreFix
 */

namespace ScoreFix\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Autoload
 */
class Autoload {

	/**
	 * Base directory for ScoreFix classes.
	 *
	 * @var string
	 */
	private static $base_dir = '';

	/**
	 * Register autoloader.
	 *
	 * @param string $base_dir Plugin root path with trailing slash.
	 * @return void
	 */
	public static function register( $base_dir ) {
		self::$base_dir = rtrim( $base_dir, '/\\' ) . DIRECTORY_SEPARATOR;
		spl_autoload_register( array( __CLASS__, 'load' ) );
	}

	/**
	 * Load class file.
	 *
	 * @param string $class Fully qualified class name.
	 * @return void
	 */
	public static function load( $class ) {
		if ( strpos( $class, 'ScoreFix\\' ) !== 0 ) {
			return;
		}

		$relative = str_replace( array( 'ScoreFix\\', '\\' ), array( '', DIRECTORY_SEPARATOR ), $class );
		// Map PSR-4 namespaces to lowercase WordPress-style directories (core/, admin/, …).
		$parts    = explode( DIRECTORY_SEPARATOR, $relative );
		$basename = array_pop( $parts );
		$dir      = array();
		foreach ( $parts as $part ) {
			$dir[] = strtolower( $part );
		}
		$file = self::$base_dir . implode( DIRECTORY_SEPARATOR, $dir );
		if ( ! empty( $dir ) ) {
			$file .= DIRECTORY_SEPARATOR;
		}
		$file .= $basename . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}
