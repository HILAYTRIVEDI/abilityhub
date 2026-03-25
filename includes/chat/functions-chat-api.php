<?php
/**
 * Public API functions and REST endpoints for the AbilityHub chat engine.
 *
 * @package AbilityHub
 */

defined( 'ABSPATH' ) || exit;

// -------------------------------------------------------------------------
// REST routes
// -------------------------------------------------------------------------

add_action( 'rest_api_init', 'abilityhub_register_chat_routes' );

/**
 * Register the chat REST API routes.
 *
 * @since 3.0.0
 */
function abilityhub_register_chat_routes(): void {
	// POST /abilityhub/v1/chat — send a message.
	register_rest_route( 'abilityhub/v1', '/chat', [
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'abilityhub_rest_chat',
		'permission_callback' => static fn() => current_user_can( 'edit_posts' ),
		'args'                => [
			'message' => [
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
				'validate_callback' => static fn( $v ) => is_string( $v ) && '' !== trim( $v ),
			],
		],
	] );

	// GET /abilityhub/v1/chat/history — load history.
	register_rest_route( 'abilityhub/v1', '/chat/history', [
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'abilityhub_rest_chat_get_history',
		'permission_callback' => static fn() => current_user_can( 'edit_posts' ),
	] );

	// DELETE /abilityhub/v1/chat/history — clear history.
	register_rest_route( 'abilityhub/v1', '/chat/history', [
		'methods'             => WP_REST_Server::DELETABLE,
		'callback'            => 'abilityhub_rest_chat_clear_history',
		'permission_callback' => static fn() => current_user_can( 'edit_posts' ),
	] );

	// GET /abilityhub/v1/batch/{id} — batch job status.
	register_rest_route( 'abilityhub/v1', '/batch/(?P<id>\d+)', [
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'abilityhub_rest_batch_status',
		'permission_callback' => static fn() => current_user_can( 'edit_posts' ),
		'args'                => [
			'id' => [
				'required'          => true,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			],
		],
	] );
}

/**
 * REST callback: send a chat message to the AI Site Operator.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function abilityhub_rest_chat( WP_REST_Request $request ): WP_REST_Response|WP_Error {
	$handler = abilityhub_get_default_handler();
	$result  = $handler->handle( $request->get_param( 'message' ), get_current_user_id() );

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	return new WP_REST_Response( $result, 200 );
}

/**
 * REST callback: retrieve conversation history for the current user.
 *
 * @return WP_REST_Response
 */
function abilityhub_rest_chat_get_history(): WP_REST_Response {
	$store   = new AbilityHub_Conversation_Store();
	$history = $store->load( get_current_user_id() );
	return new WP_REST_Response( [ 'history' => $history ], 200 );
}

/**
 * REST callback: clear conversation history for the current user.
 *
 * @return WP_REST_Response
 */
function abilityhub_rest_chat_clear_history(): WP_REST_Response {
	$store = new AbilityHub_Conversation_Store();
	$store->clear( get_current_user_id() );
	return new WP_REST_Response( [ 'cleared' => true ], 200 );
}

/**
 * REST callback: get the status of a batch job.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function abilityhub_rest_batch_status( WP_REST_Request $request ): WP_REST_Response|WP_Error {
	$batch_id = absint( $request->get_param( 'id' ) );
	$post     = get_post( $batch_id );

	if ( ! $post || 'abilityhub_batch' !== $post->post_type ) {
		return new WP_Error( 'not_found', __( 'Batch job not found.', 'abilityhub' ), [ 'status' => 404 ] );
	}

	return new WP_REST_Response( [
		'id'       => $batch_id,
		'status'   => $post->post_status,
		'ability'  => get_post_meta( $batch_id, '_abilityhub_batch_ability',  true ),
		'progress' => (int) get_post_meta( $batch_id, '_abilityhub_batch_progress', true ),
		'total'    => (int) get_post_meta( $batch_id, '_abilityhub_batch_total',    true ),
	], 200 );
}

// -------------------------------------------------------------------------
// Batch CPT
// -------------------------------------------------------------------------

/**
 * Register the batch job custom post type.
 *
 * @since 3.0.0
 */
