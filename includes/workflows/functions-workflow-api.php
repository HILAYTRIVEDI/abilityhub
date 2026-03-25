<?php
/**
 * Public API functions for the AbilityHub workflow engine.
 *
 * @package AbilityHub
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register an autonomous AI workflow.
 *
 * Workflows chain multiple abilities together and execute them automatically
 * in response to WordPress events. Results can be held for human review
 * (require_approval: true, the default) or applied immediately.
 *
 * Usage:
 *   abilityhub_register_workflow( 'auto-seo', [
 *       'trigger'     => 'post_published',
 *       'chain'       => [ 'abilityhub/generate-meta-description', 'abilityhub/suggest-internal-links' ],
 *       'guardrails'  => [ 'require_approval' => true ],
 *       'on_complete' => function( $results, $context ) {
 *           update_post_meta( $context['post_id'], '_seo_meta', $results[0]['output']['meta_description'] );
 *       },
 *   ] );
 *
 * @since 2.0.0
 *
 * @param string               $id   Unique workflow identifier (e.g. 'my-plugin/auto-seo').
 * @param array<string, mixed> $args {
 *     Workflow arguments.
 *
 *     @type string   $trigger     Required. Event name: 'post_published', 'image_uploaded',
 *                                 or 'comment_submitted'.
 *     @type string[] $chain       Required. Ordered list of ability names to execute.
 *     @type array    $guardrails  Optional. Guardrail settings.
 *                                 Defaults: { require_approval: true }.
 *     @type callable $on_complete Optional. Callback fired with ($results, $context) when
 *                                 the chain completes and (if required) is approved.
 * }
 * @return true|WP_Error True on success, WP_Error if the ID is already registered.
 */
function abilityhub_register_workflow( string $id, array $args ) {
	$workflow = new AbilityHub_Workflow( $id, $args );
	return AbilityHub_Workflow_Registry::get_instance()->register( $workflow );
}

/**
 * Get a registered workflow by ID.
 *
 * @since 2.0.0
 *
 * @param string $id Workflow identifier.
 * @return AbilityHub_Workflow|null The workflow, or null if not found.
 */
function abilityhub_get_workflow( string $id ): ?AbilityHub_Workflow {
	return AbilityHub_Workflow_Registry::get_instance()->get( $id );
}

/**
 * Get all registered workflows.
 *
 * @since 2.0.0
 *
 * @return AbilityHub_Workflow[]
 */
function abilityhub_get_workflows(): array {
	return AbilityHub_Workflow_Registry::get_instance()->get_all();
}
