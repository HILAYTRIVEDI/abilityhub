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
    'abilityhub_saved_workflows',
    'abilityhub_deactivated_workflows',
];

foreach ( $options as $option ) {
    delete_option( $option );
}

// Delete all pending-approval CPT posts
$pending_posts = get_posts( [
    'post_type'      => 'abilityhub_pending',
    'post_status'    => [ 'pending', 'publish', 'trash' ],
    'numberposts'    => -1,
    'fields'         => 'ids',
    'no_found_rows'  => true,
] );

foreach ( $pending_posts as $post_id ) {
    wp_delete_post( $post_id, true );
}

// Delete all batch job CPT posts
$batch_posts = get_posts( [
    'post_type'      => 'abilityhub_batch',
    'post_status'    => [ 'pending', 'publish', 'trash' ],
    'numberposts'    => -1,
    'fields'         => 'ids',
    'no_found_rows'  => true,
] );

foreach ( $batch_posts as $post_id ) {
    wp_delete_post( $post_id, true );
}

// Clear the scheduled cron events
wp_clear_scheduled_hook( 'abilityhub_daily_cleanup' );
wp_clear_scheduled_hook( 'abilityhub_process_batch' );
