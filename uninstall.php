<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * Removes the logs table and all plugin options.
 *
 * @package AbilityHub
 */

// If uninstall.php is not called by WordPress, exit immediately.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Drop the logs table
$table = $wpdb->prefix . 'abilityhub_logs';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

// Delete all plugin options
$options = [
    'abilityhub_version',
    'abilityhub_log_enabled',
    'abilityhub_log_retention_days',
    'abilityhub_registry_api_key',
];

foreach ( $options as $option ) {
    delete_option( $option );
}

// Clear the scheduled cron event
wp_clear_scheduled_hook( 'abilityhub_daily_cleanup' );
