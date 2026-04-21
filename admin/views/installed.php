<?php
/**
 * Installed abilities view — shows ALL registered abilities on the site.
 *
 * @package AbilityHub
 */

defined( 'ABSPATH' ) || exit;

$all_abilities = [];
if ( function_exists( 'wp_get_abilities' ) ) {
    $all_abilities = wp_get_abilities();
}

// Abilities explicitly disabled by the admin (stored as array of slugs).
$disabled_abilities = (array) get_option( 'abilityhub_disabled_abilities', [] );

// Get last execution for each ability
global $wpdb;
$last_executed = [];
if ( ! empty( $all_abilities ) ) {
    $table        = $wpdb->prefix . 'abilityhub_logs';
    $ability_list = implode( "','", array_map( 'esc_sql', array_keys( $all_abilities ) ) );
    $rows         = $wpdb->get_results(
        "SELECT ability, MAX(created_at) as last_run FROM {$table} WHERE ability IN ('{$ability_list}') GROUP BY ability",
        ARRAY_A
    );
    foreach ( $rows as $row ) {
        $last_executed[ $row['ability'] ] = $row['last_run'];
    }
}

$can_manage = current_user_can( 'manage_options' );
?>

<div class="abilityhub-installed">

    <div class="abilityhub-installed__header">
        <h2>
            <?php
            /* translators: %d: number of abilities */
            printf( esc_html__( 'All Registered Abilities (%d)', 'abilityhub' ), count( $all_abilities ) );
            ?>
        </h2>
        <p class="abilityhub-installed__subtitle">
            <?php esc_html_e( 'This table shows every ability registered on this site — from AbilityHub and any other plugin using the WordPress 7.0 Abilities API.', 'abilityhub' ); ?>
        </p>
        <?php if ( ! empty( $disabled_abilities ) && $can_manage ) : ?>
            <p class="abilityhub-installed__disabled-note">
                <?php
                /* translators: %d: number of disabled abilities */
                printf(
                    esc_html__( '%d %s currently disabled and will not be registered or available via REST.', 'abilityhub' ),
                    count( $disabled_abilities ),
                    _n( 'ability is', 'abilities are', count( $disabled_abilities ), 'abilityhub' )
                );
                ?>
            </p>
        <?php endif; ?>
    </div>

    <?php if ( empty( $all_abilities ) && empty( $disabled_abilities ) ) : ?>
        <div class="abilityhub-empty-state">
            <span class="abilityhub-empty-state__icon">⚡</span>
            <h3><?php esc_html_e( 'No abilities registered yet', 'abilityhub' ); ?></h3>
            <p>
                <?php esc_html_e( 'Abilities appear here once the WordPress 7.0 Abilities API initialises on the ', 'abilityhub' ); ?>
                <code>wp_abilities_api_init</code>
                <?php esc_html_e( ' hook.', 'abilityhub' ); ?>
            </p>
        </div>
    <?php else : ?>
        <div class="abilityhub-table-wrapper">
            <table class="widefat abilityhub-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Ability Name', 'abilityhub' ); ?></th>
                        <th><?php esc_html_e( 'Label', 'abilityhub' ); ?></th>
                        <th><?php esc_html_e( 'Category', 'abilityhub' ); ?></th>
                        <th><?php esc_html_e( 'Namespace', 'abilityhub' ); ?></th>
                        <th><?php esc_html_e( 'REST Endpoint', 'abilityhub' ); ?></th>
                        <th><?php esc_html_e( 'Last Executed', 'abilityhub' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'abilityhub' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'abilityhub' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Merge registered abilities with disabled (unregistered) AbilityHub abilities
                    // so disabled ones still appear in the table.
                    $store_ability_names = array_column( AbilityHub_Admin::get_store_abilities(), 'name' );
                    $all_ability_keys    = array_unique(
                        array_merge( array_keys( $all_abilities ), $disabled_abilities )
                    );
                    sort( $all_ability_keys );

                    foreach ( $all_ability_keys as $name ) :
                        $is_registered = isset( $all_abilities[ $name ] );
                        $ability       = $is_registered ? $all_abilities[ $name ] : null;
                        $is_own        = str_starts_with( $name, 'abilityhub/' );
                        $is_disabled   = in_array( $name, $disabled_abilities, true );

                        // Resolve label / category from the registered object or store metadata.
                        if ( $is_registered ) {
                            $label    = is_object( $ability ) && method_exists( $ability, 'get_label' )    ? $ability->get_label()    : $name;
                            $category = is_object( $ability ) && method_exists( $ability, 'get_category' ) ? $ability->get_category() : '—';
                        } else {
                            // Ability is disabled — look up metadata from store list.
                            $store_meta = array_values( array_filter(
                                AbilityHub_Admin::get_store_abilities(),
                                static fn( $a ) => $a['name'] === $name
                            ) );
                            $label    = $store_meta[0]['label']    ?? $name;
                            $category = $store_meta[0]['category'] ?? '—';
                        }

                        $namespace = strstr( $name, '/', true ) ?: $name;
                        $endpoint  = rest_url( 'wp-abilities/v1/abilities/' . rawurlencode( $name ) . '/execute' );
                        $last_run  = $last_executed[ $name ] ?? null;
                    ?>
                        <tr class="<?php echo $is_own ? 'abilityhub-table__row--own' : ''; ?> <?php echo $is_disabled ? 'abilityhub-table__row--disabled' : ''; ?>"
                            data-ability="<?php echo esc_attr( $name ); ?>">
                            <td>
                                <code class="abilityhub-code"><?php echo esc_html( $name ); ?></code>
                                <?php if ( $is_own ) : ?>
                                    <span class="abilityhub-badge abilityhub-badge--own">AbilityHub</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html( $label ); ?></td>
                            <td>
                                <?php if ( $category && '—' !== $category ) : ?>
                                    <span class="abilityhub-badge abilityhub-badge--<?php echo esc_attr( $category ); ?>">
                                        <?php echo esc_html( ucfirst( $category ) ); ?>
                                    </span>
                                <?php else : ?>
                                    <span>—</span>
                                <?php endif; ?>
                            </td>
                            <td><code><?php echo esc_html( $namespace ); ?></code></td>
                            <td>
                                <?php if ( ! $is_disabled ) : ?>
                                    <a href="<?php echo esc_url( $endpoint ); ?>" target="_blank" class="abilityhub-link">
                                        <code><?php echo esc_html( '/wp-abilities/v1/abilities/' . $name . '/execute' ); ?></code>
                                    </a>
                                <?php else : ?>
                                    <span class="abilityhub-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ( $last_run ) : ?>
                                    <span title="<?php echo esc_attr( $last_run ); ?>">
                                        <?php echo esc_html( human_time_diff( strtotime( $last_run ), time() ) . ' ' . __( 'ago', 'abilityhub' ) ); ?>
                                    </span>
                                <?php else : ?>
                                    <span class="abilityhub-muted"><?php esc_html_e( 'Never', 'abilityhub' ); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ( $is_disabled ) : ?>
                                    <span class="abilityhub-status-badge abilityhub-status-badge--disabled">
                                        <?php esc_html_e( 'Disabled', 'abilityhub' ); ?>
                                    </span>
                                <?php else : ?>
                                    <span class="abilityhub-status-badge abilityhub-status-badge--enabled">
                                        <?php esc_html_e( 'Enabled', 'abilityhub' ); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="abilityhub-actions-cell">
                                <?php if ( ! $is_disabled ) : ?>
                                    <a href="<?php echo esc_url( admin_url( 'tools.php?page=ai-abilities-explorer' ) ); ?>"
                                       class="button button-small">
                                        <?php esc_html_e( 'Try', 'abilityhub' ); ?> ↗
                                    </a>
                                <?php endif; ?>

                                <?php if ( $can_manage && $is_own ) : ?>
                                    <?php if ( $is_disabled ) : ?>
                                        <button type="button"
                                                class="button button-small abilityhub-toggle-ability"
                                                data-ability="<?php echo esc_attr( $name ); ?>"
                                                data-enable="1">
                                            <?php esc_html_e( 'Enable', 'abilityhub' ); ?>
                                        </button>
                                    <?php else : ?>
                                        <button type="button"
                                                class="button button-small abilityhub-toggle-ability"
                                                data-ability="<?php echo esc_attr( $name ); ?>"
                                                data-enable="0">
                                            <?php esc_html_e( 'Disable', 'abilityhub' ); ?>
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</div>
