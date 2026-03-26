<?php
/**
 * Tests for AbilityHub_AI_Client.
 *
 * @package AbilityHub
 */

require_once __DIR__ . '/class-abilityhub-test-case.php';

class Test_AbilityHub_AI_Client extends AbilityHub_Test_Case {

    // -----------------------------------------------------------------------
    // is_available()
    // -----------------------------------------------------------------------

    public function test_is_available_returns_false_when_wp_function_missing(): void {
        // wp_ai_client_prompt is not defined → not available
        $this->assertFalse( AbilityHub_AI_Client::is_available() );
    }

    public function test_is_available_returns_true_when_wp_function_exists(): void {
        \Brain\Monkey\Functions\when( 'wp_ai_client_prompt' )->justReturn( new stdClass() );
        $this->assertTrue( AbilityHub_AI_Client::is_available() );
    }

    // -----------------------------------------------------------------------
    // get_builder()
    // -----------------------------------------------------------------------

    public function test_get_builder_returns_wp_error_when_unavailable(): void {
        $result = AbilityHub_AI_Client::get_builder( 'test prompt' );
        $this->assertIsWpError( $result, 'no_ai_client' );
    }

    public function test_get_builder_returns_builder_when_available(): void {
        $mock_builder = new stdClass();
        \Brain\Monkey\Functions\when( 'wp_ai_client_prompt' )->justReturn( $mock_builder );

        $result = AbilityHub_AI_Client::get_builder( 'test prompt' );
        $this->assertSame( $mock_builder, $result );
    }

    // -----------------------------------------------------------------------
    // prompt()
    // -----------------------------------------------------------------------

    public function test_prompt_returns_wp_error_when_unavailable(): void {
        $result = AbilityHub_AI_Client::prompt( 'test prompt' );
        $this->assertIsWpError( $result, 'no_ai_client' );
    }

    public function test_prompt_returns_string_on_success(): void {
        $builder = \Mockery::mock( 'AI_Prompt_Builder' );
        $builder->shouldReceive( 'generate_text' )->andReturn( 'AI response text' );

        \Brain\Monkey\Functions\when( 'wp_ai_client_prompt' )->justReturn( $builder );

        $result = AbilityHub_AI_Client::prompt( 'Write something' );
        $this->assertSame( 'AI response text', $result );
    }

    // -----------------------------------------------------------------------
    // is_supported_for_text_generation()
    // -----------------------------------------------------------------------

    public function test_text_generation_not_supported_when_unavailable(): void {
        $this->assertFalse( AbilityHub_AI_Client::is_supported_for_text_generation() );
    }

    public function test_text_generation_supported_when_provider_says_so(): void {
        $builder = \Mockery::mock( 'AI_Support_Builder' );
        $builder->shouldReceive( 'is_supported_for_text_generation' )->andReturn( true );

        \Brain\Monkey\Functions\when( 'wp_ai_client_prompt' )->justReturn( $builder );

        $this->assertTrue( AbilityHub_AI_Client::is_supported_for_text_generation() );
    }

    // -----------------------------------------------------------------------
    // get_provider_name()
    // -----------------------------------------------------------------------

    public function test_get_provider_name_returns_not_available_when_no_api(): void {
        $name = AbilityHub_AI_Client::get_provider_name();
        $this->assertStringContainsStringIgnoringCase( 'not available', $name );
    }

    public function test_get_provider_name_returns_provider_name_when_api_available(): void {
        $provider = new stdClass();
        $provider->name = 'OpenAI'; // Not a real WP provider object, just for get_name()
        // Use wp_get_active_ai_provider to return a mock
        $mock_provider = \Mockery::mock( 'AI_Provider' );
        $mock_provider->shouldReceive( 'get_name' )->andReturn( 'OpenAI GPT-4' );

        \Brain\Monkey\Functions\when( 'wp_get_active_ai_provider' )->justReturn( $mock_provider );
        \Brain\Monkey\Functions\when( 'wp_ai_client_prompt' )->justReturn( new stdClass() );

        $name = AbilityHub_AI_Client::get_provider_name();
        $this->assertSame( 'OpenAI GPT-4', $name );
    }
}
