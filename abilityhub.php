<?php
/**
 * Plugin Name:       AbilityHub
 * Plugin URI:        https://github.com/HILAYTRIVEDI
 * Description:       The marketplace for WordPress AI abilities. Browse, install, and build composable AI capabilities built natively on the WordPress 7.0 Abilities API and WP AI Client. WooCommerce abilities require WooCommerce.
 * Version:           1.0.0
 * Requires at least: 7.0
 * Requires PHP:      7.4
 * Author:            Hilay Trivedi
 * Author URI:        https://github.com/HILAYTRIVEDI
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       abilityhub
 * Domain Path:       /languages
 * AbilityHub:        true
 */

defined( 'ABSPATH' ) || exit;

define( 'ABILITYHUB_VERSION',     '1.0.0' );
define( 'ABILITYHUB_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'ABILITYHUB_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'ABILITYHUB_PLUGIN_FILE', __FILE__ );
define( 'ABILITYHUB_NAMESPACE',   'abilityhub' );

// Autoload includes/ and admin/ classes
spl_autoload_register( function ( $class ) {
    $prefix = 'AbilityHub_';
    if ( strpos( $class, $prefix ) !== 0 ) {
        return;
    }

    $filename = 'class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';

    // Look in includes/ first, then admin/
    $candidates = [
        ABILITYHUB_PLUGIN_DIR . 'includes/' . $filename,
        ABILITYHUB_PLUGIN_DIR . 'admin/'    . $filename,
    ];

    foreach ( $candidates as $file ) {
        if ( file_exists( $file ) ) {
            require_once $file;
            return;
        }
    }
} );

// Load ability base class and all ability classes manually (not in includes/)
function abilityhub_load_ability_files(): void {
    require_once ABILITYHUB_PLUGIN_DIR . 'abilities/class-abilityhub-ability-base.php';

    $ability_dirs = [
        ABILITYHUB_PLUGIN_DIR . 'abilities/content/',
        ABILITYHUB_PLUGIN_DIR . 'abilities/woocommerce/',
        ABILITYHUB_PLUGIN_DIR . 'abilities/developer/',
        ABILITYHUB_PLUGIN_DIR . 'abilities/media/',
        ABILITYHUB_PLUGIN_DIR . 'abilities/site/',
    ];

    foreach ( $ability_dirs as $dir ) {
        if ( ! is_dir( $dir ) ) {
            continue;
        }
        foreach ( glob( $dir . 'class-ability-*.php' ) as $file ) {
            require_once $file;
        }
    }
}
abilityhub_load_ability_files();

