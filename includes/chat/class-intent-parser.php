<?php
/**
 * Parses AI responses to extract structured intent objects.
 *
 * @package AbilityHub
 */

defined( 'ABSPATH' ) || exit;

class AbilityHub_Intent_Parser {

	const VALID_INTENTS = [
		'run_ability',
		'batch_process',
		'create_workflow',
		'deactivate_workflow',
		'activate_workflow',
	];

	/**
	 * Extract an intent array from an AI response string.
	 *
	 * Looks for the last ```json { ... } ``` fence in the message.
	 *
	 * @param string $ai_response Full AI response text.
	 * @return array<string, mixed>|null Validated intent or null when none found.
	 */
	public function parse( string $ai_response ): ?array {
		if ( ! preg_match( '/```json\s*(\{[^`]+\})\s*```/s', $ai_response, $matches ) ) {
			return null;
		}

		$decoded = json_decode( trim( $matches[1] ), true );

		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $decoded ) ) {
			return null;
		}

		if ( empty( $decoded['intent'] ) || ! in_array( $decoded['intent'], self::VALID_INTENTS, true ) ) {
			return null;
		}

		return $this->validate( $decoded );
	}

	/**
	 * Strip the intent JSON fence from the AI message, leaving only the prose.
	 *
	 * @param string $ai_response Full AI response text.
	 * @return string Human-readable portion only.
	 */
	public function strip_intent( string $ai_response ): string {
		return trim( preg_replace( '/```json\s*\{[^`]+\}\s*```/s', '', $ai_response ) );
	}

	/**
	 * Validate and sanitise required fields per intent type.
	 *
	 * @param array<string, mixed> $intent Raw decoded intent.
	 * @return array<string, mixed>|null Sanitised intent or null on validation failure.
	 */
	private function validate( array $intent ): ?array {
		switch ( $intent['intent'] ) {

			case 'run_ability':
				if ( empty( $intent['ability'] ) ) {
					return null;
				}
				$intent['ability'] = sanitize_text_field( $intent['ability'] );
				$intent['input']   = is_array( $intent['input'] ?? null ) ? $intent['input'] : [];
				return $intent;

			case 'batch_process':
				if ( empty( $intent['ability'] ) ) {
					return null;
				}
				$intent['ability']     = sanitize_text_field( $intent['ability'] );
				$intent['post_type']   = sanitize_key( $intent['post_type']   ?? 'post' );
				$intent['post_status'] = sanitize_key( $intent['post_status'] ?? 'publish' );
				$intent['limit']       = min( absint( $intent['limit'] ?? 10 ), 100 );
				$intent['input_map']   = is_array( $intent['input_map'] ?? null ) ? $intent['input_map'] : [];
				return $intent;

			case 'create_workflow':
				if ( empty( $intent['workflow_id'] ) || empty( $intent['trigger'] ) || empty( $intent['chain'] ) ) {
					return null;
				}
				if ( ! is_array( $intent['chain'] ) || empty( $intent['chain'] ) ) {
					return null;
				}
				$allowed_triggers = [ 'post_published', 'image_uploaded', 'comment_submitted' ];
				if ( ! in_array( $intent['trigger'], $allowed_triggers, true ) ) {
					return null;
				}
				$intent['workflow_id']      = sanitize_text_field( $intent['workflow_id'] );
				$intent['chain']            = array_map( 'sanitize_text_field', $intent['chain'] );
				$intent['require_approval'] = (bool) ( $intent['require_approval'] ?? true );
				return $intent;

			case 'deactivate_workflow':
			case 'activate_workflow':
				if ( empty( $intent['workflow_id'] ) ) {
					return null;
				}
				$intent['workflow_id'] = sanitize_text_field( $intent['workflow_id'] );
				return $intent;
		}

		return null;
	}
}