function abilityhub_register_batch_post_type(): void {
	register_post_type( 'abilityhub_batch', [
		'label'               => __( 'AbilityHub Batch Jobs', 'abilityhub' ),
		'public'              => false,
		'show_ui'             => false,
		'show_in_rest'        => false,
		'rewrite'             => false,
		'query_var'           => false,
		'supports'            => [ 'title', 'author', 'custom-fields' ],
		'capability_type'     => 'post',
		'map_meta_cap'        => true,
	] );
}

// -------------------------------------------------------------------------
// Cron: batch processor
// -------------------------------------------------------------------------

/**
 * WP-Cron callback to process a queued batch job.
 *
 * Reads the batch CPT post, resolves the target posts, runs the ability
 * on each one, and updates progress meta until all posts are done.
 *
 * @since 3.0.0
 *
 * @param int $batch_id The abilityhub_batch post ID.
 * @return void
 */
function abilityhub_cron_process_batch( int $batch_id ): void {
	if ( ! function_exists( 'wp_execute_ability' ) ) {
		return;
	}

	$post = get_post( $batch_id );
	if ( ! $post || 'abilityhub_batch' !== $post->post_type ) {
		return;
	}

	// Avoid re-running a completed or already-running job.
	if ( in_array( $post->post_status, [ 'publish', 'trash' ], true ) ) {
		return;
	}

	$ability     = get_post_meta( $batch_id, '_abilityhub_batch_ability',     true );
	$post_type   = get_post_meta( $batch_id, '_abilityhub_batch_post_type',   true ) ?: 'post';
	$post_status = get_post_meta( $batch_id, '_abilityhub_batch_post_status', true ) ?: 'publish';
	$limit       = (int) get_post_meta( $batch_id, '_abilityhub_batch_limit', true ) ?: 10;
	$input_map   = json_decode( get_post_meta( $batch_id, '_abilityhub_batch_input_map', true ) ?: '{}', true );

	// Try stored post IDs first (from abilityhub_queue_batch), then query.
	$stored_ids_raw = get_post_meta( $batch_id, '_abilityhub_batch_post_ids', true );
	if ( $stored_ids_raw ) {
		$post_ids = array_map( 'absint', (array) json_decode( $stored_ids_raw, true ) );
	} else {
		$post_ids = get_posts( [
			'post_type'      => $post_type,
			'post_status'    => $post_status,
			'posts_per_page' => $limit,
			'fields'         => 'ids',
		] );
	}

	$total    = count( $post_ids );
	$progress = 0;

	update_post_meta( $batch_id, '_abilityhub_batch_total', $total );

	foreach ( $post_ids as $pid ) {
		$wp_post = get_post( $pid );
		if ( ! $wp_post ) {
			$progress++;
			continue;
		}

		// Build the input from the input_map (maps ability input keys → WP_Post fields).
		$input = [];
		foreach ( (array) $input_map as $ability_key => $post_field ) {
			$input[ $ability_key ] = is_string( $post_field ) && isset( $wp_post->$post_field )
				? (string) $wp_post->$post_field
				: '';
		}
		if ( empty( $input ) ) {
			$input = [ 'content' => $wp_post->post_content, 'post_id' => $pid ];
		}

		wp_execute_ability( $ability, $input );

		$progress++;
		update_post_meta( $batch_id, '_abilityhub_batch_progress', $progress );
	}

	// Mark the job as complete.
	wp_update_post( [ 'ID' => $batch_id, 'post_status' => 'publish' ] );
}
add_action( 'abilityhub_process_batch', 'abilityhub_cron_process_batch' );

// -------------------------------------------------------------------------
// Helper functions (public API)
// -------------------------------------------------------------------------

/**
 * Get the singleton AbilityHub_Chat_Handler instance.
 *
 * @since 3.0.0
 *
 * @return AbilityHub_Chat_Handler
 */
function abilityhub_get_default_handler(): AbilityHub_Chat_Handler {
	static $handler = null;

	if ( null === $handler ) {
		$scanner = new AbilityHub_Site_Scanner();
		$handler = new AbilityHub_Chat_Handler(
			new AbilityHub_Prompt_Builder( $scanner ),
			new AbilityHub_Intent_Parser(),
			new AbilityHub_Intent_Executor(),
			new AbilityHub_Conversation_Store()
		);
	}

	return $handler;
}

