<?php
/**
 * Tests for AbilityHub_Workflow_Registry.
 *
 * @package AbilityHub
 */

require_once __DIR__ . '/class-abilityhub-test-case.php';

class Test_AbilityHub_Workflow_Registry extends AbilityHub_Test_Case {

    protected function setUp(): void {
        parent::setUp();
        $this->reset_workflow_registry();
    }

    protected function tearDown(): void {
        $this->reset_workflow_registry();
        parent::tearDown();
    }

    private function make_workflow( string $id, string $trigger = 'post_published' ): AbilityHub_Workflow {
        return new AbilityHub_Workflow( $id, [ 'trigger' => $trigger, 'chain' => [] ] );
    }

    // -----------------------------------------------------------------------
    // Singleton
    // -----------------------------------------------------------------------

    public function test_get_instance_returns_same_object(): void {
        $a = AbilityHub_Workflow_Registry::get_instance();
        $b = AbilityHub_Workflow_Registry::get_instance();
        $this->assertSame( $a, $b );
    }

    // -----------------------------------------------------------------------
    // register()
    // -----------------------------------------------------------------------

    public function test_register_returns_true_on_success(): void {
        $result = AbilityHub_Workflow_Registry::get_instance()->register( $this->make_workflow( 'test/wf-1' ) );
        $this->assertTrue( $result );
    }

    public function test_register_duplicate_returns_wp_error(): void {
        $registry = AbilityHub_Workflow_Registry::get_instance();
        $registry->register( $this->make_workflow( 'test/dup' ) );

        $result = $registry->register( $this->make_workflow( 'test/dup' ) );

        $this->assertIsWpError( $result, 'workflow_exists' );
    }

    // -----------------------------------------------------------------------
    // get() / has()
    // -----------------------------------------------------------------------

    public function test_get_returns_registered_workflow(): void {
        $wf = $this->make_workflow( 'test/get' );
        AbilityHub_Workflow_Registry::get_instance()->register( $wf );

        $retrieved = AbilityHub_Workflow_Registry::get_instance()->get( 'test/get' );
        $this->assertSame( $wf, $retrieved );
    }

    public function test_get_returns_null_for_unknown_id(): void {
        $this->assertNull( AbilityHub_Workflow_Registry::get_instance()->get( 'does/not/exist' ) );
    }

    public function test_has_returns_true_after_registration(): void {
        AbilityHub_Workflow_Registry::get_instance()->register( $this->make_workflow( 'test/has' ) );
        $this->assertTrue( AbilityHub_Workflow_Registry::get_instance()->has( 'test/has' ) );
    }

    public function test_has_returns_false_before_registration(): void {
        $this->assertFalse( AbilityHub_Workflow_Registry::get_instance()->has( 'test/missing' ) );
    }

    // -----------------------------------------------------------------------
    // get_by_trigger()
    // -----------------------------------------------------------------------

    public function test_get_by_trigger_returns_matching_workflows(): void {
        $registry = AbilityHub_Workflow_Registry::get_instance();
        $wf1      = $this->make_workflow( 'test/pub-1', 'post_published' );
        $wf2      = $this->make_workflow( 'test/pub-2', 'post_published' );
        $wf3      = $this->make_workflow( 'test/img-1', 'image_uploaded' );

        $registry->register( $wf1 );
        $registry->register( $wf2 );
        $registry->register( $wf3 );

        $by_trigger = $registry->get_by_trigger( 'post_published' );
        $this->assertCount( 2, $by_trigger );
        $this->assertArrayHasKey( 'test/pub-1', $by_trigger );
        $this->assertArrayHasKey( 'test/pub-2', $by_trigger );
    }

    public function test_get_by_trigger_returns_empty_array_when_none_match(): void {
        $this->assertSame( [], AbilityHub_Workflow_Registry::get_instance()->get_by_trigger( 'comment_submitted' ) );
    }

    // -----------------------------------------------------------------------
    // get_all()
    // -----------------------------------------------------------------------

    public function test_get_all_returns_all_registered_workflows(): void {
        $registry = AbilityHub_Workflow_Registry::get_instance();
        $registry->register( $this->make_workflow( 'test/all-1' ) );
        $registry->register( $this->make_workflow( 'test/all-2' ) );

        $all = $registry->get_all();
        $this->assertCount( 2, $all );
        $this->assertArrayHasKey( 'test/all-1', $all );
        $this->assertArrayHasKey( 'test/all-2', $all );
    }

    public function test_get_all_returns_empty_array_when_nothing_registered(): void {
        $this->assertSame( [], AbilityHub_Workflow_Registry::get_instance()->get_all() );
    }
}
