<?php
/**
 * Execution Logs view.
 *
 * @package AbilityHub
 */

defined( 'ABSPATH' ) || exit;

// Filters
$filter_ability   = sanitize_text_field( $_GET['filter_ability'] ?? '' );
$filter_status    = sanitize_key( $_GET['filter_status'] ?? '' );
$filter_date_from = sanitize_text_field( $_GET['date_from'] ?? '' );
$filter_date_to   = sanitize_text_field( $_GET['date_to'] ?? '' );
$current_page     = max( 1, absint( $_GET['paged'] ?? 1 ) );
$per_page         = 20;

$logs = AbilityHub_Logger::get_logs( [
    'per_page'  => $per_page,
    'page'      => $current_page,
    'ability'   => $filter_ability,
    'status'    => $filter_status,
    'date_from' => $filter_date_from,
    'date_to'   => $filter_date_to,
] );

$total_pages = ceil( $logs['total'] / $per_page );
$base_url    = add_query_arg( [ 'page' => 'abilityhub', 'tab' => 'logs' ], admin_url( 'admin.php' ) );
?>

<div class="abilityhub-logs">

    <div class="abilityhub-logs__header">
        <h2><?php esc_html_e( 'Execution Logs', 'abilityhub' ); ?></h2>
        <div class="abilityhub-logs__actions">
            <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-ajax.php?action=abilityhub_export_logs' ), 'abilityhub_nonce', 'nonce' ) ); ?>"
               class="button">
                ⬇ <?php esc_html_e( 'Export CSV', 'abilityhub' ); ?>
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="abilityhub-filters">
        <form method="GET" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="abilityhub-filters__form">
            <input type="hidden" name="page" value="abilityhub">
            <input type="hidden" name="tab" value="logs">

            <div class="abilityhub-filters__row">
                <div class="abilityhub-field abilityhub-field--inline">
                    <label for="filter_ability"><?php esc_html_e( 'Ability', 'abilityhub' ); ?></label>
                    <input type="text"
                           id="filter_ability"
                           name="filter_ability"
                           value="<?php echo esc_attr( $filter_ability ); ?>"
                           placeholder="<?php esc_attr_e( 'e.g. abilityhub/generate-meta-description', 'abilityhub' ); ?>"
                           class="regular-text">
                </div>

                <div class="abilityhub-field abilityhub-field--inline">
                    <label for="filter_status"><?php esc_html_e( 'Status', 'abilityhub' ); ?></label>
                    <select id="filter_status" name="filter_status">
                        <option value=""><?php esc_html_e( 'All', 'abilityhub' ); ?></option>
                        <option value="success" <?php selected( $filter_status, 'success' ); ?>><?php esc_html_e( 'Success', 'abilityhub' ); ?></option>
                        <option value="error" <?php selected( $filter_status, 'error' ); ?>><?php esc_html_e( 'Error', 'abilityhub' ); ?></option>
                    </select>
                </div>

                <div class="abilityhub-field abilityhub-field--inline">
                    <label for="date_from"><?php esc_html_e( 'From', 'abilityhub' ); ?></label>
                    <input type="date" id="date_from" name="date_from" value="<?php echo esc_attr( $filter_date_from ); ?>">
                </div>

                <div class="abilityhub-field abilityhub-field--inline">
                    <label for="date_to"><?php esc_html_e( 'To', 'abilityhub' ); ?></label>
                    <input type="date" id="date_to" name="date_to" value="<?php echo esc_attr( $filter_date_to ); ?>">
                </div>

                <button type="submit" class="button"><?php esc_html_e( 'Filter', 'abilityhub' ); ?></button>
                <a href="<?php echo esc_url( $base_url ); ?>" class="button"><?php esc_html_e( 'Reset', 'abilityhub' ); ?></a>
            </div>
        </form>
    </div>

    <!-- Results count -->
    <div class="abilityhub-table-meta">
        <?php
        printf(
            /* translators: 1: number of results */
            esc_html__( 'Showing %1$d results', 'abilityhub' ),
            count( $logs['items'] )
        );
        if ( $logs['total'] > $per_page ) {
            printf(
                /* translators: 1: total count */
                esc_html__( ' of %1$d total', 'abilityhub' ),
                $logs['total']
            );
        }
        ?>
    </div>

    <!-- Logs table -->
    <?php if ( empty( $logs['items'] ) ) : ?>
        <div class="abilityhub-empty-state">
            <span class="abilityhub-empty-state__icon">📋</span>
            <h3><?php esc_html_e( 'No logs found', 'abilityhub' ); ?></h3>
            <p><?php esc_html_e( 'Ability executions will be logged here once abilities are used.', 'abilityhub' ); ?></p>
        </div>
    <?php else : ?>
        <div class="abilityhub-table-wrapper">
            <table class="widefat abilityhub-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Ability', 'abilityhub' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'abilityhub' ); ?></th>
                        <th><?php esc_html_e( 'Duration', 'abilityhub' ); ?></th>
                        <th><?php esc_html_e( 'User', 'abilityhub' ); ?></th>
                        <th><?php esc_html_e( 'Timestamp', 'abilityhub' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $logs['items'] as $log ) : ?>
                        <?php
                        $user      = get_userdata( absint( $log['user_id'] ) );
                        $user_name = $user ? $user->display_name : __( 'System', 'abilityhub' );
                        ?>
                        <tr>
                            <td>
                                <code class="abilityhub-code"><?php echo esc_html( $log['ability'] ); ?></code>
                            </td>
                            <td>
                                <span class="abilityhub-status abilityhub-status--<?php echo esc_attr( $log['status'] ); ?>">
                                    <?php echo 'success' === $log['status'] ? '✓' : '✗'; ?>
                                    <?php echo esc_html( ucfirst( $log['status'] ) ); ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                $ms = absint( $log['duration_ms'] );
                                if ( $ms >= 1000 ) {
                                    echo esc_html( number_format( $ms / 1000, 2 ) . 's' );
                                } else {
                                    echo esc_html( $ms . 'ms' );
                                }
                                ?>
                            </td>
                            <td><?php echo esc_html( $user_name ); ?></td>
                            <td>
                                <span title="<?php echo esc_attr( $log['created_at'] ); ?>">
                                    <?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $log['created_at'] ) ) ); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ( $total_pages > 1 ) : ?>
            <div class="abilityhub-pagination">
                <?php if ( $current_page > 1 ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( 'paged', $current_page - 1, $base_url ) ); ?>" class="button">
                        &larr; <?php esc_html_e( 'Previous', 'abilityhub' ); ?>
                    </a>
                <?php endif; ?>

                <span class="abilityhub-pagination__info">
                    <?php
                    printf(
                        /* translators: 1: current page, 2: total pages */
                        esc_html__( 'Page %1$d of %2$d', 'abilityhub' ),
                        $current_page,
                        $total_pages
                    );
                    ?>
                </span>

                <?php if ( $current_page < $total_pages ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( 'paged', $current_page + 1, $base_url ) ); ?>" class="button">
                        <?php esc_html_e( 'Next', 'abilityhub' ); ?> &rarr;
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

</div>
