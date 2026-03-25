<?php
/**
 * Admin controller for AbilityHub.
 *
 * @package AbilityHub
 */

defined( 'ABSPATH' ) || exit;

class AbilityHub_Admin {

    /**
     * Register the top-level admin menu page.
     */
    public function add_menu_page(): void {
        add_menu_page(
            __( 'AbilityHub', 'abilityhub' ),
            __( 'AbilityHub', 'abilityhub' ),
            'edit_posts',
            'abilityhub',
            [ $this, 'render_page' ],
            'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>' ),
            25
        );

        // Sub-pages (same callback — tab handles routing)
        add_submenu_page( 'abilityhub', __( 'Dashboard', 'abilityhub' ),      __( 'Dashboard', 'abilityhub' ),      'edit_posts',      'abilityhub',                    [ $this, 'render_page' ] );
        add_submenu_page( 'abilityhub', __( 'Ability Store', 'abilityhub' ),  __( 'Ability Store', 'abilityhub' ),  'edit_posts',      'abilityhub&tab=store',          [ $this, 'render_page' ] );
        add_submenu_page( 'abilityhub', __( 'Installed', 'abilityhub' ),      __( 'Installed', 'abilityhub' ),      'edit_posts',      'abilityhub&tab=installed',      [ $this, 'render_page' ] );
        add_submenu_page( 'abilityhub', __( 'Execution Logs', 'abilityhub' ), __( 'Execution Logs', 'abilityhub' ), 'edit_posts',      'abilityhub&tab=logs',           [ $this, 'render_page' ] );
        add_submenu_page( 'abilityhub', __( 'Workflows', 'abilityhub' ),      __( 'Workflows', 'abilityhub' ),      'manage_options',  'abilityhub&tab=workflows',      [ $this, 'render_page' ] );
        add_submenu_page( 'abilityhub', __( 'AI Operator', 'abilityhub' ),   __( 'AI Operator', 'abilityhub' ),   'edit_posts',      'abilityhub&tab=chat',           [ $this, 'render_page' ] );
        add_submenu_page( 'abilityhub', __( 'Settings', 'abilityhub' ),       __( 'Settings', 'abilityhub' ),       'manage_options',  'abilityhub&tab=settings',       [ $this, 'render_page' ] );
    }

    /**
     * Enqueue admin CSS and JS only on AbilityHub pages.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_assets( string $hook ): void {
        if ( strpos( $hook, 'abilityhub' ) === false ) {
            return;
        }

        wp_enqueue_style(
            'abilityhub-admin',
            ABILITYHUB_PLUGIN_URL . 'admin/assets/css/abilityhub-admin.css',
            [],
            ABILITYHUB_VERSION
        );

        wp_enqueue_script(
            'abilityhub-admin',
            ABILITYHUB_PLUGIN_URL . 'admin/assets/js/abilityhub-admin.js',
            [ 'jquery' ],
            ABILITYHUB_VERSION,
            true
        );

        wp_localize_script( 'abilityhub-admin', 'AbilityHub', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'abilityhub_nonce' ),
            'i18n'     => [
                'executing'   => __( 'Executing…', 'abilityhub' ),
                'error'       => __( 'Error', 'abilityhub' ),
                'success'     => __( 'Success', 'abilityhub' ),
                'copy'        => __( 'Copy', 'abilityhub' ),
                'copied'      => __( 'Copied!', 'abilityhub' ),
            ],
        ] );

        // Chat panel assets — only needed on the chat tab.
        $current_tab = sanitize_key( $_GET['tab'] ?? 'dashboard' );
        if ( 'chat' === $current_tab ) {
            wp_enqueue_style(
                'abilityhub-chat',
                ABILITYHUB_PLUGIN_URL . 'admin/assets/css/chat-panel.css',
                [],
                ABILITYHUB_VERSION
            );

            wp_enqueue_script(
                'abilityhub-chat',
                ABILITYHUB_PLUGIN_URL . 'admin/assets/js/chat-panel.js',
                [],
                ABILITYHUB_VERSION,
                true
            );

            wp_localize_script( 'abilityhub-chat', 'AbilityHubChat', [
                'rest_url'         => rest_url( 'abilityhub/v1/chat' ),
                'history_url'      => rest_url( 'abilityhub/v1/chat/history' ),
                'batch_status_url' => rest_url( 'abilityhub/v1/batch/{id}' ),
                'nonce'            => wp_create_nonce( 'wp_rest' ),
                'i18n'             => [
                    'you'            => __( 'You', 'abilityhub' ),
                    'error'          => __( 'An error occurred. Please try again.', 'abilityhub' ),
                    'confirm_clear'  => __( 'Clear the entire conversation? This cannot be undone.', 'abilityhub' ),
                    'cleared'        => __( 'Conversation cleared. I\'m AbilityOperator — how can I help you?', 'abilityhub' ),
                    'batch_progress' => __( 'Processing: {p}/{t} posts…', 'abilityhub' ),
                    'batch_complete' => __( 'Batch complete — {t} posts processed.', 'abilityhub' ),
                    'view_batch'     => __( 'View batch', 'abilityhub' ),
                ],
            ] );
        }
    }

    /**
     * Render the main admin page, routing to the correct tab view.
     */
    public function render_page(): void {
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'abilityhub' ) );
        }

        $tab = sanitize_key( $_GET['tab'] ?? 'dashboard' );

        $allowed_tabs = [ 'dashboard', 'store', 'installed', 'logs', 'workflows', 'chat', 'settings' ];
        if ( ! in_array( $tab, $allowed_tabs, true ) ) {
            $tab = 'dashboard';
        }

        // These tabs require manage_options.
        if ( in_array( $tab, [ 'settings', 'workflows' ], true ) && ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'abilityhub' ) );
        }

        $view_file = ABILITYHUB_PLUGIN_DIR . "admin/views/{$tab}.php";

        echo '<div class="wrap abilityhub-wrap">';
        $this->render_header( $tab );

        if ( file_exists( $view_file ) ) {
            require $view_file;
        } else {
            echo '<p>' . esc_html__( 'View not found.', 'abilityhub' ) . '</p>';
        }

        echo '</div>';
    }

    /**
     * Render the page header with tab navigation.
     *
     * @param string $active_tab Current active tab slug.
     */
    private function render_header( string $active_tab ): void {
        // Build the Workflows tab label — add a count badge if items are pending.
        $workflows_label = __( 'Workflows', 'abilityhub' );
        if ( current_user_can( 'manage_options' ) && class_exists( 'AbilityHub_Approval_Queue' ) ) {
            $pending_count = ( new AbilityHub_Approval_Queue() )->count_pending();
            if ( $pending_count > 0 ) {
                $workflows_label .= ' <span class="awaiting-mod">' . absint( $pending_count ) . '</span>';
            }
        }

        $tabs = [
            'dashboard' => __( 'Dashboard', 'abilityhub' ),
            'store'     => __( 'Ability Store', 'abilityhub' ),
            'installed' => __( 'Installed', 'abilityhub' ),
            'logs'      => __( 'Execution Logs', 'abilityhub' ),
            'workflows' => $workflows_label,
            'chat'      => __( 'AI Operator', 'abilityhub' ),
            'settings'  => __( 'Settings', 'abilityhub' ),
        ];
        ?>
        <div class="abilityhub-header">
            <div class="abilityhub-header__logo">
                <span class="abilityhub-logo-icon">⚡</span>
                <h1><?php esc_html_e( 'AbilityHub', 'abilityhub' ); ?></h1>
                <span class="abilityhub-version"><?php echo esc_html( ABILITYHUB_VERSION ); ?></span>
            </div>
        </div>
        <nav class="abilityhub-tabs nav-tab-wrapper">
            <?php foreach ( $tabs as $slug => $label ) : ?>
                <?php
                if ( in_array( $slug, [ 'settings', 'workflows' ], true ) && ! current_user_can( 'manage_options' ) ) {
                    continue; // manage_options-only tabs.
                }
                $url    = add_query_arg( [ 'page' => 'abilityhub', 'tab' => $slug ], admin_url( 'admin.php' ) );
                $active = $active_tab === $slug ? ' nav-tab-active' : '';
                ?>
                <a href="<?php echo esc_url( $url ); ?>" class="nav-tab<?php echo esc_attr( $active ); ?>">
                    <?php echo wp_kses( $label, [ 'span' => [ 'class' => [] ] ] ); ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <?php
    }

    // -------------------------------------------------------------------------
    // AJAX: Execute an ability
    // -------------------------------------------------------------------------

    public function ajax_execute_ability(): void {
        check_ajax_referer( 'abilityhub_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'abilityhub' ) ] );
        }

        $ability_name = sanitize_text_field( wp_unslash( $_POST['ability'] ?? '' ) );
        $raw_input    = wp_unslash( $_POST['input'] ?? '{}' );
        $input        = json_decode( $raw_input, true );

        if ( ! $ability_name ) {
            wp_send_json_error( [ 'message' => __( 'Ability name is required.', 'abilityhub' ) ] );
        }

        if ( ! is_array( $input ) ) {
            wp_send_json_error( [ 'message' => __( 'Input must be valid JSON.', 'abilityhub' ) ] );
        }

        if ( ! function_exists( 'wp_get_ability' ) ) {
            wp_send_json_error( [ 'message' => __( 'Abilities API not available. Requires WordPress 7.0+.', 'abilityhub' ) ] );
        }

        $ability = wp_get_ability( $ability_name );

        if ( ! $ability ) {
            wp_send_json_error( [
                /* translators: %s: ability slug */
                'message' => sprintf( __( 'Ability "%s" not found.', 'abilityhub' ), $ability_name ),
            ] );
        }

        $result = $ability->execute( $input );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success( [ 'result' => $result ] );
    }

    // -------------------------------------------------------------------------
    // AJAX: Get all registered abilities for the Installed tab
    // -------------------------------------------------------------------------

    public function ajax_get_all_abilities(): void {
        check_ajax_referer( 'abilityhub_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'abilityhub' ) ] );
        }

        if ( ! function_exists( 'wp_get_abilities' ) ) {
            wp_send_json_error( [ 'message' => __( 'Abilities API not available.', 'abilityhub' ) ] );
        }

        $abilities = wp_get_abilities();
        $data      = [];

        foreach ( $abilities as $name => $ability ) {
            $data[] = [
                'name'     => $name,
                'label'    => is_object( $ability ) && method_exists( $ability, 'get_label' )
                    ? $ability->get_label()
                    : $name,
                'category' => is_object( $ability ) && method_exists( $ability, 'get_category' )
                    ? $ability->get_category()
                    : '',
                'endpoint' => rest_url( 'wp-abilities/v1/abilities/' . rawurlencode( $name ) . '/execute' ),
            ];
        }

        wp_send_json_success( [ 'abilities' => $data ] );
    }

    // -------------------------------------------------------------------------
    // AJAX: Export logs as CSV
    // -------------------------------------------------------------------------

    public function ajax_export_logs(): void {
        check_ajax_referer( 'abilityhub_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'abilityhub' ) );
        }

        $logs = AbilityHub_Logger::get_logs( [ 'per_page' => 10000, 'page' => 1 ] );

        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="abilityhub-logs-' . gmdate( 'Y-m-d' ) . '.csv"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $output = fopen( 'php://output', 'w' );
        fputcsv( $output, [ 'ID', 'Ability', 'Status', 'Duration (ms)', 'User ID', 'Created At' ] );

        foreach ( $logs['items'] as $row ) {
            fputcsv( $output, [
                $row['id'],
                $row['ability'],
                $row['status'],
                $row['duration_ms'],
                $row['user_id'],
                $row['created_at'],
            ] );
        }

        fclose( $output );
        exit;
    }

    // -------------------------------------------------------------------------
    // Admin POST: Save settings
    // -------------------------------------------------------------------------

    public function save_settings(): void {
        check_admin_referer( 'abilityhub_settings' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'abilityhub' ) );
        }

        update_option( 'abilityhub_log_enabled',       absint( $_POST['log_enabled'] ?? 1 ) );
        update_option( 'abilityhub_log_retention_days', absint( $_POST['log_retention_days'] ?? 30 ) );
        update_option( 'abilityhub_registry_api_key',  sanitize_text_field( $_POST['registry_api_key'] ?? '' ) );

        wp_safe_redirect( add_query_arg(
            [ 'page' => 'abilityhub', 'tab' => 'settings', 'saved' => '1' ],
            admin_url( 'admin.php' )
        ) );
        exit;
    }

    // -------------------------------------------------------------------------
    // Helper: get AbilityHub's own abilities metadata for the store
    // -------------------------------------------------------------------------

    public static function get_store_abilities(): array {
        return [
            [
                'name'        => 'abilityhub/generate-meta-description',
                'label'       => __( 'Generate meta description', 'abilityhub' ),
                'description' => __( 'Generates an SEO title and 155-character meta description from post content.', 'abilityhub' ),
                'category'    => 'seo',
                'example'     => [ 'content' => 'WordPress 7.0 introduces the new Abilities API, making it easier than ever to integrate AI into your site workflows.', 'keyword' => 'WordPress AI' ],
            ],
            [
                'name'        => 'abilityhub/rewrite-tone',
                'label'       => __( 'Rewrite tone', 'abilityhub' ),
                'description' => __( 'Rewrites content in a specified tone while preserving all facts and meaning.', 'abilityhub' ),
                'category'    => 'editorial',
                'example'     => [ 'content' => 'The product has some issues that need addressing.', 'tone' => 'professional' ],
            ],
            [
                'name'        => 'abilityhub/summarise-post',
                'label'       => __( 'Summarise post', 'abilityhub' ),
                'description' => __( 'Generates a concise summary and one-sentence TL;DR from post content.', 'abilityhub' ),
                'category'    => 'editorial',
                'example'     => [ 'content' => 'WordPress powers over 43% of all websites on the internet, making it the world\'s most popular CMS.', 'max_words' => 30 ],
            ],
            [
                'name'        => 'abilityhub/suggest-internal-links',
                'label'       => __( 'Suggest internal links', 'abilityhub' ),
                'description' => __( 'Analyses post content and suggests 3-5 relevant internal links from existing posts.', 'abilityhub' ),
                'category'    => 'seo',
                'example'     => [ 'content' => 'Learn how to optimise your WordPress site for speed and performance.' ],
            ],
            [
                'name'        => 'abilityhub/translate-block',
                'label'       => __( 'Translate block', 'abilityhub' ),
                'description' => __( 'Translates block content into a target language, preserving block markup.', 'abilityhub' ),
                'category'    => 'multilingual',
                'example'     => [ 'content' => 'Welcome to our website. We are happy to serve you.', 'target_language' => 'es' ],
            ],
            [
                'name'        => 'abilityhub/generate-product-description',
                'label'       => __( 'Generate product description', 'abilityhub' ),
                'description' => __( 'Generates WooCommerce short description, long description, and meta description for a product.', 'abilityhub' ),
                'category'    => 'ecommerce',
                'example'     => [ 'product_name' => 'Ergonomic Office Chair', 'attributes' => 'color: black, material: mesh, adjustable: yes', 'keyword' => 'ergonomic office chair' ],
            ],
            [
                'name'        => 'abilityhub/write-review-response',
                'label'       => __( 'Write review response', 'abilityhub' ),
                'description' => __( 'Generates a professional, brand-appropriate response to a customer product review.', 'abilityhub' ),
                'category'    => 'ecommerce',
                'example'     => [ 'review_text' => 'Great product, fast shipping!', 'rating' => 5, 'product_name' => 'Ergonomic Office Chair' ],
            ],
            [
                'name'        => 'abilityhub/generate-upsell-copy',
                'label'       => __( 'Generate upsell copy', 'abilityhub' ),
                'description' => __( 'Creates compelling upsell and cross-sell copy for related WooCommerce products.', 'abilityhub' ),
                'category'    => 'ecommerce',
                'example'     => [ 'product_name' => 'Ergonomic Office Chair', 'related_products' => [ 'Lumbar Support Cushion', 'Adjustable Desk' ] ],
            ],
            [
                'name'        => 'abilityhub/moderate-comment',
                'label'       => __( 'Moderate comment', 'abilityhub' ),
                'description' => __( 'Analyses a comment and returns an approve, flag, or spam verdict with confidence score.', 'abilityhub' ),
                'category'    => 'moderation',
                'example'     => [ 'comment_text' => 'This is a great article! Really helpful content.', 'post_context' => 'WordPress performance tips' ],
            ],
            [
                'name'        => 'abilityhub/generate-block-pattern',
                'label'       => __( 'Generate block pattern', 'abilityhub' ),
                'description' => __( 'Generates a complete WordPress block pattern with PHP registration code and block markup.', 'abilityhub' ),
                'category'    => 'developer',
                'example'     => [ 'description' => 'A hero section with a large heading, subheading, and two call-to-action buttons side by side' ],
            ],
            [
                'name'        => 'abilityhub/explain-php-error',
                'label'       => __( 'Explain PHP error', 'abilityhub' ),
                'description' => __( 'Explains a PHP or WordPress error in plain language and suggests a fix with a code example.', 'abilityhub' ),
                'category'    => 'developer',
                'example'     => [ 'error_message' => 'Fatal error: Call to a member function get_meta() on null in /wp-content/plugins/my-plugin/includes/class-product.php on line 42' ],
            ],
            [
                'name'        => 'abilityhub/write-wp-hook-docs',
                'label'       => __( 'Write WP hook docs', 'abilityhub' ),
                'description' => __( 'Generates a PHPDoc docblock and usage example for a WordPress action or filter hook.', 'abilityhub' ),
                'category'    => 'developer',
                'example'     => [ 'hook_name' => 'save_post', 'hook_type' => 'action', 'parameters' => 'int $post_id, WP_Post $post, bool $update' ],
            ],
            [
                'name'        => 'abilityhub/generate-alt-text',
                'label'       => __( 'Generate alt text', 'abilityhub' ),
                'description' => __( 'Generates accessible, descriptive alt text and optional caption for an image using AI vision.', 'abilityhub' ),
                'category'    => 'accessibility',
                'example'     => [ 'image_url' => 'https://via.placeholder.com/800x400', 'image_context' => 'WordPress developer conference keynote' ],
            ],
            [
                'name'        => 'abilityhub/suggest-image-filename',
                'label'       => __( 'Suggest image filename', 'abilityhub' ),
                'description' => __( 'Suggests an SEO-friendly, descriptive filename for an image based on context.', 'abilityhub' ),
                'category'    => 'media',
                'example'     => [ 'current_filename' => 'IMG_20240315_142301.jpg', 'image_context' => 'WordPress logo on dark background' ],
            ],
            [
                'name'        => 'abilityhub/mcp-capability-manifest',
                'label'       => __( 'MCP capability manifest', 'abilityhub' ),
                'description' => __( 'Returns a full MCP-compatible manifest of all AI abilities registered on this WordPress site, making it a discoverable MCP server.', 'abilityhub' ),
                'category'    => 'site',
                'example'     => [],
            ],
        ];
    }
}
