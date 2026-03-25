<?php
/**
 * Fired during plugin activation.
 *
 * @package AbilityHub
 */

defined( 'ABSPATH' ) || exit;

class AbilityHub_Activator {

    /**
     * Create required DB tables and set plugin version.
     */
    public static function activate(): void {
        self::create_logs_table();
        update_option( 'abilityhub_version', ABILITYHUB_VERSION );
        update_option( 'abilityhub_log_enabled', 1 );
        update_option( 'abilityhub_log_retention_days', 30 );
    }

    private static function create_logs_table(): void {
        global $wpdb;

        $table           = $wpdb->prefix . 'abilityhub_logs';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            ability     VARCHAR(200)        NOT NULL,
            status      VARCHAR(20)         NOT NULL DEFAULT 'success',
            duration_ms INT(11)             NOT NULL DEFAULT 0,
            user_id     BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            created_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY ability (ability),
            KEY created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}
