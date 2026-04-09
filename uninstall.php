<?php
/**
 * Uninstall ScoreFix — remove options.
 *
 * @package ScoreFix
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'scorefix_settings' );
delete_option( 'scorefix_last_scan' );
delete_option( 'scorefix_fix_stats' );
