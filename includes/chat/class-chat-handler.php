<?php
/**
 * Main orchestrator for the AI Site Operator chat.
 *
 * Wires together the prompt builder, AI client, intent parser,
 * intent executor, and conversation store into a single handle() call.
 *
 * @package AbilityHub
 */

defined( 'ABSPATH' ) || exit;

class AbilityHub_Chat_Handler {

	/** @var AbilityHub_Prompt_Builder */
	private AbilityHub_Prompt_Builder $prompt_builder;

	/** @var AbilityHub_Intent_Parser */
	private AbilityHub_Intent_Parser $intent_parser;

	/** @var AbilityHub_Intent_Executor */
	private AbilityHub_Intent_Executor $intent_executor;

	/** @var AbilityHub_Conversation_Store */
	private AbilityHub_Conversation_Store $conversation_store;

	public function __construct(
		AbilityHub_Prompt_Builder $prompt_builder,
		AbilityHub_Intent_Parser $intent_parser,
		AbilityHub_Intent_Executor $intent_executor,
		AbilityHub_Conversation_Store $conversation_store
	) {
		$this->prompt_builder     = $prompt_builder;
		$this->intent_parser      = $intent_parser;
		$this->intent_executor    = $intent_executor;
		$this->conversation_store = $conversation_store;
	}

	/**
	 * Process a user message and return the AI reply plus any intent result.
	 *
	 * @param string $user_message The raw user message.
	 * @param int    $user_id      WordPress user ID of the sender.
	 * @return array{reply: string, intent_result: array|null}|WP_Error
	 */
	public function handle( string $user_message, int $user_id ): array|WP_Error {
		if ( ! AbilityHub_AI_Client::is_available() ) {
			return new WP_Error(
				'no_ai_client',
				__( 'WordPress AI Client is not available. Requires WordPress 7.0+.', 'abilityhub' )
			);
		}

		$user_message = sanitize_textarea_field( wp_unslash( $user_message ) );

		if ( '' === $user_message ) {
			return new WP_Error( 'empty_message', __( 'Message cannot be empty.', 'abilityhub' ) );
		}

		$system_instruction = $this->prompt_builder->build_system_instruction();
		$history            = $this->conversation_store->get_history_for_ai( $user_id, 6 );

		$builder = AbilityHub_AI_Client::get_builder( $user_message );
		if ( is_wp_error( $builder ) ) {
			return $builder;
		}

		$builder = $builder->using_system_instruction( $system_instruction );

		if ( ! empty( $history ) && method_exists( $builder, 'with_history' ) ) {
			$builder = $builder->with_history( $history );
		}

		$ai_response = $builder->generate_text();

		if ( is_wp_error( $ai_response ) ) {
			return $ai_response;
		}

		// Persist the exchange before executing any intent.
		$this->conversation_store->append( $user_id, 'user',      $user_message );
		$this->conversation_store->append( $user_id, 'assistant', $ai_response  );

		// Parse and execute any intent embedded in the response.
		$intent        = $this->intent_parser->parse( $ai_response );
		$intent_result = null;

		if ( null !== $intent ) {
			$intent_result = $this->intent_executor->execute( $intent, $user_id );

			// Inject the ability result back into conversation history so the AI can
			// use output data (e.g. post IDs from get-posts) in follow-up turns.
			if ( ! empty( $intent_result['data'] ) ) {
				$ability_name = $intent['ability'] ?? $intent['intent'] ?? 'unknown';
				$result_json  = wp_json_encode( $intent_result['data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
				$feedback     = sprintf(
					'[AbilityHub] Ability "%s" result: %s',
					$ability_name,
					$result_json
				);
				$this->conversation_store->append( $user_id, 'user', $feedback );

				// Call the AI again so it can present the results to the user
				// rather than waiting for the next user message.
				$follow_up_history = $this->conversation_store->get_history_for_ai( $user_id, 8 );

				$follow_up_builder = AbilityHub_AI_Client::get_builder( $feedback );
				if ( ! is_wp_error( $follow_up_builder ) ) {
					$follow_up_builder = $follow_up_builder->using_system_instruction( $system_instruction );

					if ( ! empty( $follow_up_history ) && method_exists( $follow_up_builder, 'with_history' ) ) {
						$follow_up_builder = $follow_up_builder->with_history( $follow_up_history );
					}

					$follow_up_response = $follow_up_builder->generate_text();

					if ( ! is_wp_error( $follow_up_response ) ) {
						$this->conversation_store->append( $user_id, 'assistant', $follow_up_response );

						return [
							'reply'         => $this->intent_parser->strip_intent( $follow_up_response ),
							'intent_result' => $intent_result,
						];
					}
				}
			}
		}

		return [
			'reply'         => $this->intent_parser->strip_intent( $ai_response ),
			'intent_result' => $intent_result,
		];
	}
}
