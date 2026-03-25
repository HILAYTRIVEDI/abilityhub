<?php
/**
 * Dashboard view.
 *
 * @package AbilityHub
 */

defined( 'ABSPATH' ) || exit;

$count_today  = AbilityHub_Logger::get_count( 'today' );
$count_week   = AbilityHub_Logger::get_count( 'week' );
$most_used    = AbilityHub_Logger::get_most_used();
$ai_available = AbilityHub_AI_Client::is_available();

// Count installed abilities
$total_abilities = 0;
if ( function_exists( 'wp_get_abilities' ) ) {
    $total_abilities = count( wp_get_abilities() );
}
?>

<?php if ( ! $ai_available ) : ?>
    <div class="notice notice-warning abilityhub-notice">
        <p>
            <strong><?php esc_html_e( 'AI Provider not configured', 'abilityhub' ); ?></strong> —
            <?php esc_html_e( 'AbilityHub requires WordPress 7.0+ with an AI provider configured via the WordPress AI Client. Abilities will return errors until an AI provider is connected.', 'abilityhub' ); ?>
        </p>
    </div>
<?php endif; ?>

<div class="abilityhub-dashboard">

    <!-- Stats row -->
    <div class="abilityhub-stats-grid">
        <div class="abilityhub-stat-card">
            <div class="abilityhub-stat-card__icon">⚡</div>
            <div class="abilityhub-stat-card__value"><?php echo esc_html( $total_abilities ); ?></div>
            <div class="abilityhub-stat-card__label"><?php esc_html_e( 'Abilities Installed', 'abilityhub' ); ?></div>
        </div>
        <div class="abilityhub-stat-card">
            <div class="abilityhub-stat-card__icon">📅</div>
            <div class="abilityhub-stat-card__value"><?php echo esc_html( $count_today ); ?></div>
            <div class="abilityhub-stat-card__label"><?php esc_html_e( 'Executions Today', 'abilityhub' ); ?></div>
        </div>
        <div class="abilityhub-stat-card">
            <div class="abilityhub-stat-card__icon">📊</div>
            <div class="abilityhub-stat-card__value"><?php echo esc_html( $count_week ); ?></div>
            <div class="abilityhub-stat-card__label"><?php esc_html_e( 'Executions This Week', 'abilityhub' ); ?></div>
        </div>
        <div class="abilityhub-stat-card">
            <div class="abilityhub-stat-card__icon">🏆</div>
            <div class="abilityhub-stat-card__value abilityhub-stat-card__value--small">
                <?php echo $most_used ? esc_html( $most_used ) : esc_html__( 'None yet', 'abilityhub' ); ?>
            </div>
            <div class="abilityhub-stat-card__label"><?php esc_html_e( 'Most Used Ability', 'abilityhub' ); ?></div>
        </div>
    </div>

    <!-- WordPress 7.0 Abilities API info box -->
    <div class="abilityhub-info-box">
        <h2><?php esc_html_e( 'New in WordPress 7.0: The Abilities API', 'abilityhub' ); ?> 🎉</h2>
        <p>
            <?php esc_html_e( 'The WordPress Abilities API is a standardised way to register, discover, and execute AI-powered capabilities on any WordPress site. Think of "abilities" like WordPress blocks — but for AI actions.', 'abilityhub' ); ?>
        </p>
        <ul>
            <li><?php esc_html_e( 'Any plugin can register an ability using wp_register_ability()', 'abilityhub' ); ?></li>
            <li><?php esc_html_e( 'Every ability is automatically available via the REST API at /wp-json/wp-abilities/v1/abilities', 'abilityhub' ); ?></li>
            <li><?php esc_html_e( 'AI calls are provider-agnostic — AbilityHub works with any WordPress AI provider', 'abilityhub' ); ?></li>
            <li><?php esc_html_e( 'The MCP Manifest ability turns your site into a discoverable AI tool server', 'abilityhub' ); ?></li>
        </ul>
    </div>

    <!-- Quick Execute Panel -->
    <div class="abilityhub-card">
        <div class="abilityhub-card__header">
            <h2><?php esc_html_e( 'Quick Execute', 'abilityhub' ); ?></h2>
            <p class="abilityhub-card__subtitle"><?php esc_html_e( 'Test any registered ability directly from this dashboard.', 'abilityhub' ); ?></p>
        </div>
        <div class="abilityhub-card__body">
            <div class="abilityhub-quick-execute">
                <div class="abilityhub-quick-execute__controls">
                    <div class="abilityhub-field">
                        <label for="qe-ability"><?php esc_html_e( 'Ability', 'abilityhub' ); ?></label>
                        <select id="qe-ability" class="abilityhub-select">
                            <option value=""><?php esc_html_e( '— Select an ability —', 'abilityhub' ); ?></option>
                            <?php
                            $store_abilities = AbilityHub_Admin::get_store_abilities();
                            foreach ( $store_abilities as $ability ) :
                            ?>
                                <option value="<?php echo esc_attr( $ability['name'] ); ?>"
                                        data-example="<?php echo esc_attr( wp_json_encode( $ability['example'] ) ); ?>">
                                    <?php echo esc_html( $ability['label'] ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="abilityhub-field">
                        <label for="qe-input"><?php esc_html_e( 'Input (JSON)', 'abilityhub' ); ?></label>
                        <textarea id="qe-input" class="abilityhub-textarea abilityhub-textarea--code" rows="6" placeholder="{}">{}</textarea>
                    </div>
                    <button id="qe-run" class="button button-primary abilityhub-button">
                        <?php esc_html_e( 'Execute Ability', 'abilityhub' ); ?>
                    </button>
                </div>
                <div class="abilityhub-quick-execute__output" id="qe-output" style="display:none;">
                    <div class="abilityhub-output-header">
                        <span class="abilityhub-output-label"><?php esc_html_e( 'Output', 'abilityhub' ); ?></span>
                        <button class="abilityhub-copy-btn" data-target="qe-result"><?php esc_html_e( 'Copy', 'abilityhub' ); ?></button>
                    </div>
                    <pre id="qe-result" class="abilityhub-output-pre"></pre>
                </div>
            </div>
        </div>
    </div>

</div>
