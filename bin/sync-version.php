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

$root = dirname( __DIR__ );
$path_version = $root . '/VERSION';

if ( ! is_readable( $path_version ) ) {
	fwrite( STDERR, "VERSION missing or unreadable: {$path_version}\n" );
	exit( 1 );
}

$raw = file_get_contents( $path_version );
$ver  = trim( is_string( $raw ) ? $raw : '' );
$pattern_ok = '/^\d+\.\d+\.\d+(?:-[a-zA-Z0-9.]+)?$/';

if ( '' === $ver || 1 !== preg_match( $pattern_ok, $ver ) ) {
	fwrite( STDERR, "VERSION must look like 1.2.3 or 1.2.3-beta.1; got: {$ver}\n" );
	exit( 1 );
}

$path_main = $root . '/scorefix.php';
$path_readme = $root . '/readme.txt';

foreach ( array( $path_main, $path_readme ) as $path ) {
	if ( ! is_readable( $path ) || ! is_writable( $path ) ) {
		fwrite( STDERR, "File missing or not writable: {$path}\n" );
		exit( 1 );
	}
}

$main = file_get_contents( $path_main );
if ( ! is_string( $main ) ) {
	fwrite( STDERR, "Cannot read scorefix.php\n" );
	exit( 1 );
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
	fwrite( STDERR, "scorefix.php: expected 1 header Version line and 1 SCOREFIX_VERSION define; got header={$n_header}, define={$n_define}\n" );
	exit( 1 );
}

$readme = file_get_contents( $path_readme );
if ( ! is_string( $readme ) ) {
	fwrite( STDERR, "Cannot read readme.txt\n" );
	exit( 1 );
}

$readme = preg_replace( '/^Stable tag:\s*\S+/m', 'Stable tag: ' . $ver, $readme, 1, $n_stable );

if ( 1 !== $n_stable ) {
	fwrite( STDERR, "readme.txt: expected 1 Stable tag line; got {$n_stable}\n" );
	exit( 1 );
}

if ( file_put_contents( $path_main, $main ) === false || file_put_contents( $path_readme, $readme ) === false ) {
	fwrite( STDERR, "Write failed.\n" );
	exit( 1 );
}

echo "Synced version {$ver} from VERSION into scorefix.php and readme.txt.\n";
