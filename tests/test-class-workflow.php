<?php
/**
 * Tests for AbilityHub_Workflow.
 *
 * @package AbilityHub
 */

require_once __DIR__ . '/class-abilityhub-test-case.php';

class Test_AbilityHub_Workflow extends AbilityHub_Test_Case {

    // -----------------------------------------------------------------------
    // Constructor / getters
    // -----------------------------------------------------------------------

    public function test_get_id_returns_provided_id(): void {
        $wf = new AbilityHub_Workflow( 'my-plugin/test', [
            'trigger' => 'post_published',
            'chain'   => [],
        ] );
        $this->assertSame( 'my-plugin/test', $wf->get_id() );
    }

    public function test_get_trigger_returns_trigger(): void {
        $wf = new AbilityHub_Workflow( 'test', [ 'trigger' => 'image_uploaded', 'chain' => [] ] );
        $this->assertSame( 'image_uploaded', $wf->get_trigger() );
    }

    public function test_get_chain_returns_ordered_ability_list(): void {
        $chain = [ 'abilityhub/generate-meta-description', 'abilityhub/suggest-internal-links' ];
        $wf    = new AbilityHub_Workflow( 'test', [ 'trigger' => 'post_published', 'chain' => $chain ] );
        $this->assertSame( $chain, $wf->get_chain() );
    }

    public function test_get_chain_defaults_to_empty_array(): void {
        $wf = new AbilityHub_Workflow( 'test', [ 'trigger' => 'post_published' ] );
        $this->assertSame( [], $wf->get_chain() );
    }

    // -----------------------------------------------------------------------
    // Guardrails / require_approval
    // -----------------------------------------------------------------------

    public function test_requires_approval_defaults_to_true(): void {
        $wf = new AbilityHub_Workflow( 'test', [ 'trigger' => 'post_published', 'chain' => [] ] );
        $this->assertTrue( $wf->requires_approval() );
    }

    public function test_requires_approval_can_be_disabled(): void {
        $wf = new AbilityHub_Workflow( 'test', [
            'trigger'    => 'post_published',
            'chain'      => [],
            'guardrails' => [ 'require_approval' => false ],
        ] );
        $this->assertFalse( $wf->requires_approval() );
    }

    public function test_get_guardrails_merges_with_defaults(): void {
        $wf         = new AbilityHub_Workflow( 'test', [ 'trigger' => 'x', 'chain' => [] ] );
        $guardrails = $wf->get_guardrails();
        $this->assertArrayHasKey( 'require_approval', $guardrails );
        $this->assertTrue( $guardrails['require_approval'] );
    }

    // -----------------------------------------------------------------------
    // on_complete callback
    // -----------------------------------------------------------------------

    public function test_get_on_complete_returns_null_when_not_set(): void {
        $wf = new AbilityHub_Workflow( 'test', [ 'trigger' => 'x', 'chain' => [] ] );
        $this->assertNull( $wf->get_on_complete() );
    }

    public function test_get_on_complete_returns_callable(): void {
        $callback = static fn() => 'done';
        $wf       = new AbilityHub_Workflow( 'test', [
            'trigger'     => 'x',
            'chain'       => [],
            'on_complete' => $callback,
        ] );
        $this->assertSame( $callback, $wf->get_on_complete() );
    }

    public function test_non_callable_on_complete_is_ignored(): void {
        $wf = new AbilityHub_Workflow( 'test', [
            'trigger'     => 'x',
            'chain'       => [],
            'on_complete' => 'not_a_real_function_xyz_12345',
        ] );
        $this->assertNull( $wf->get_on_complete() );
    }
}
