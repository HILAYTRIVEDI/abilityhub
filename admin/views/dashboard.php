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
            <strong><?php esc_html_e( 'AI Provider not configured', 'abilityhub' ); ?></strong> 
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
            <?php esc_html_e( 'The WordPress Abilities API is a standardised way to register, discover, and execute AI-powered capabilities on any WordPress site. Think of "abilities" like WordPress blocks  but for AI actions.', 'abilityhub' ); ?>
        </p>
        <ul>
            <li><?php esc_html_e( 'Any plugin can register an ability using wp_register_ability()', 'abilityhub' ); ?></li>
            <li><?php esc_html_e( 'Every ability is automatically available via the REST API at /wp-json/wp-abilities/v1/abilities', 'abilityhub' ); ?></li>
            <li><?php esc_html_e( 'AI calls are provider-agnostic  AbilityHub works with any WordPress AI provider', 'abilityhub' ); ?></li>
            <li><?php esc_html_e( 'The MCP Manifest ability turns your site into a discoverable AI tool server', 'abilityhub' ); ?></li>
        </ul>
    </div>

    <!-- WP Core Abilities Explorer link -->
    <div class="abilityhub-card">
        <div class="abilityhub-card__header">
            <h2><?php esc_html_e( 'Abilities Explorer', 'abilityhub' ); ?></h2>
            <p class="abilityhub-card__subtitle"><?php esc_html_e( 'WordPress includes a built-in tool to browse, inspect, and test every registered ability on this site.', 'abilityhub' ); ?></p>
        </div>
        <div class="abilityhub-card__body">
            <p><?php esc_html_e( 'Use the WordPress AI Abilities Explorer to run any ability interactively, inspect its input schema, and view the raw output — no custom tooling needed.', 'abilityhub' ); ?></p>
            <a href="<?php echo esc_url( admin_url( 'tools.php?page=ai-abilities-explorer' ) ); ?>"
               class="button button-primary abilityhub-button">
                <?php esc_html_e( 'Open Abilities Explorer', 'abilityhub' ); ?> ↗
            </a>
        </div>
    </div>

    <!-- How to use AbilityHub -->
    <div class="abilityhub-card">
        <div class="abilityhub-card__header">
            <h2><?php esc_html_e( 'How to Use AbilityHub', 'abilityhub' ); ?></h2>
        </div>
        <div class="abilityhub-card__body abilityhub-docs">

            <h3><?php esc_html_e( 'What is AbilityHub?', 'abilityhub' ); ?></h3>
            <p>
                <?php esc_html_e( 'AbilityHub is a WordPress plugin that ships 15 production-ready AI abilities built on the WordPress 7.0 Abilities API. Each ability is a named, self-describing AI action (e.g. "generate alt text" or "rewrite tone") that any plugin, theme, or tool can discover and execute — without hard-coding AI logic.', 'abilityhub' ); ?>
            </p>

            <h3><?php esc_html_e( 'Admin Panel Tabs', 'abilityhub' ); ?></h3>
            <table class="widefat abilityhub-docs__table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Tab', 'abilityhub' ); ?></th>
                        <th><?php esc_html_e( 'What it does', 'abilityhub' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong><?php esc_html_e( 'Dashboard', 'abilityhub' ); ?></strong></td>
                        <td><?php esc_html_e( 'Overview stats (executions today/this week, most-used ability) and quick links.', 'abilityhub' ); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e( 'Ability Store', 'abilityhub' ); ?></strong></td>
                        <td><?php esc_html_e( 'Browse all 15 bundled abilities by category. See which are active and view their REST endpoint schema.', 'abilityhub' ); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e( 'Installed', 'abilityhub' ); ?></strong></td>
                        <td><?php esc_html_e( 'Lists every ability currently registered on this site — from AbilityHub and any other plugin using the Abilities API — with last-execution timestamps.', 'abilityhub' ); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e( 'Execution Logs', 'abilityhub' ); ?></strong></td>
                        <td><?php esc_html_e( 'Full history of ability executions: status, duration, triggering user, and input/output payload. Exportable as CSV.', 'abilityhub' ); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e( 'Workflows', 'abilityhub' ); ?></strong></td>
                        <td><?php esc_html_e( 'Define multi-step automated workflows that chain abilities together. Supports an approval queue for outputs that need a human review before applying.', 'abilityhub' ); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e( 'AI Operator', 'abilityhub' ); ?></strong></td>
                        <td><?php esc_html_e( 'A conversational interface — ask it to run abilities, explain results, or perform bulk operations across your content in plain language.', 'abilityhub' ); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e( 'Settings', 'abilityhub' ); ?></strong></td>
                        <td><?php esc_html_e( 'Enable/disable execution logging, set log retention period, and configure your AbilityHub Registry API key for community abilities.', 'abilityhub' ); ?></td>
                    </tr>
                </tbody>
            </table>

            <h3><?php esc_html_e( 'Running an Ability', 'abilityhub' ); ?></h3>
            <ol>
                <li><?php esc_html_e( 'Open the WordPress AI Abilities Explorer via the button above.', 'abilityhub' ); ?></li>
                <li><?php esc_html_e( 'Select any registered ability from the list.', 'abilityhub' ); ?></li>
                <li><?php esc_html_e( 'Fill in the required input fields and click Execute.', 'abilityhub' ); ?></li>
                <li><?php esc_html_e( 'The output appears immediately. Executions are logged in the Execution Logs tab.', 'abilityhub' ); ?></li>
            </ol>

            <h3><?php esc_html_e( 'Calling Abilities from Code', 'abilityhub' ); ?></h3>
            <p><?php esc_html_e( 'Every ability is available via the WordPress REST API:', 'abilityhub' ); ?></p>
            <pre class="abilityhub-output-pre">POST /wp-json/wp-abilities/v1/abilities/{ability-name}/execute
Content-Type: application/json

{ "content": "Your input here" }</pre>
            <p><?php esc_html_e( 'Or call it server-side:', 'abilityhub' ); ?></p>
            <pre class="abilityhub-output-pre">$ability = wp_get_ability( 'abilityhub/summarise-post' );
$result  = $ability->execute( [ 'content' => get_the_content() ] );</pre>

            <h3><?php esc_html_e( 'Registering Your Own Ability', 'abilityhub' ); ?></h3>
            <p><?php esc_html_e( 'Use the WordPress 7.0 Abilities API to register custom abilities from any plugin:', 'abilityhub' ); ?></p>
            <pre class="abilityhub-output-pre">add_action( 'wp_abilities_api_init', function() {
    wp_register_ability( 'my-plugin/my-ability', [
        'label'    => 'My Custom Ability',
        'callback' => function( array $input ): string {
            return wp_ai_client_prompt( 'Do something with: ' . $input['text'] );
        },
        'schema'   => [
            'text' => [ 'type' => 'string', 'required' => true ],
        ],
    ] );
} );</pre>
            <p><?php esc_html_e( 'Once registered, your ability appears automatically in the Installed tab and the Abilities Explorer.', 'abilityhub' ); ?></p>

        </div>
    </div>

</div>
