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
        self::create_token_usage_table();
        update_option( 'abilityhub_version',            ABILITYHUB_VERSION );
        update_option( 'abilityhub_db_version',         '1.1' );
        update_option( 'abilityhub_log_enabled',        1 );
        update_option( 'abilityhub_log_retention_days', 30 );
    }

    /**
     * Run any pending DB migrations for already-active installs.
     * Called on 'plugins_loaded' to handle upgrades without reactivation.
     */
    public static function maybe_upgrade(): void {
        $db_version = get_option( 'abilityhub_db_version', '1.0' );
        if ( version_compare( $db_version, '1.1', '<' ) ) {
            self::create_token_usage_table();
            update_option( 'abilityhub_db_version', '1.1' );
        }
    }

    private static function create_token_usage_table(): void {
        global $wpdb;

        $table           = $wpdb->prefix . 'abilityhub_token_usage';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id                BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            ability           VARCHAR(200)        NOT NULL DEFAULT '',
            provider_id       VARCHAR(100)        NOT NULL DEFAULT '',
            provider_name     VARCHAR(200)        NOT NULL DEFAULT '',
            model_id          VARCHAR(200)        NOT NULL DEFAULT '',
            model_name        VARCHAR(200)        NOT NULL DEFAULT '',
            capability        VARCHAR(50)         NOT NULL DEFAULT 'text_generation',
            prompt_tokens     INT(11)             NOT NULL DEFAULT 0,
            completion_tokens INT(11)             NOT NULL DEFAULT 0,
            total_tokens      INT(11)             NOT NULL DEFAULT 0,
            thought_tokens    INT(11)             NOT NULL DEFAULT 0,
            created_at        DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY ability (ability),
            KEY provider_id (provider_id),
            KEY created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
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