register_activation_hook( __FILE__, [ 'AbilityHub_Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'AbilityHub_Deactivator', 'deactivate' ] );
add_action( 'plugins_loaded', [ 'AbilityHub_Activator', 'maybe_upgrade' ] );

function abilityhub_run(): void {
    $loader = new AbilityHub_Loader();
    $loader->run();
}
abilityhub_run();

// -------------------------------------------------------------------------
// v2: Workflow Engine
// -------------------------------------------------------------------------

require_once ABILITYHUB_PLUGIN_DIR . 'includes/workflows/class-workflow.php';
require_once ABILITYHUB_PLUGIN_DIR . 'includes/workflows/class-workflow-registry.php';
require_once ABILITYHUB_PLUGIN_DIR . 'includes/workflows/class-workflow-runner.php';
require_once ABILITYHUB_PLUGIN_DIR . 'includes/workflows/class-trigger-manager.php';
require_once ABILITYHUB_PLUGIN_DIR . 'includes/workflows/class-approval-queue.php';
require_once ABILITYHUB_PLUGIN_DIR . 'includes/workflows/functions-workflow-api.php';

// Register the CPT used as the approval queue store.
add_action( 'init', [ 'AbilityHub_Approval_Queue', 'register_post_type' ] );

// -------------------------------------------------------------------------
// v3: AI Site Operator Chat
// -------------------------------------------------------------------------

require_once ABILITYHUB_PLUGIN_DIR . 'includes/chat/class-site-scanner.php';
require_once ABILITYHUB_PLUGIN_DIR . 'includes/chat/class-conversation-store.php';
require_once ABILITYHUB_PLUGIN_DIR . 'includes/chat/class-prompt-builder.php';
require_once ABILITYHUB_PLUGIN_DIR . 'includes/chat/class-intent-parser.php';
require_once ABILITYHUB_PLUGIN_DIR . 'includes/chat/class-intent-executor.php';
require_once ABILITYHUB_PLUGIN_DIR . 'includes/chat/class-chat-handler.php';
require_once ABILITYHUB_PLUGIN_DIR . 'includes/chat/functions-chat-api.php';

// Register the batch job CPT.
add_action( 'init', 'abilityhub_register_batch_post_type' );

/**
 * Wire up the workflow engine and register built-in demo workflows.
 *
 * Runs at priority 20 on wp_abilities_api_init — after all abilities are
 * registered at default priority — so the runner can call wp_execute_ability()
 * on any registered ability.
 */
add_action( 'wp_abilities_api_init', static function () {
    $queue   = new AbilityHub_Approval_Queue();
    $runner  = new AbilityHub_Workflow_Runner( $queue );
    $manager = new AbilityHub_Trigger_Manager(
        AbilityHub_Workflow_Registry::get_instance(),
        $runner
    );

    // Demo workflow: generate SEO meta + suggest internal links on publish.
    abilityhub_register_workflow( 'abilityhub/auto-seo', [
        'trigger'    => 'post_published',
        'chain'      => [
            'abilityhub/generate-meta-description',
            'abilityhub/suggest-internal-links',
        ],
        'guardrails' => [ 'require_approval' => true ],
        'on_complete' => static function ( array $results, array $context ) {
            $meta_description = $results[0]['output']['meta_description'] ?? '';
            $meta_title       = $results[0]['output']['meta_title']       ?? '';

            if ( ! empty( $meta_description ) ) {
                update_post_meta( $context['post_id'], '_abilityhub_meta_description', sanitize_text_field( $meta_description ) );
            }
            if ( ! empty( $meta_title ) ) {
                update_post_meta( $context['post_id'], '_abilityhub_meta_title', sanitize_text_field( $meta_title ) );
            }
        },
    ] );

    // Demo workflow: generate alt text + suggest filename on image upload.
    abilityhub_register_workflow( 'abilityhub/auto-alt-text', [
        'trigger'    => 'image_uploaded',
        'chain'      => [
            'abilityhub/generate-alt-text',
            'abilityhub/suggest-image-filename',
        ],
        'guardrails' => [ 'require_approval' => true ],
        'on_complete' => static function ( array $results, array $context ) {
            $alt_text = $results[0]['output']['alt_text'] ?? '';

            if ( ! empty( $alt_text ) ) {
                update_post_meta( $context['attachment_id'], '_wp_attachment_image_alt', sanitize_text_field( $alt_text ) );
            }
        },
    ] );

    // Begin listening for trigger events.
    $manager->listen();

}, 20 );

/**
 * Re-register any workflows that were dynamically created via the chat
 * (stored in the DB) so they survive page reloads.
 *
 * Runs at priority 25 — after the built-in demo workflows at priority 20
 * and after all abilities are registered at default priority.
 */
add_action( 'wp_abilities_api_init', static function () {
    $saved = abilityhub_load_saved_workflows();

    if ( empty( $saved ) ) {
        return;
    }

    $deactivated = (array) get_option( 'abilityhub_deactivated_workflows', [] );

    foreach ( $saved as $workflow_id => $config ) {
        if ( in_array( $workflow_id, $deactivated, true ) ) {
            continue; // Skip workflows explicitly deactivated by the user.
        }

        // Avoid duplicate registration (demo workflows already registered at 20).
        if ( abilityhub_get_workflow( $workflow_id ) ) {
            continue;
        }

        abilityhub_register_workflow( $workflow_id, [
            'trigger'    => $config['trigger']          ?? 'post_published',
            'chain'      => (array) ( $config['chain']  ?? [] ),
            'guardrails' => [ 'require_approval' => (bool) ( $config['require_approval'] ?? true ) ],
        ] );
    }
}, 25 );
