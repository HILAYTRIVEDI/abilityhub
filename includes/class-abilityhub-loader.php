<?php
/**
 * Registers all hooks and wires the plugin together.
 *
 * @package AbilityHub
 */

defined( 'ABSPATH' ) || exit;

class AbilityHub_Loader {

    public function run(): void {
        // Register ability categories before abilities
        add_action( 'wp_abilities_api_categories_init', [ $this, 'register_categories' ] );

        // Register all abilities with the WP Abilities API when it initialises.
        add_action( 'wp_abilities_api_init', [ $this, 'register_abilities' ] );

        // Also register on 'init' so the static registry is populated early
        // enough for REST API requests (which fire on rest_api_init, after init).
        // This ensures the chat intent executor can call abilities even when
        // wp_abilities_api_init hasn't fired yet.
        add_action( 'init', [ $this, 'register_abilities' ], 20 );

        // Track token usage for every AI call across all providers
        $token_tracker = new AbilityHub_Token_Tracker();
        add_action( 'wp_ai_client_after_generate_result', [ $token_tracker, 'track' ] );

        // Append site identity to WP AI plugin ability system instructions so
        // abilities like alt-text and meta description are brand-aware.
        add_filter( 'wpai_system_instruction', [ $this, 'filter_wpai_system_instruction' ], 10, 3 );

        // Purge old logs daily
        add_action( 'abilityhub_daily_cleanup', [ 'AbilityHub_Logger', 'purge_old_logs' ] );
        if ( ! wp_next_scheduled( 'abilityhub_daily_cleanup' ) ) {
            wp_schedule_event( time(), 'daily', 'abilityhub_daily_cleanup' );
        }

        // Admin-only hooks
        if ( is_admin() ) {
            $admin = new AbilityHub_Admin();
            add_action( 'admin_menu',            [ $admin, 'add_menu_page'           ] );
            add_action( 'admin_enqueue_scripts', [ $admin, 'enqueue_assets'          ] );
            add_action( 'wp_ajax_abilityhub_execute',         [ $admin, 'ajax_execute_ability'  ] );
            add_action( 'wp_ajax_abilityhub_get_abilities',  [ $admin, 'ajax_get_all_abilities' ] );
            add_action( 'wp_ajax_abilityhub_export_logs',    [ $admin, 'ajax_export_logs'       ] );
            add_action( 'wp_ajax_abilityhub_toggle_ability', [ $admin, 'ajax_toggle_ability'    ] );
            add_action( 'admin_post_abilityhub_save_settings',   [ $admin, 'save_settings'          ] );
            add_action( 'admin_post_abilityhub_approve_workflow', [ $this, 'handle_approve_workflow' ] );
            add_action( 'admin_post_abilityhub_reject_workflow',  [ $this, 'handle_reject_workflow'  ] );
        }
    }

    // -------------------------------------------------------------------------
    // WP AI plugin integration
    // -------------------------------------------------------------------------

    /**
     * Append the site name to WP AI plugin system instructions so built-in
     * abilities (alt text, meta description, etc.) are brand-aware.
     *
     * @param string $instruction Existing system instruction.
     * @param string $ability_name The ability being executed.
     * @param array  $data Context data passed to the instruction.
     * @return string
     */
    public function filter_wpai_system_instruction( string $instruction, string $ability_name, array $data ): string {
        $site_name = get_bloginfo( 'name' );
        if ( $site_name ) {
            $instruction = rtrim( $instruction ) . "\nSite: " . esc_html( $site_name ) . '.';
        }
        return $instruction;
    }

    // -------------------------------------------------------------------------
    // Workflow approval queue handlers (admin-post.php)
    // -------------------------------------------------------------------------

    /**
     * Handle a workflow approval request.
     *
     * @return void
     */
    public function handle_approve_workflow(): void {
        $post_id = absint( $_GET['post_id'] ?? 0 );

        check_admin_referer( 'abilityhub_workflow_action_' . $post_id );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to approve workflows.', 'abilityhub' ) );
        }

        $queue  = new AbilityHub_Approval_Queue();
        $result = $queue->approve( $post_id );

        $redirect_args = [ 'page' => 'abilityhub', 'tab' => 'workflows' ];

