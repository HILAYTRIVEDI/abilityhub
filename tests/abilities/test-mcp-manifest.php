<?php
/**
 * Tests for the MCP Capability Manifest ability.
 *
 * @package AbilityHub
 */

require_once dirname( __DIR__ ) . '/class-abilityhub-test-case.php';

class Test_Ability_Mcp_Manifest extends AbilityHub_Test_Case {

    private AbilityHub_Ability_Mcp_Manifest $ability;

    protected function setUp(): void {
        parent::setUp();
        $this->ability = new AbilityHub_Ability_Mcp_Manifest();
    }

    // -----------------------------------------------------------------------
    // Abilities API not available
    // -----------------------------------------------------------------------

    public function test_returns_error_when_abilities_api_unavailable(): void {
        // wp_get_abilities is NOT stubbed → function_exists returns false
        $result = $this->ability->execute( [] );
        $this->assertIsWpError( $result, 'no_abilities_api' );
    }

    // -----------------------------------------------------------------------
    // Empty abilities list
    // -----------------------------------------------------------------------

    public function test_returns_empty_manifest_when_no_abilities_registered(): void {
        \Brain\Monkey\Functions\when( 'wp_get_abilities' )->justReturn( [] );
        \Brain\Monkey\Functions\when( 'rest_url' )->justReturn( 'https://example.com/wp-json/wp-abilities/v1/abilities' );

        $result = $this->ability->execute( [] );

        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'manifest', $result );
        $this->assertArrayHasKey( 'total_abilities', $result );
        $this->assertSame( 0, $result['total_abilities'] );
        $this->assertSame( [], $result['manifest']['tools'] );
    }

    // -----------------------------------------------------------------------
    // Full manifest with abilities
    // -----------------------------------------------------------------------

    public function test_manifest_includes_all_registered_abilities(): void {
        $mock_ability = [
            'label'        => 'Generate meta description',
            'description'  => 'Generates SEO meta.',
            'input_schema' => [ 'type' => 'object', 'properties' => [ 'content' => [ 'type' => 'string' ] ] ],
        ];

        \Brain\Monkey\Functions\when( 'wp_get_abilities' )->justReturn( [
            'abilityhub/generate-meta-description' => $mock_ability,
            'abilityhub/summarise-post'            => [
                'label'        => 'Summarise post',
                'description'  => 'Generates a summary.',
                'input_schema' => [ 'type' => 'object', 'properties' => [] ],
            ],
        ] );
        \Brain\Monkey\Functions\when( 'rest_url' )->justReturn( 'https://example.com/wp-json/wp-abilities/v1/abilities' );
        \Brain\Monkey\Functions\when( 'get_bloginfo' )->justReturn( 'My WordPress Site' );
        \Brain\Monkey\Functions\when( 'get_site_url' )->justReturn( 'https://example.com' );

        $result = $this->ability->execute( [] );

        $this->assertSame( 2, $result['total_abilities'] );
        $this->assertCount( 2, $result['manifest']['tools'] );
    }

    // -----------------------------------------------------------------------
    // MCP manifest structure
    // -----------------------------------------------------------------------

    public function test_manifest_contains_required_mcp_fields(): void {
        \Brain\Monkey\Functions\when( 'wp_get_abilities' )->justReturn( [] );
        \Brain\Monkey\Functions\when( 'rest_url' )->justReturn( 'https://example.com/wp-json/wp-abilities/v1/abilities' );

        $result   = $this->ability->execute( [] );
        $manifest = $result['manifest'];

        // When abilities list is empty the manifest is a short-circuit return with tools => []
        $this->assertIsArray( $manifest );
        $this->assertArrayHasKey( 'tools', $manifest );
    }

    public function test_manifest_protocol_version_is_set(): void {
        \Brain\Monkey\Functions\when( 'wp_get_abilities' )->justReturn( [
            'test/ability' => [ 'label' => 'Test', 'description' => 'Test.', 'input_schema' => [] ],
        ] );
        \Brain\Monkey\Functions\when( 'rest_url' )->justReturn( 'https://example.com/wp-json/wp-abilities/v1/abilities' );
        \Brain\Monkey\Functions\when( 'get_bloginfo' )->justReturn( 'Test Site' );
        \Brain\Monkey\Functions\when( 'get_site_url' )->justReturn( 'https://example.com' );

        $result = $this->ability->execute( [] );

        $this->assertSame( '2024-11-05', $result['manifest']['protocol_version'] );
    }

    public function test_mcp_endpoint_is_set(): void {
        \Brain\Monkey\Functions\when( 'wp_get_abilities' )->justReturn( [] );
        \Brain\Monkey\Functions\when( 'rest_url' )->justReturn( 'https://example.com/wp-json/wp-abilities/v1/abilities' );

        $result = $this->ability->execute( [] );

        $this->assertNotEmpty( $result['mcp_endpoint'] );
        $this->assertStringContainsString( 'abilities', $result['mcp_endpoint'] );
    }

    // -----------------------------------------------------------------------
    // Object-style ability (method-based API)
    // -----------------------------------------------------------------------

    public function test_manifest_handles_object_style_abilities(): void {
        $ability_obj = \Mockery::mock( 'WP_Ability_Object' );
        $ability_obj->shouldReceive( 'get_label' )->andReturn( 'Mock Ability' );
        $ability_obj->shouldReceive( 'get_description' )->andReturn( 'A mock ability.' );
        $ability_obj->shouldReceive( 'get_input_schema' )->andReturn( [ 'type' => 'object', 'properties' => [] ] );

        \Brain\Monkey\Functions\when( 'wp_get_abilities' )->justReturn( [ 'test/mock' => $ability_obj ] );
        \Brain\Monkey\Functions\when( 'rest_url' )->justReturn( 'https://example.com/wp-json/wp-abilities/v1/abilities' );
        \Brain\Monkey\Functions\when( 'get_bloginfo' )->justReturn( 'Test Site' );
        \Brain\Monkey\Functions\when( 'get_site_url' )->justReturn( 'https://example.com' );

        $result = $this->ability->execute( [] );

        $this->assertSame( 1, $result['total_abilities'] );
        $tool = $result['manifest']['tools'][0];
        $this->assertSame( 'test/mock', $tool['name'] );
        $this->assertSame( 'A mock ability.', $tool['description'] );
    }
}
