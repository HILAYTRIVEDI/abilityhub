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
    </div>

    <?php if ( empty( $all_abilities ) ) : ?>
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
                        <th><?php esc_html_e( 'Actions', 'abilityhub' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $all_abilities as $name => $ability ) : ?>
                        <?php
                        // Extract namespace (e.g. 'abilityhub' from 'abilityhub/generate-meta')
                        $namespace  = strstr( $name, '/', true ) ?: $name;
                        $label      = is_object( $ability ) && method_exists( $ability, 'get_label' ) ? $ability->get_label() : $name;
                        $category   = is_object( $ability ) && method_exists( $ability, 'get_category' ) ? $ability->get_category() : '—';
                        $endpoint   = rest_url( 'wp-abilities/v1/abilities/' . rawurlencode( $name ) . '/execute' );
                        $last_run   = $last_executed[ $name ] ?? null;
                        $is_own     = str_starts_with( $name, 'abilityhub/' );
                        ?>
                        <tr class="<?php echo $is_own ? 'abilityhub-table__row--own' : ''; ?>">
                            <td>
                                <code class="abilityhub-code"><?php echo esc_html( $name ); ?></code>
                                <?php if ( $is_own ) : ?>
                                    <span class="abilityhub-badge abilityhub-badge--own">AbilityHub</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html( $label ); ?></td>
                            <td>
                                <?php if ( $category && $category !== '—' ) : ?>
                                    <span class="abilityhub-badge abilityhub-badge--<?php echo esc_attr( $category ); ?>">
                                        <?php echo esc_html( ucfirst( $category ) ); ?>
                                    </span>
                                <?php else : ?>
                                    <span>—</span>
                                <?php endif; ?>
                            </td>
                            <td><code><?php echo esc_html( $namespace ); ?></code></td>
                            <td>
                                <a href="<?php echo esc_url( $endpoint ); ?>" target="_blank" class="abilityhub-link">
                                    <code><?php echo esc_html( '/wp-abilities/v1/abilities/' . $name . '/execute' ); ?></code>
                                </a>
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
                                <button class="button button-small abilityhub-try-btn"
                                        data-ability="<?php echo esc_attr( $name ); ?>"
                                        data-label="<?php echo esc_attr( $label ); ?>"
                                        data-example="{}">
                                    <?php esc_html_e( 'Try', 'abilityhub' ); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</div>

<!-- Reuse the same Try it modal from the store (JS handles it globally) -->
<div id="abilityhub-modal" class="abilityhub-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="abilityhub-modal-title">
    <div class="abilityhub-modal__backdrop"></div>
    <div class="abilityhub-modal__content">
        <div class="abilityhub-modal__header">
            <h2 id="abilityhub-modal-title" class="abilityhub-modal__title"></h2>
            <button class="abilityhub-modal__close" aria-label="<?php esc_attr_e( 'Close', 'abilityhub' ); ?>">✕</button>
        </div>
        <div class="abilityhub-modal__body">
            <div class="abilityhub-field">
                <label for="modal-input"><?php esc_html_e( 'Input (JSON)', 'abilityhub' ); ?></label>
                <textarea id="modal-input" class="abilityhub-textarea abilityhub-textarea--code" rows="8">{}</textarea>
            </div>
            <button id="modal-execute" class="button button-primary abilityhub-button">
                <?php esc_html_e( 'Execute', 'abilityhub' ); ?>
            </button>
            <div id="modal-output" style="display:none;" class="abilityhub-modal__output">
                <div class="abilityhub-output-header">
                    <span id="modal-status" class="abilityhub-output-label"></span>
                    <span id="modal-duration" class="abilityhub-output-meta"></span>
                    <button class="abilityhub-copy-btn" data-target="modal-result"><?php esc_html_e( 'Copy', 'abilityhub' ); ?></button>
                </div>
                <pre id="modal-result" class="abilityhub-output-pre"></pre>
            </div>
        </div>
    </div>
</div>
