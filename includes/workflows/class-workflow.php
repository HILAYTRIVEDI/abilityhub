<?php
/**
 * Workflow data structure.
 *
 * Immutable object that holds a workflow definition.
 * Does nothing on its own — pass it to the runner to execute.
 *
 * @package AbilityHub
 */

defined( 'ABSPATH' ) || exit;

class AbilityHub_Workflow {

	/**
	 * Unique workflow identifier.
	 *
	 * @var string
	 */
	private string $id;

	/**
	 * The trigger event name (e.g. 'post_published').
	 *
	 * @var string
	 */
	private string $trigger;

	/**
	 * Ordered list of ability names to execute in sequence.
	 *
	 * @var string[]
	 */
	private array $chain;

	/**
	 * Guardrail settings.
	 *
	 * @var array<string, mixed>
	 */
	private array $guardrails;

	/**
	 * Optional callback fired when the chain completes and is approved.
	 *
	 * @var callable|null
	 */
	private $on_complete;

	/**
	 * Constructor.
	 *
	 * @param string               $id   Unique workflow identifier.
	 * @param array<string, mixed> $args {
	 *     @type string   $trigger     Event name: 'post_published', 'image_uploaded', 'comment_submitted'.
	 *     @type string[] $chain       Ordered ability names to execute.
	 *     @type array    $guardrails  Optional. Defaults: { require_approval: true }.
	 *     @type callable $on_complete Optional. Called with ($results, $context) on completion.
	 * }
	 */
	public function __construct( string $id, array $args ) {
		$this->id          = $id;
		$this->trigger     = $args['trigger'] ?? '';
		$this->chain       = (array) ( $args['chain'] ?? [] );
		$this->guardrails  = wp_parse_args(
			$args['guardrails'] ?? [],
			[ 'require_approval' => true ]
		);
		$this->on_complete = isset( $args['on_complete'] ) && is_callable( $args['on_complete'] )
			? $args['on_complete']
			: null;
	}

	/**
	 * Get the workflow ID.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return $this->id;
	}

	/**
	 * Get the trigger event name.
	 *
	 * @return string
	 */
	public function get_trigger(): string {
		return $this->trigger;
	}

	/**
	 * Get the ordered chain of ability names.
	 *
	 * @return string[]
	 */
	public function get_chain(): array {
		return $this->chain;
	}

	/**
	 * Get all guardrail settings.
	 *
	 * @return array<string, mixed>
	 */
	public function get_guardrails(): array {
		return $this->guardrails;
	}

	/**
	 * Whether this workflow requires human approval before completing.
	 *
	 * @return bool
	 */
	public function requires_approval(): bool {
		return ! empty( $this->guardrails['require_approval'] );
	}

	/**
	 * Get the on_complete callback, or null if not set.
	 *
	 * @return callable|null
	 */
	public function get_on_complete(): ?callable {
		return $this->on_complete;
	}
}