        if ( is_wp_error( $result ) ) {
            $redirect_args['workflow_action'] = 'error';
            $redirect_args['workflow_error']  = $result->get_error_message();
        } else {
            $redirect_args['workflow_action'] = 'approved';
        }

        wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Handle a workflow rejection request.
     *
     * @return void
     */
    public function handle_reject_workflow(): void {
        $post_id = absint( $_GET['post_id'] ?? 0 );

        check_admin_referer( 'abilityhub_workflow_action_' . $post_id );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to reject workflows.', 'abilityhub' ) );
        }

        $queue = new AbilityHub_Approval_Queue();
        $queue->reject( $post_id );

        wp_safe_redirect( add_query_arg(
            [ 'page' => 'abilityhub', 'tab' => 'workflows', 'workflow_action' => 'rejected' ],
            admin_url( 'admin.php' )
        ) );
        exit;
    }

    /**
     * Register AbilityHub ability categories.
     */
    public function register_categories(): void {
        if ( ! function_exists( 'wp_register_ability_category' ) ) {
            return;
        }

        $categories = [
            'seo'           => [
                'label'       => __( 'SEO',            'abilityhub' ),
                'description' => __( 'Search engine optimisation abilities', 'abilityhub' ),
            ],
            'editorial'     => [
                'label'       => __( 'Editorial',      'abilityhub' ),
                'description' => __( 'Content writing and editing abilities', 'abilityhub' ),
            ],
            'multilingual'  => [
                'label'       => __( 'Multilingual',   'abilityhub' ),
                'description' => __( 'Translation and language abilities', 'abilityhub' ),
            ],
            'ecommerce'     => [
                'label'       => __( 'eCommerce',      'abilityhub' ),
                'description' => __( 'WooCommerce and shop abilities', 'abilityhub' ),
            ],
            'moderation'    => [
                'label'       => __( 'Moderation',     'abilityhub' ),
                'description' => __( 'Content moderation abilities', 'abilityhub' ),
            ],
            'developer'     => [
                'label'       => __( 'Developer',      'abilityhub' ),
                'description' => __( 'Code and development abilities', 'abilityhub' ),
            ],
            'accessibility' => [
                'label'       => __( 'Accessibility',  'abilityhub' ),
                'description' => __( 'Accessibility improvement abilities', 'abilityhub' ),
            ],
            'media'         => [
                'label'       => __( 'Media',          'abilityhub' ),
                'description' => __( 'Image and media abilities', 'abilityhub' ),
            ],
            'site'          => [
                'label'       => __( 'Site',           'abilityhub' ),
                'description' => __( 'Site-level and MCP abilities', 'abilityhub' ),
            ],
        ];

        foreach ( $categories as $slug => $args ) {
            wp_register_ability_category( $slug, $args );
        }
    }

    /**
     * Instantiate and register all 15 ability classes.
     */
    public function register_abilities(): void {
        if ( ! function_exists( 'wp_register_ability' ) ) {
            return;
        }

        $ability_classes = [
            // Content
            'AbilityHub_Ability_Generate_Meta',
            'AbilityHub_Ability_Classify_Content',
            'AbilityHub_Ability_Rewrite_Tone',
            'AbilityHub_Ability_Summarise_Post',
            'AbilityHub_Ability_Suggest_Links',
            'AbilityHub_Ability_Translate_Block',
            // WooCommerce
            'AbilityHub_Ability_Product_Description',
            'AbilityHub_Ability_Review_Response',
            'AbilityHub_Ability_Upsell_Copy',
            'AbilityHub_Ability_Moderate_Comment',
            // Developer
            'AbilityHub_Ability_Generate_Block_Pattern',
            'AbilityHub_Ability_Explain_Php_Error',
            'AbilityHub_Ability_Write_Hook_Docs',
            // Media
            'AbilityHub_Ability_Generate_Alt_Text',
            'AbilityHub_Ability_Suggest_Filename',
            // Site
            'AbilityHub_Ability_Mcp_Manifest',
            'AbilityHub_Ability_Get_Posts',
            'AbilityHub_Ability_Manage_Post',
            'AbilityHub_Ability_Update_Site_Setting',
            'AbilityHub_Ability_Fetch_Url',
        ];

        foreach ( $ability_classes as $class ) {
            if ( class_exists( $class ) ) {
                ( new $class() )->register();
            }
        }
    }
}
