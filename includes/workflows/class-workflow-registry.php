<?php
/**
 * Workflow registry — in-memory storage for all registered workflows.
 *
 * @package AbilityHub
 */

defined( 'ABSPATH' ) || exit;

class AbilityHub_Workflow_Registry {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * All registered workflows, keyed by ID.
	 *
	 * @var AbilityHub_Workflow[]
	 */
	private array $workflows = [];

	/**
	 * Private constructor — use get_instance().
	 */
	private function __construct() {}

	/**
	 * Get the singleton instance.
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register a workflow.
	 *
	 * @param AbilityHub_Workflow $workflow The workflow to register.
	 * @return true|WP_Error True on success, WP_Error if already registered.
	 */
	public function register( AbilityHub_Workflow $workflow ) {
		$id = $workflow->get_id();

		if ( isset( $this->workflows[ $id ] ) ) {
			return new WP_Error(
				'workflow_exists',
				/* translators: %s: workflow ID */
				sprintf( __( 'Workflow "%s" is already registered.', 'abilityhub' ), $id )
			);
		}

		$this->workflows[ $id ] = $workflow;

		return true;
	}

	/**
	 * Get a workflow by ID.
	 *
	 * @param string $id Workflow identifier.
	 * @return AbilityHub_Workflow|null
	 */
	public function get( string $id ): ?AbilityHub_Workflow {
		return $this->workflows[ $id ] ?? null;
	}

	/**
	 * Get all workflows that use a given trigger.
	 *
	 * @param string $trigger Trigger event name.
	 * @return AbilityHub_Workflow[]
	 */
	public function get_by_trigger( string $trigger ): array {
		return array_filter(
			$this->workflows,
			static function ( AbilityHub_Workflow $wf ) use ( $trigger ) {
				return $wf->get_trigger() === $trigger;
			}
		);
	}

	/**
	 * Get all registered workflows.
	 *
	 * @return AbilityHub_Workflow[]
	 */
	public function get_all(): array {
		return $this->workflows;
	}

	/**
	 * Check whether a workflow is registered.
	 *
	 * @param string $id Workflow identifier.
	 * @return bool
	 */
	public function has( string $id ): bool {
		return isset( $this->workflows[ $id ] );
	}
}
