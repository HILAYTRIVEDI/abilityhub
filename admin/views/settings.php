<?php
/**
 * Settings view.
 *
 * @package AbilityHub
 */

defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html__( 'You do not have permission to manage settings.', 'abilityhub' ) );
}

$saved             = isset( $_GET['saved'] ) && '1' === $_GET['saved'];
$log_enabled       = get_option( 'abilityhub_log_enabled', 1 );
$retention_days    = get_option( 'abilityhub_log_retention_days', 30 );
$registry_api_key  = get_option( 'abilityhub_registry_api_key', '' );
$provider_name     = AbilityHub_AI_Client::get_provider_name();
$ai_available      = AbilityHub_AI_Client::is_available();
?>

<?php if ( $saved ) : ?>
    <div class="notice notice-success is-dismissible">
        <p><?php esc_html_e( 'Settings saved.', 'abilityhub' ); ?></p>
    </div>
<?php endif; ?>

<div class="abilityhub-settings">

    <form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="abilityhub_save_settings">
        <?php wp_nonce_field( 'abilityhub_settings' ); ?>

        <!-- AI Provider section -->
        <div class="abilityhub-card">
            <div class="abilityhub-card__header">
                <h2><?php esc_html_e( 'AI Provider', 'abilityhub' ); ?></h2>
            </div>
            <div class="abilityhub-card__body">
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Active Provider', 'abilityhub' ); ?></th>
                        <td>
                            <?php if ( $ai_available ) : ?>
                                <span class="abilityhub-provider-name">
                                    <?php echo esc_html( $provider_name ); ?>
                                </span>
                                <p class="description">
                                    <?php esc_html_e( 'The AI provider is configured via the WordPress Connectors API. AbilityHub is provider-agnostic and works with any configured provider.', 'abilityhub' ); ?>
                                </p>
                            <?php else : ?>
                                <span class="abilityhub-provider-name abilityhub-provider-name--unavailable">
                                    <?php echo esc_html( $provider_name ); ?>
                                </span>
                                <p class="description">
                                    <?php esc_html_e( 'WordPress AI Client is not available on this installation. Upgrade to WordPress 7.0+ or install the AI Experiments plugin, then configure an AI provider via Settings > AI.', 'abilityhub' ); ?>
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Ability Namespace', 'abilityhub' ); ?></th>
                        <td>
                            <code>abilityhub</code>
                            <p class="description"><?php esc_html_e( 'All AbilityHub abilities are prefixed with this namespace. This value is fixed.', 'abilityhub' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Logging section -->
        <div class="abilityhub-card">
            <div class="abilityhub-card__header">
                <h2><?php esc_html_e( 'Execution Logging', 'abilityhub' ); ?></h2>
            </div>
            <div class="abilityhub-card__body">
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="log_enabled"><?php esc_html_e( 'Log executions', 'abilityhub' ); ?></label>
                        </th>
                        <td>
                            <label class="abilityhub-toggle">
                                <input type="checkbox"
                                       id="log_enabled"
                                       name="log_enabled"
                                       value="1"
                                       <?php checked( $log_enabled, 1 ); ?>>
                                <span class="abilityhub-toggle__slider"></span>
                            </label>
                            <p class="description">
                                <?php esc_html_e( 'Record every ability execution to the logs table. Disable to stop logging.', 'abilityhub' ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="log_retention_days"><?php esc_html_e( 'Log retention', 'abilityhub' ); ?></label>
                        </th>
                        <td>
                            <input type="number"
                                   id="log_retention_days"
                                   name="log_retention_days"
                                   value="<?php echo esc_attr( $retention_days ); ?>"
                                   min="1"
                                   max="365"
                                   class="small-text">
                            <?php esc_html_e( 'days', 'abilityhub' ); ?>
                            <p class="description">
                                <?php esc_html_e( 'Logs older than this many days are automatically deleted. Default: 30.', 'abilityhub' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Registry section -->
        <div class="abilityhub-card">
            <div class="abilityhub-card__header">
                <h2><?php esc_html_e( 'AbilityHub Registry', 'abilityhub' ); ?></h2>
                <span class="abilityhub-badge abilityhub-badge--coming-soon"><?php esc_html_e( 'Coming Soon', 'abilityhub' ); ?></span>
            </div>
            <div class="abilityhub-card__body">
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="registry_api_key"><?php esc_html_e( 'Registry API Key', 'abilityhub' ); ?></label>
                        </th>
                        <td>
                            <input type="password"
                                   id="registry_api_key"
                                   name="registry_api_key"
                                   value="<?php echo esc_attr( $registry_api_key ); ?>"
                                   class="regular-text"
                                   placeholder="<?php esc_attr_e( 'Enter your AbilityHub Registry key', 'abilityhub' ); ?>"
                                   disabled>
                            <p class="description">
                                <?php esc_html_e( 'Connect to the AbilityHub Registry to browse and install community-built abilities. Coming soon.', 'abilityhub' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- REST API info -->
        <div class="abilityhub-card">
            <div class="abilityhub-card__header">
                <h2><?php esc_html_e( 'REST API', 'abilityhub' ); ?></h2>
            </div>
            <div class="abilityhub-card__body">
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Abilities endpoint', 'abilityhub' ); ?></th>
                        <td>
                            <a href="<?php echo esc_url( rest_url( 'wp-abilities/v1/abilities' ) ); ?>" target="_blank" class="abilityhub-link">
                                <code><?php echo esc_html( rest_url( 'wp-abilities/v1/abilities' ) ); ?></code>
                            </a>
                            <p class="description">
                                <?php esc_html_e( 'All registered abilities are discoverable via this REST endpoint. This is also the MCP discovery URL.', 'abilityhub' ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'MCP endpoint', 'abilityhub' ); ?></th>
                        <td>
                            <a href="<?php echo esc_url( rest_url( 'wp-abilities/v1/abilities' ) ); ?>" target="_blank" class="abilityhub-link">
                                <code><?php echo esc_html( rest_url( 'wp-abilities/v1/abilities' ) ); ?></code>
                            </a>
                            <p class="description">
                                <?php esc_html_e( 'Use the abilityhub/mcp-capability-manifest ability to generate a full MCP-compatible tool manifest from this endpoint.', 'abilityhub' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <?php submit_button( __( 'Save Settings', 'abilityhub' ) ); ?>
    </form>

</div>
