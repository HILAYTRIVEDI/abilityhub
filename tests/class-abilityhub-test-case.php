<?php
/**
 * Base test case for AbilityHub unit tests.
 *
 * Provides Brain\Monkey setUp/tearDown wiring and shared helpers for
 * mocking the WP AI client builder and common WordPress functions.
 *
 * @package AbilityHub
 */

abstract class AbilityHub_Test_Case extends \PHPUnit\Framework\TestCase {

    use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

    protected function setUp(): void {
        parent::setUp();
        \Brain\Monkey\setUp();

        // Always stub these — they are called by AbilityHub_Logger::log()
        // inside every ability's execute() method.
        \Brain\Monkey\Functions\when( 'get_option' )->justReturn( 0 );       // disable logging
        \Brain\Monkey\Functions\when( 'get_current_user_id' )->justReturn( 1 );
    }

    protected function tearDown(): void {
        \Brain\Monkey\tearDown();
        \Mockery::close();
        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // AI builder helpers
    // -----------------------------------------------------------------------

    /**
     * Stub wp_ai_client_prompt to return a fluent builder that yields $response
     * as a JSON-encoded string from generate_text().
     *
     * @param array $response The decoded response data.
     * @return \Mockery\MockInterface
     */
    protected function mock_ai_success( array $response ): \Mockery\MockInterface {
        $builder = \Mockery::mock( 'AbilityHub_AI_Builder_Success' );
        $builder->shouldReceive( 'using_system_instruction' )->andReturnSelf();
        $builder->shouldReceive( 'using_temperature' )->andReturnSelf();
        $builder->shouldReceive( 'as_json_response' )->andReturnSelf();
        $builder->shouldReceive( 'generate_text' )->andReturn( json_encode( $response ) );

        \Brain\Monkey\Functions\when( 'wp_ai_client_prompt' )->justReturn( $builder );

        return $builder;
    }

    /**
     * Stub wp_ai_client_prompt so generate_text() returns a WP_Error.
     */
    protected function mock_ai_generate_error(
        string $code    = 'ai_error',
        string $message = 'AI generation failed'
    ): void {
        $builder = \Mockery::mock( 'AbilityHub_AI_Builder_GenError' );
        $builder->shouldReceive( 'using_system_instruction' )->andReturnSelf();
        $builder->shouldReceive( 'using_temperature' )->andReturnSelf();
        $builder->shouldReceive( 'as_json_response' )->andReturnSelf();
        $builder->shouldReceive( 'generate_text' )->andReturn( new WP_Error( $code, $message ) );

        \Brain\Monkey\Functions\when( 'wp_ai_client_prompt' )->justReturn( $builder );
    }

    /**
     * Stub wp_ai_client_prompt so generate_text() returns an unparseable string
     * to exercise the parse_error code path.
     */
    protected function mock_ai_invalid_json(): void {
        $builder = \Mockery::mock( 'AbilityHub_AI_Builder_BadJson' );
        $builder->shouldReceive( 'using_system_instruction' )->andReturnSelf();
        $builder->shouldReceive( 'using_temperature' )->andReturnSelf();
        $builder->shouldReceive( 'as_json_response' )->andReturnSelf();
        $builder->shouldReceive( 'generate_text' )->andReturn( 'this is not valid json {{' );

        \Brain\Monkey\Functions\when( 'wp_ai_client_prompt' )->justReturn( $builder );
    }

    // -----------------------------------------------------------------------
    // Workflow registry helper
    // -----------------------------------------------------------------------

    /**
     * Reset the WorkflowRegistry singleton so each test starts with a clean slate.
     */
    protected function reset_workflow_registry(): void {
        $ref  = new ReflectionClass( AbilityHub_Workflow_Registry::class );
        $prop = $ref->getProperty( 'instance' );
        $prop->setAccessible( true );
        $prop->setValue( null, null );
    }

    // -----------------------------------------------------------------------
    // Assertion helpers
    // -----------------------------------------------------------------------

    protected function assertIsWpError( $value, string $expected_code = '' ): void {
        $this->assertInstanceOf( WP_Error::class, $value );
        if ( $expected_code !== '' ) {
            $this->assertSame( $expected_code, $value->get_error_code() );
        }
    }
}
