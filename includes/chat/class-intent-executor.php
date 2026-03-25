<?php
/**
 * Executes validated intent objects from the AI Site Operator chat.
 *
 * @package AbilityHub
 */

defined( 'ABSPATH' ) || exit;

class AbilityHub_Intent_Executor {

	/**
	 * Execute an intent and return a result array.
	 *
	 * @param array<string, mixed> $intent  Validated intent from AbilityHub_Intent_Parser.
	 * @param int                  $user_id Acting user ID.
	 * @return array{success: bool, message: string, data: mixed}
	 */
	public function execute( array $intent, int $user_id = 0 ): array {
		switch ( $intent['intent'] ) {
			case 'run_ability':
				return $this->run_ability( $intent );

			case 'batch_process':
				return $this->batch_process( $intent, $user_id );

			case 'create_workflow':
				return $this->create_workflow( $intent );

			case 'deactivate_workflow':
				return $this->toggle_workflow( $intent['workflow_id'], false );

			case 'activate_workflow':
				return $this->toggle_workflow( $intent['workflow_id'], true );
		}

		return [
			'success' => false,
			'message' => __( 'Unknown intent type.', 'abilityhub' ),
			'data'    => null,
		];
	}

	// -------------------------------------------------------------------------
	// Private: intent handlers
	// -------------------------------------------------------------------------

	/**
	 * @param array<string, mixed> $intent
	 * @return array{success: bool, message: string, data: mixed}
	 */
	private function run_ability( array $intent ): array {
		if ( ! function_exists( 'wp_execute_ability' ) ) {
			return [
				'success' => false,
				'message' => __( 'Abilities API not available. Requires WordPress 7.0+.', 'abilityhub' ),
				'data'    => null,
			];
		}

		$result = wp_execute_ability( $intent['ability'], $intent['input'] );

		if ( is_wp_error( $result ) ) {
			return [ 'success' => false, 'message' => $result->get_error_message(), 'data' => null ];
		}

		return [
			'success' => true,
			/* translators: %s: ability name */
			'message' => sprintf( __( 'Ability "%s" executed successfully.', 'abilityhub' ), $intent['ability'] ),
			'data'    => $result,
		];
	}

	/**
	 * @param array<string, mixed> $intent
	 * @param int                  $user_id
	 * @return array{success: bool, message: string, data: mixed}
	 */
	private function batch_process( array $intent, int $user_id ): array {
		$batch_id = wp_insert_post( [
			'post_type'   => 'abilityhub_batch',
			'post_status' => 'pending',
			'post_title'  => sprintf(
				'Batch: %s on %s [%s]',
				$intent['ability'],
				$intent['post_type'],
				gmdate( 'Y-m-d H:i:s' )
			),
			'post_author' => $user_id,
			'meta_input'  => [
				'_abilityhub_batch_ability'     => $intent['ability'],
				'_abilityhub_batch_post_type'   => $intent['post_type'],
				'_abilityhub_batch_post_status' => $intent['post_status'],
				'_abilityhub_batch_limit'       => $intent['limit'],
				'_abilityhub_batch_input_map'   => wp_json_encode( $intent['input_map'] ),
				'_abilityhub_batch_progress'    => 0,
				'_abilityhub_batch_total'       => 0,
			],
		] );

		if ( is_wp_error( $batch_id ) ) {
			return [ 'success' => false, 'message' => $batch_id->get_error_message(), 'data' => null ];
		}

		wp_schedule_single_event( time() + 1, 'abilityhub_process_batch', [ $batch_id ] );

		return [
			'success' => true,
			'message' => sprintf(
				/* translators: 1: ability name, 2: limit, 3: post type */
				__( 'Batch job queued: run "%1$s" on up to %2$d %3$s posts.', 'abilityhub' ),
				$intent['ability'],
				$intent['limit'],
				$intent['post_type']
			),
			'data' => [ 'batch_id' => $batch_id ],
		];
	}

	/**
	 * @param array<string, mixed> $intent
	 * @return array{success: bool, message: string, data: mixed}
	 */
	private function create_workflow( array $intent ): array {
		if ( ! function_exists( 'abilityhub_register_workflow' ) ) {
			return [
				'success' => false,
				'message' => __( 'Workflow API not available.', 'abilityhub' ),
				'data'    => null,
			];
		}

		$result = abilityhub_register_workflow( $intent['workflow_id'], [
			'trigger'    => $intent['trigger'],
			'chain'      => $intent['chain'],
			'guardrails' => [ 'require_approval' => $intent['require_approval'] ],
		] );

		if ( is_wp_error( $result ) ) {
			return [ 'success' => false, 'message' => $result->get_error_message(), 'data' => null ];
		}

		// Persist so it survives page reload.
		abilityhub_save_workflow_to_db( $intent['workflow_id'], [
			'trigger'          => $intent['trigger'],
			'chain'            => $intent['chain'],
			'require_approval' => $intent['require_approval'],
		] );

		return [
			'success' => true,
			/* translators: %s: workflow ID */
			'message' => sprintf( __( 'Workflow "%s" created and activated.', 'abilityhub' ), $intent['workflow_id'] ),
			'data'    => [ 'workflow_id' => $intent['workflow_id'] ],
		];
	}

	/**
	 * @param string $workflow_id
	 * @param bool   $active
	 * @return array{success: bool, message: string, data: mixed}
	 */
	private function toggle_workflow( string $workflow_id, bool $active ): array {
		if ( $active ) {
			abilityhub_activate_workflow( $workflow_id );
			return [
				'success' => true,
				/* translators: %s: workflow ID */
				'message' => sprintf( __( 'Workflow "%s" activated.', 'abilityhub' ), $workflow_id ),
				'data'    => null,
			];
		}

		abilityhub_deactivate_workflow( $workflow_id );
		return [
			'success' => true,
			/* translators: %s: workflow ID */
			'message' => sprintf( __( 'Workflow "%s" deactivated.', 'abilityhub' ), $workflow_id ),
			'data'    => null,
		];
	}
}
