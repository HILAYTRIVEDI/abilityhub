<?php
/**
 * Trigger manager — bridges WordPress action hooks to workflow execution.
 *
 * Maps AbilityHub trigger names to their corresponding WordPress hooks
 * and extracts the context data passed to each workflow in the chain.
 *
 * @package AbilityHub
 */

defined( 'ABSPATH' ) || exit;

class AbilityHub_Trigger_Manager {

	/**
	 * Map of trigger names to WordPress hook configuration.
	 *
	 * Each entry defines:
	 *   - hook:    The WordPress action hook name.
	 *   - args:    Number of arguments the hook passes.
	 *   - context: Callable that receives the hook arguments and returns
	 *              the context array passed to the workflow chain.
	 *
	 * @var array<string, array>
	 */
	private array $trigger_map;

	/**
	 * The workflow registry.
	 *
	 * @var AbilityHub_Workflow_Registry
	 */
	private AbilityHub_Workflow_Registry $registry;

	/**
	 * The workflow runner.
	 *
	 * @var AbilityHub_Workflow_Runner
	 */
	private AbilityHub_Workflow_Runner $runner;

	/**
	 * Constructor.
	 *
	 * @param AbilityHub_Workflow_Registry $registry Workflow registry instance.
	 * @param AbilityHub_Workflow_Runner   $runner   Workflow runner instance.
	 */
	public function __construct(
		AbilityHub_Workflow_Registry $registry,
		AbilityHub_Workflow_Runner $runner
	) {
		$this->registry = $registry;
		$this->runner   = $runner;

		$this->trigger_map = [
			'post_published'    => [
				'hook'    => 'publish_post',
				'args'    => 2,
				'context' => static function ( $post_id, $post ) {
					return [
						'post_id'      => absint( $post_id ),
						'post_title'   => sanitize_text_field( $post->post_title ),
						'post_content' => wp_strip_all_tags( $post->post_content ),
						'post_url'     => get_permalink( $post_id ),
					];
				},
			],
			'image_uploaded'    => [
				'hook'    => 'add_attachment',
				'args'    => 1,
				'context' => static function ( $attachment_id ) {
					return [
						'attachment_id' => absint( $attachment_id ),
						'image_url'     => wp_get_attachment_url( $attachment_id ),
						'filename'      => sanitize_file_name( basename( (string) get_attached_file( $attachment_id ) ) ),
					];
				},
			],
			'comment_submitted' => [
				'hook'    => 'wp_insert_comment',
				'args'    => 2,
				'context' => static function ( $comment_id, $comment ) {
					return [
						'comment_id'   => absint( $comment_id ),
						'comment_text' => sanitize_text_field( $comment->comment_content ),
						'author_name'  => sanitize_text_field( $comment->comment_author ),
						'post_id'      => absint( $comment->comment_post_ID ),
					];
				},
			],
		];
	}

	/**
	 * Register WordPress hooks for every trigger that has at least one workflow.
	 *
	 * Call this after all workflows have been registered via
	 * abilityhub_register_workflow().
	 *
	 * @return void
	 */
	public function listen(): void {
		foreach ( $this->trigger_map as $trigger_name => $config ) {
			$workflows = $this->registry->get_by_trigger( $trigger_name );

			if ( empty( $workflows ) ) {
				continue;
			}

			add_action(
				$config['hook'],
				function () use ( $trigger_name, $config, $workflows ) {
					$hook_args = func_get_args();
					$context   = call_user_func_array( $config['context'], $hook_args );

					foreach ( $workflows as $workflow ) {
						$this->runner->run( $workflow, $context );
					}
				},
				20,
				$config['args']
			);
		}
	}

	/**
	 * Get the list of all supported trigger names.
	 *
	 * @return string[]
	 */
	public function get_supported_triggers(): array {
		return array_keys( $this->trigger_map );
	}
}
