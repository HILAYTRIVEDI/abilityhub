<?php
/**
 * Approval queue — holds workflow results pending human review.
 *
 * Uses a custom post type as storage so results survive server restarts
 * and benefit from WordPress's built-in capabilities system.
 *
 * @package AbilityHub
 */

defined( 'ABSPATH' ) || exit;

class AbilityHub_Approval_Queue {

	/**
	 * The custom post type slug.
	 *
	 * @var string
	 */
	const POST_TYPE = 'abilityhub_pending';

	/**
	 * Register the custom post type used as the queue store.
	 *
	 * Hook this onto 'init'.
	 *
	 * @return void
	 */
	public static function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			[
				'labels'       => [
					'name'          => __( 'Pending Approvals', 'abilityhub' ),
					'singular_name' => __( 'Pending Approval', 'abilityhub' ),
				],
				'description'  => __( 'Workflow results awaiting human review before being applied.', 'abilityhub' ),
				'public'       => false,
				'show_ui'      => false, // We render our own admin view.
				'show_in_menu' => false,
				'supports'     => [ 'title' ],
				'capabilities' => [ 'create_posts' => 'do_not_allow' ],
				'map_meta_cap' => true,
			]
		);
	}

	/**
	 * Add a completed workflow result to the approval queue.
	 *
	 * @param AbilityHub_Workflow   $workflow The workflow that produced the results.
	 * @param array<string, mixed>  $context  The trigger context.
	 * @param array<int, array>     $results  Per-ability outputs from the chain.
	 * @return int The post ID of the queued item.
	 */
	public function enqueue( AbilityHub_Workflow $workflow, array $context, array $results ): int {
		$title = sprintf(
			/* translators: 1: workflow ID, 2: date/time */
			__( '%1$s — %2$s', 'abilityhub' ),
			$workflow->get_id(),
			current_time( 'mysql' )
		);

		$post_id = wp_insert_post( [
			'post_type'   => self::POST_TYPE,
			'post_title'  => $title,
			'post_status' => 'pending',
		] );

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return 0;
		}

		update_post_meta( $post_id, '_abilityhub_workflow_id', $workflow->get_id() );
		update_post_meta( $post_id, '_abilityhub_trigger',     $workflow->get_trigger() );
		update_post_meta( $post_id, '_abilityhub_context',     $context );
		update_post_meta( $post_id, '_abilityhub_results',     $results );
		update_post_meta( $post_id, '_abilityhub_created_at',  current_time( 'mysql' ) );

		/**
		 * Fires after a workflow result is added to the approval queue.
		 *
		 * @since 2.0.0
		 *
		 * @param int                  $post_id  The queued item post ID.
		 * @param AbilityHub_Workflow  $workflow The workflow.
		 * @param array                $context  Trigger context.
		 * @param array                $results  Chain results.
		 */
		do_action( 'abilityhub_workflow_queued', $post_id, $workflow, $context, $results );

		return $post_id;
	}

	/**
	 * Approve a queued item: fire its on_complete callback and mark it published.
	 *
	 * @param int $post_id The queued item post ID.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	public function approve( int $post_id ) {
		$workflow_id = get_post_meta( $post_id, '_abilityhub_workflow_id', true );
		$context     = get_post_meta( $post_id, '_abilityhub_context',     true );
		$results     = get_post_meta( $post_id, '_abilityhub_results',     true );

		if ( ! $workflow_id ) {
			return new WP_Error(
				'invalid_queue_item',
				__( 'This item does not appear to be a valid pending workflow result.', 'abilityhub' )
			);
		}

		$workflow = AbilityHub_Workflow_Registry::get_instance()->get( $workflow_id );

		if ( ! $workflow ) {
			return new WP_Error(
				'workflow_not_found',
				/* translators: %s: workflow ID */
				sprintf( __( 'Workflow "%s" is no longer registered.', 'abilityhub' ), $workflow_id )
			);
		}

		$callback = $workflow->get_on_complete();

		if ( is_callable( $callback ) ) {
			call_user_func( $callback, (array) $results, (array) $context );
		}

		// Mark as approved (published = done).
		wp_update_post( [
			'ID'          => $post_id,
			'post_status' => 'publish',
		] );

		/**
		 * Fires after a queued workflow result is approved.
		 *
		 * @since 2.0.0
		 *
		 * @param int    $post_id     The queued item post ID.
		 * @param string $workflow_id The workflow identifier.
		 * @param array  $results     The chain results that were applied.
		 * @param array  $context     The original trigger context.
		 */
		do_action( 'abilityhub_workflow_approved', $post_id, $workflow_id, (array) $results, (array) $context );

		return true;
	}

	/**
	 * Reject and discard a queued item.
	 *
	 * @param int $post_id The queued item post ID.
	 * @return void
	 */
	public function reject( int $post_id ): void {
		$workflow_id = get_post_meta( $post_id, '_abilityhub_workflow_id', true );

		wp_trash_post( $post_id );

		/**
		 * Fires after a queued workflow result is rejected.
		 *
		 * @since 2.0.0
		 *
		 * @param int    $post_id     The queued item post ID.
		 * @param string $workflow_id The workflow identifier.
		 */
		do_action( 'abilityhub_workflow_rejected', $post_id, (string) $workflow_id );
	}

	/**
	 * Get all items currently pending approval.
	 *
	 * @param int $limit Maximum number of items to return. Default 50.
	 * @return WP_Post[]
	 */
	public function get_pending( int $limit = 50 ): array {
		return get_posts( [
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'pending',
			'numberposts'    => $limit,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		] );
	}

	/**
	 * Count items currently pending approval.
	 *
	 * @return int
	 */
	public function count_pending(): int {
		$counts = wp_count_posts( self::POST_TYPE );
		return isset( $counts->pending ) ? (int) $counts->pending : 0;
	}
}
