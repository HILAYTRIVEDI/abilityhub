<?php
/**
 * Tests for all 3 Developer abilities:
 *  - Generate Block Pattern
 *  - Explain PHP Error
 *  - Write WP Hook Docs
 *
 * @package AbilityHub
 */

require_once dirname( __DIR__ ) . '/class-abilityhub-test-case.php';

// ===========================================================================
// Generate Block Pattern
// ===========================================================================

class Test_Ability_Generate_Block_Pattern extends AbilityHub_Test_Case {

    private AbilityHub_Ability_Generate_Block_Pattern $ability;

    protected function setUp(): void {
        parent::setUp();
        $this->ability = new AbilityHub_Ability_Generate_Block_Pattern();
    }

    public function test_missing_description_returns_error(): void {
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->justReturn( '' );

        $result = $this->ability->execute( [] );
        $this->assertIsWpError( $result, 'missing_description' );
    }

    public function test_ai_unavailable_returns_error(): void {
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->returnArg();

        $result = $this->ability->execute( [ 'description' => 'A hero section with heading and CTA.' ] );
        $this->assertIsWpError( $result, 'no_ai_client' );
    }

    public function test_ai_generate_error_propagates(): void {
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->returnArg();
        $this->mock_ai_generate_error();

        $result = $this->ability->execute( [ 'description' => 'A hero section.' ] );
        $this->assertIsWpError( $result );
    }

    public function test_invalid_json_returns_parse_error(): void {
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->returnArg();
        $this->mock_ai_invalid_json();

        $result = $this->ability->execute( [ 'description' => 'A hero section.' ] );
        $this->assertIsWpError( $result, 'parse_error' );
    }

    public function test_successful_execution_returns_php_and_html(): void {
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->returnArg();
        \Brain\Monkey\Functions\when( 'wp_kses_post' )->returnArg();
        $this->mock_ai_success( [
            'pattern_php'  => '<?php register_block_pattern( "mytheme/hero", [ ... ] ); ?>',
            'pattern_html' => '<!-- wp:heading --><h2>Welcome</h2><!-- /wp:heading -->',
        ] );

        $result = $this->ability->execute( [
            'description' => 'A hero section with heading and two CTA buttons.',
        ] );

        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'pattern_php', $result );
        $this->assertArrayHasKey( 'pattern_html', $result );
        $this->assertStringContainsString( 'register_block_pattern', $result['pattern_php'] );
    }

    public function test_block_types_hint_is_accepted(): void {
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->returnArg();
        \Brain\Monkey\Functions\when( 'wp_kses_post' )->returnArg();
        $this->mock_ai_success( [
            'pattern_php'  => '<?php register_block_pattern( "x/y", [] ); ?>',
            'pattern_html' => '<!-- wp:paragraph --><p>Text</p><!-- /wp:paragraph -->',
        ] );

        $result = $this->ability->execute( [
            'description' => 'A text block.',
            'block_types' => [ 'core/paragraph', 'core/heading' ],
        ] );

        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'pattern_html', $result );
    }
}

// ===========================================================================
// Explain PHP Error
// ===========================================================================

class Test_Ability_Explain_Php_Error extends AbilityHub_Test_Case {

    private AbilityHub_Ability_Explain_Php_Error $ability;

    protected function setUp(): void {
        parent::setUp();
        $this->ability = new AbilityHub_Ability_Explain_Php_Error();
    }

    public function test_missing_error_message_returns_error(): void {
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->justReturn( '' );
        \Brain\Monkey\Functions\when( 'sanitize_textarea_field' )->justReturn( '' );

        $result = $this->ability->execute( [] );
        $this->assertIsWpError( $result, 'missing_error' );
    }

    public function test_ai_unavailable_returns_error(): void {
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->returnArg();
        \Brain\Monkey\Functions\when( 'sanitize_textarea_field' )->returnArg();

        $result = $this->ability->execute( [
            'error_message' => 'Fatal error: Call to undefined function foo()',
        ] );
        $this->assertIsWpError( $result, 'no_ai_client' );
    }

