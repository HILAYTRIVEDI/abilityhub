<?php
/**
 * Workflow runner — executes a chain of abilities and handles guardrails.
 *
 * @package AbilityHub
 */

defined( 'ABSPATH' ) || exit;

class AbilityHub_Workflow_Runner {

	/**
	 * Approval queue for workflows that require human review.
	 *
	 * @var AbilityHub_Approval_Queue
	 */
	private AbilityHub_Approval_Queue $queue;

	/**
	 * Constructor.
	 *
	 * @param AbilityHub_Approval_Queue $queue The approval queue instance.
	 */
	public function __construct( AbilityHub_Approval_Queue $queue ) {
		$this->queue = $queue;
	}

	/**
	 * Execute a workflow's chain of abilities.
	 *
	 * Each ability in the chain receives the trigger context merged with
	 * all outputs from previous abilities, so every step can build on
	 * what came before.
	 *
	 * @param AbilityHub_Workflow      $workflow The workflow to execute.
	 * @param array<string, mixed>     $context  Trigger context data.
	 * @return array<int, array>|WP_Error Array of per-ability results, or WP_Error on failure.
	 */
	public function run( AbilityHub_Workflow $workflow, array $context ) {
		$chain         = $workflow->get_chain();
		$current_input = $context;
		$results       = [];

		foreach ( $chain as $ability_name ) {
			if ( ! function_exists( 'wp_execute_ability' ) ) {
				$error = new WP_Error(
					'no_abilities_api',
					__( 'WordPress Abilities API not available. Requires WordPress 7.0+.', 'abilityhub' )
				);
				$this->log_execution( $workflow, $context, $results, $error );
				return $error;
			}

			$result = wp_execute_ability( $ability_name, $current_input );

			if ( is_wp_error( $result ) ) {
				$this->log_execution( $workflow, $context, $results, $result );
				return $result;
			}

			$results[] = [
				'ability' => $ability_name,
				'output'  => $result,
			];

			// Merge this ability's output into the input for the next ability.
			// This is the core of the chain: each step enriches the context.
			if ( is_array( $result ) ) {
				$current_input = array_merge( $current_input, $result );
			}
		}

		// Apply guardrails.
		if ( $workflow->requires_approval() ) {
			// Hold results for human review before applying.
			$this->queue->enqueue( $workflow, $context, $results );
		} else {
			// Auto-apply: fire the completion callback immediately.
			$callback = $workflow->get_on_complete();
			if ( is_callable( $callback ) ) {
				call_user_func( $callback, $results, $context );
			}
		}

		$this->log_execution( $workflow, $context, $results, null );

		return $results;
	}

	/**
	 * Log a workflow execution via action hook.
	 *
	 * Other plugins and themes can hook into `abilityhub_workflow_executed`
	 * to react to workflow completions (e.g. custom logging, notifications).
	 *
	 * @param AbilityHub_Workflow      $workflow The workflow that ran.
	 * @param array<string, mixed>     $context  Trigger context.
	 * @param array<int, array>        $results  Per-ability results collected so far.
	 * @param WP_Error|null            $error    The error if the run failed, or null on success.
	 */
	private function log_execution(
		AbilityHub_Workflow $workflow,
		array $context,
		array $results,
		?WP_Error $error
	): void {
		/**
		 * Fires after a workflow has executed (successfully or not).
		 *
		 * @since 2.0.0
		 *
		 * @param array $data {
		 *     @type string        $workflow_id Workflow identifier.
		 *     @type string        $trigger     Trigger event name.
		 *     @type array         $context     Trigger context passed to the first ability.
		 *     @type array         $results     Outputs collected from each ability in the chain.
		 *     @type WP_Error|null $error       Error instance if run failed, null on success.
		 *     @type string        $timestamp   MySQL-formatted UTC timestamp.
		 * }
		 */
		do_action( 'abilityhub_workflow_executed', [
			'workflow_id' => $workflow->get_id(),
			'trigger'     => $workflow->get_trigger(),
			'context'     => $context,
			'results'     => $results,
			'error'       => $error,
			'timestamp'   => current_time( 'mysql' ),
		] );
	}
}
