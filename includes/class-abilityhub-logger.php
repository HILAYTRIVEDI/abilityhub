<?php
/**
 * Logs ability executions to the database.
 *
 * @package AbilityHub
 */

defined( 'ABSPATH' ) || exit;

class AbilityHub_Logger {

    /**
     * Log an ability execution.
     *
     * @param array $data {
     *     @type string $ability     Ability name.
     *     @type string $status      'success' or 'error'.
     *     @type int    $duration_ms Execution time in milliseconds.
     * }
     */
    public static function log( array $data ): void {
        if ( ! get_option( 'abilityhub_log_enabled', 1 ) ) {
            return;
        }

        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'abilityhub_logs',
            [
                'ability'     => sanitize_text_field( $data['ability'] ?? '' ),
                'status'      => sanitize_text_field( $data['status'] ?? 'success' ),
                'duration_ms' => absint( $data['duration_ms'] ?? 0 ),
                'user_id'     => get_current_user_id(),
                'created_at'  => current_time( 'mysql' ),
            ],
            [ '%s', '%s', '%d', '%d', '%s' ]
        );
    }

    /**
     * Get execution counts for a time period.
     *
     * @param string $period 'today' or 'week'.
     * @return int
     */
    public static function get_count( string $period = 'today' ): int {
        global $wpdb;

        $table = $wpdb->prefix . 'abilityhub_logs';

        if ( 'today' === $period ) {
            $date = current_time( 'Y-m-d' );
            return (int) $wpdb->get_var(
                $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE DATE(created_at) = %s", $date )
            );
        }

        if ( 'week' === $period ) {
            return (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$table} WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
            );
        }

        return 0;
    }

    /**
     * Get the most-used ability name.
     *
     * @return string
     */
    public static function get_most_used(): string {
        global $wpdb;

        $table  = $wpdb->prefix . 'abilityhub_logs';
        $result = $wpdb->get_var(
            "SELECT ability FROM {$table} GROUP BY ability ORDER BY COUNT(*) DESC LIMIT 1"
        );

        return $result ?? '';
    }

    /**
     * Get paginated log entries.
     *
     * @param array $args {
     *     @type int    $per_page  Rows per page. Default 20.
     *     @type int    $page      Current page. Default 1.
     *     @type string $ability   Filter by ability slug.
     *     @type string $status    Filter by status.
     *     @type string $date_from Start date (Y-m-d).
     *     @type string $date_to   End date (Y-m-d).
     * }
     * @return array{items: array, total: int}
     */
    public static function get_logs( array $args = [] ): array {
        global $wpdb;

        $table    = $wpdb->prefix . 'abilityhub_logs';
        $per_page = absint( $args['per_page'] ?? 20 );
        $page     = max( 1, absint( $args['page'] ?? 1 ) );
        $offset   = ( $page - 1 ) * $per_page;

        $where  = [ '1=1' ];
        $params = [];

        if ( ! empty( $args['ability'] ) ) {
            $where[]  = 'ability = %s';
            $params[] = sanitize_text_field( $args['ability'] );
        }

        if ( ! empty( $args['status'] ) ) {
            $where[]  = 'status = %s';
            $params[] = sanitize_text_field( $args['status'] );
        }

        if ( ! empty( $args['date_from'] ) ) {
            $where[]  = 'DATE(created_at) >= %s';
            $params[] = sanitize_text_field( $args['date_from'] );
        }

        if ( ! empty( $args['date_to'] ) ) {
            $where[]  = 'DATE(created_at) <= %s';
            $params[] = sanitize_text_field( $args['date_to'] );
        }

        $where_clause = implode( ' AND ', $where );

        $count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}";
        $data_sql  = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d";

        if ( ! empty( $params ) ) {
            $total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );
            $items = $wpdb->get_results(
                $wpdb->prepare( $data_sql, array_merge( $params, [ $per_page, $offset ] ) ),
                ARRAY_A
            );
        } else {
            $total = (int) $wpdb->get_var( $count_sql );
            $items = $wpdb->get_results(
                $wpdb->prepare( $data_sql, $per_page, $offset ),
                ARRAY_A
            );
        }

        return [
            'items' => $items ?: [],
            'total' => $total,
        ];
    }

    /**
     * Purge logs older than retention period.
     */
    public static function purge_old_logs(): void {
        global $wpdb;

        $days  = absint( get_option( 'abilityhub_log_retention_days', 30 ) );
        $table = $wpdb->prefix . 'abilityhub_logs';

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            )
        );
    }
}