/**
 * Persist a dynamically-created workflow so it survives page reloads.
 *
 * Workflows registered only in PHP memory are lost on the next request.
 * This function saves them to the 'abilityhub_saved_workflows' option so
 * they can be re-registered on every boot via abilityhub_load_saved_workflows().
 *
 * @since 3.0.0
 *
 * @param string               $workflow_id Unique workflow ID.
 * @param array<string, mixed> $config      Workflow config (trigger, chain, require_approval).
 * @return void
 */
function abilityhub_save_workflow_to_db( string $workflow_id, array $config ): void {
	$saved = abilityhub_load_saved_workflows();
	$saved[ sanitize_text_field( $workflow_id ) ] = $config;
	update_option( 'abilityhub_saved_workflows', $saved, false );
}

/**
 * Load all persisted workflows from the database.
 *
 * @since 3.0.0
 *
 * @return array<string, array<string, mixed>>
 */
function abilityhub_load_saved_workflows(): array {
	$saved = get_option( 'abilityhub_saved_workflows', [] );
	return is_array( $saved ) ? $saved : [];
}

/**
 * Queue a batch of specific post IDs for ability processing.
 *
 * @since 3.0.0
 *
 * @param string   $ability   Ability name to run.
 * @param int[]    $post_ids  Post IDs to process.
 * @param array    $input_map Field mapping (ability input key → WP_Post field).
 * @param int      $user_id   User who triggered the batch.
 * @return int|WP_Error Batch CPT post ID, or WP_Error.
 */
function abilityhub_queue_batch( string $ability, array $post_ids, array $input_map = [], int $user_id = 0 ): int|WP_Error {
	if ( empty( $post_ids ) ) {
		return new WP_Error( 'empty_batch', __( 'No posts to process.', 'abilityhub' ) );
	}

	$batch_id = wp_insert_post( [
		'post_type'   => 'abilityhub_batch',
		'post_status' => 'pending',
		'post_title'  => sprintf( 'Batch: %s (%d posts)', $ability, count( $post_ids ) ),
		'post_author' => $user_id,
		'meta_input'  => [
			'_abilityhub_batch_ability'   => sanitize_text_field( $ability ),
			'_abilityhub_batch_post_ids'  => wp_json_encode( array_map( 'absint', $post_ids ) ),
			'_abilityhub_batch_input_map' => wp_json_encode( $input_map ),
			'_abilityhub_batch_progress'  => 0,
			'_abilityhub_batch_total'     => count( $post_ids ),
		],
	] );

	if ( is_wp_error( $batch_id ) ) {
		return $batch_id;
	}

	wp_schedule_single_event( time() + 1, 'abilityhub_process_batch', [ $batch_id ] );
	return $batch_id;
}

/**
 * Mark a workflow as deactivated.
 *
 * Deactivated workflows remain registered but the trigger manager skips them.
 *
 * @since 3.0.0
 *
 * @param string $workflow_id
 * @return void
 */
function abilityhub_deactivate_workflow( string $workflow_id ): void {
	$deactivated = (array) get_option( 'abilityhub_deactivated_workflows', [] );
	$workflow_id = sanitize_text_field( $workflow_id );

	if ( ! in_array( $workflow_id, $deactivated, true ) ) {
		$deactivated[] = $workflow_id;
		update_option( 'abilityhub_deactivated_workflows', $deactivated, false );
	}
}

/**
 * Re-activate a previously deactivated workflow.
 *
 * @since 3.0.0
 *
 * @param string $workflow_id
 * @return void
 */
function abilityhub_activate_workflow( string $workflow_id ): void {
	$deactivated = (array) get_option( 'abilityhub_deactivated_workflows', [] );
	$workflow_id = sanitize_text_field( $workflow_id );
	$deactivated = array_values( array_filter( $deactivated, static fn( $id ) => $id !== $workflow_id ) );
	update_option( 'abilityhub_deactivated_workflows', $deactivated, false );
}