    public function test_successful_execution_returns_four_fields(): void {
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->returnArg();
        \Brain\Monkey\Functions\when( 'sanitize_textarea_field' )->returnArg();
        $this->mock_ai_success( [
            'plain_explanation' => 'You called a function that does not exist.',
            'likely_cause'      => 'Plugin function not loaded.',
            'fix_suggestion'    => 'Check the function name and ensure the file is included.',
            'code_example'      => "if ( function_exists( 'foo' ) ) { foo(); }",
        ] );

        $result = $this->ability->execute( [
            'error_message' => 'Fatal error: Call to undefined function foo()',
        ] );

        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'plain_explanation', $result );
        $this->assertArrayHasKey( 'likely_cause', $result );
        $this->assertArrayHasKey( 'fix_suggestion', $result );
        $this->assertArrayHasKey( 'code_example', $result );
    }

    public function test_file_context_is_accepted_as_optional(): void {
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->returnArg();
        \Brain\Monkey\Functions\when( 'sanitize_textarea_field' )->returnArg();
        $this->mock_ai_success( [
            'plain_explanation' => 'Explanation.',
            'likely_cause'      => 'Cause.',
            'fix_suggestion'    => 'Fix.',
            'code_example'      => '<?php // fix ?>',
        ] );

        $result = $this->ability->execute( [
            'error_message' => 'Fatal error: ...',
            'file_context'  => '$foo = get_post( null );',
        ] );

        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'plain_explanation', $result );
    }
}

// ===========================================================================
// Write WP Hook Docs
// ===========================================================================

class Test_Ability_Write_Hook_Docs extends AbilityHub_Test_Case {

    private AbilityHub_Ability_Write_Hook_Docs $ability;

    protected function setUp(): void {
        parent::setUp();
        $this->ability = new AbilityHub_Ability_Write_Hook_Docs();
    }

    public function test_missing_hook_name_returns_error(): void {
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->justReturn( '' );

        $result = $this->ability->execute( [ 'hook_type' => 'action' ] );
        $this->assertIsWpError( $result, 'missing_hook_name' );
    }

    public function test_invalid_hook_type_returns_error(): void {
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->returnArg();

        $result = $this->ability->execute( [ 'hook_name' => 'save_post', 'hook_type' => 'magic' ] );
        $this->assertIsWpError( $result, 'invalid_hook_type' );
    }

    public function test_ai_unavailable_returns_error(): void {
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->returnArg();

        $result = $this->ability->execute( [ 'hook_name' => 'save_post', 'hook_type' => 'action' ] );
        $this->assertIsWpError( $result, 'no_ai_client' );
    }

    public function test_successful_action_hook_returns_docblock_and_example(): void {
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->returnArg();
        \Brain\Monkey\Functions\when( 'sanitize_textarea_field' )->returnArg();
        $this->mock_ai_success( [
            'docblock'      => "/**\n * Fires after a post is saved.\n *\n * @since 1.0.0\n */",
            'usage_example' => "add_action( 'save_post', function( \$post_id ) { ... } );",
        ] );

        $result = $this->ability->execute( [
            'hook_name' => 'save_post',
            'hook_type' => 'action',
            'parameters' => 'int $post_id, WP_Post $post, bool $update',
        ] );

        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'docblock', $result );
        $this->assertArrayHasKey( 'usage_example', $result );
    }

    public function test_successful_filter_hook_returns_docblock_and_example(): void {
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->returnArg();
        \Brain\Monkey\Functions\when( 'sanitize_textarea_field' )->returnArg();
        $this->mock_ai_success( [
            'docblock'      => "/**\n * Filters post content.\n *\n * @return string\n */",
            'usage_example' => "add_filter( 'the_content', function( \$content ) { return \$content; } );",
        ] );

        $result = $this->ability->execute( [ 'hook_name' => 'the_content', 'hook_type' => 'filter' ] );

        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'docblock', $result );
        $this->assertArrayHasKey( 'usage_example', $result );
    }

    /**
     * @dataProvider hook_type_provider
     */
    public function test_both_hook_types_are_valid( string $type ): void {
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->returnArg();
        \Brain\Monkey\Functions\when( 'sanitize_textarea_field' )->returnArg();
        $this->mock_ai_success( [ 'docblock' => '/** ... */', 'usage_example' => 'add_action(...)' ] );

        $result = $this->ability->execute( [ 'hook_name' => 'test_hook', 'hook_type' => $type ] );
        $this->assertIsArray( $result );
    }

    public static function hook_type_provider(): array {
        return [ [ 'action' ], [ 'filter' ] ];
    }
}
