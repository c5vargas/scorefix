#!/usr/bin/env php
<?php
/**
 * Sync plugin version from VERSION file into scorefix.php and readme.txt.
 *
 * Usage (repo root): php bin/sync-version.php
 *
 * @package ScoreFix
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	if ( ! in_array( PHP_SAPI, array( 'cli', 'phpdbg' ), true ) ) {
		exit;
	}
} else {
	exit;
}

/**
 * Print a line to stderr (CLI tool; WP_Filesystem not available).
 *
 * @param string $message Message.
 * @return void
 */
function scorefix_sync_version_stderr( string $message ): void {
	file_put_contents( 'php://stderr', $message );
}

/**
 * Run sync; exit code 0 ok, 1 error.
 *
 * @return int
 */
function scorefix_sync_version_run(): int {
	$root = dirname( __DIR__ );
	$path_version = $root . '/VERSION';

	if ( ! is_readable( $path_version ) ) {
		scorefix_sync_version_stderr( 'VERSION missing or unreadable: ' . $path_version . "\n" );
		return 1;
	}

	$raw        = file_get_contents( $path_version );
	$ver        = trim( is_string( $raw ) ? $raw : '' );
	$pattern_ok = '/^\d+\.\d+\.\d+(?:-[a-zA-Z0-9.]+)?$/';

	if ( '' === $ver || 1 !== preg_match( $pattern_ok, $ver ) ) {
		scorefix_sync_version_stderr( 'VERSION must look like 1.2.3 or 1.2.3-beta.1; got: ' . $ver . "\n" );
		return 1;
	}

	$path_main   = $root . '/scorefix.php';
	$path_readme = $root . '/readme.txt';

	foreach ( array( $path_main, $path_readme ) as $path ) {
		if ( ! is_readable( $path ) ) {
			scorefix_sync_version_stderr( 'File missing or unreadable: ' . $path . "\n" );
			return 1;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- CLI maint script; no WP bootstrap.
		if ( ! is_writable( $path ) ) {
			scorefix_sync_version_stderr( 'File not writable: ' . $path . "\n" );
			return 1;
		}
	}

	$main = file_get_contents( $path_main );
	if ( ! is_string( $main ) ) {
		scorefix_sync_version_stderr( "Cannot read scorefix.php\n" );
		return 1;
	}

	$main = preg_replace( '/^ \* Version:\s+\S+/m', ' * Version:           ' . $ver, $main, 1, $n_header );
	$main = preg_replace(
		"/define\\(\\s*'SCOREFIX_VERSION'\\s*,\\s*'[^']*'\\s*\\)\\s*;/",
		"define( 'SCOREFIX_VERSION', '{$ver}' );",
		$main,
		-1,
		$n_define
	);

	if ( 1 !== $n_header || 1 !== $n_define ) {
		scorefix_sync_version_stderr( "scorefix.php: expected 1 header Version line and 1 SCOREFIX_VERSION define; got header={$n_header}, define={$n_define}\n" );
		return 1;
	}

	$readme = file_get_contents( $path_readme );
	if ( ! is_string( $readme ) ) {
		scorefix_sync_version_stderr( "Cannot read readme.txt\n" );
		return 1;
	}

	$readme = preg_replace( '/^Stable tag:\s*\S+/m', 'Stable tag: ' . $ver, $readme, 1, $n_stable );

	if ( 1 !== $n_stable ) {
		scorefix_sync_version_stderr( "readme.txt: expected 1 Stable tag line; got {$n_stable}\n" );
		return 1;
	}

	if ( file_put_contents( $path_main, $main ) === false || file_put_contents( $path_readme, $readme ) === false ) {
		scorefix_sync_version_stderr( "Write failed.\n" );
		return 1;
	}

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI stdout; $ver validated by semver regex above; WP esc_* unavailable without bootstrap.
	echo 'Synced version ' . $ver . " from VERSION into scorefix.php and readme.txt.\n";

	return 0;
}

$scorefix_sync_exit_code = scorefix_sync_version_run();
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Process exit code (int), not HTML output.
exit( (int) $scorefix_sync_exit_code );
