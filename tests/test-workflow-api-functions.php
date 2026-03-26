<?php
/**
 * Tests for the workflow public API functions in functions-workflow-api.php.
 *
 * @package AbilityHub
 */

require_once __DIR__ . '/class-abilityhub-test-case.php';

class Test_Workflow_Api_Functions extends AbilityHub_Test_Case {

    protected function setUp(): void {
        parent::setUp();
        $this->reset_workflow_registry();
    }

    protected function tearDown(): void {
        $this->reset_workflow_registry();
        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // abilityhub_register_workflow()
    // -----------------------------------------------------------------------

    public function test_register_workflow_returns_true_on_first_registration(): void {
        $result = abilityhub_register_workflow( 'test/api-wf', [
            'trigger' => 'post_published',
            'chain'   => [ 'abilityhub/generate-meta-description' ],
        ] );
        $this->assertTrue( $result );
    }

    public function test_register_workflow_returns_wp_error_on_duplicate(): void {
        abilityhub_register_workflow( 'test/dup-api', [ 'trigger' => 'post_published', 'chain' => [] ] );
        $result = abilityhub_register_workflow( 'test/dup-api', [ 'trigger' => 'post_published', 'chain' => [] ] );

        $this->assertIsWpError( $result, 'workflow_exists' );
    }

    // -----------------------------------------------------------------------
    // abilityhub_get_workflow()
    // -----------------------------------------------------------------------

    public function test_get_workflow_returns_registered_workflow(): void {
        abilityhub_register_workflow( 'test/get-api', [ 'trigger' => 'post_published', 'chain' => [] ] );
        $wf = abilityhub_get_workflow( 'test/get-api' );

        $this->assertInstanceOf( AbilityHub_Workflow::class, $wf );
        $this->assertSame( 'test/get-api', $wf->get_id() );
    }

    public function test_get_workflow_returns_null_for_unknown(): void {
        $this->assertNull( abilityhub_get_workflow( 'does/not/exist' ) );
    }

    // -----------------------------------------------------------------------
    // abilityhub_get_workflows()
    // -----------------------------------------------------------------------

    public function test_get_workflows_returns_all_registered(): void {
        abilityhub_register_workflow( 'test/list-1', [ 'trigger' => 'post_published', 'chain' => [] ] );
        abilityhub_register_workflow( 'test/list-2', [ 'trigger' => 'image_uploaded', 'chain' => [] ] );

        $all = abilityhub_get_workflows();
        $this->assertCount( 2, $all );
    }

    public function test_get_workflows_returns_empty_array_initially(): void {
        $this->assertSame( [], abilityhub_get_workflows() );
    }

    // -----------------------------------------------------------------------
    // Workflow chain is stored correctly
    // -----------------------------------------------------------------------

    public function test_registered_workflow_has_correct_chain(): void {
        $chain = [ 'abilityhub/generate-meta-description', 'abilityhub/summarise-post' ];
        abilityhub_register_workflow( 'test/chain', [ 'trigger' => 'post_published', 'chain' => $chain ] );

        $wf = abilityhub_get_workflow( 'test/chain' );
        $this->assertSame( $chain, $wf->get_chain() );
    }

    public function test_registered_workflow_requires_approval_by_default(): void {
        abilityhub_register_workflow( 'test/approval', [ 'trigger' => 'post_published', 'chain' => [] ] );
        $wf = abilityhub_get_workflow( 'test/approval' );

        $this->assertTrue( $wf->requires_approval() );
    }
}
