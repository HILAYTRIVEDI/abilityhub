<?php
/**
 * Ability: Moderate Comment
 *
 * @package AbilityHub
 */

defined( 'ABSPATH' ) || exit;

class AbilityHub_Ability_Moderate_Comment extends AbilityHub_Ability_Base {

	protected string $name        = 'abilityhub/moderate-comment';
	protected string $label       = 'Moderate comment';
	protected string $description = 'Analyses a comment and returns an approve, flag, or spam verdict with confidence score.';
	protected string $category    = 'moderation';

	protected array $input_schema = [
		'type'       => 'object',
		'required'   => [ 'comment_text' ],
		'properties' => [
			'comment_text' => [
				'type'        => 'string',
				'description' => 'The comment text to moderate.',
			],
			'post_context' => [
				'type'        => 'string',
				'description' => 'Optional: the post title or excerpt for context.',
			],
		],
	];

	protected array $output_schema = [
		'type'       => 'object',
		'required'   => [ 'verdict', 'confidence', 'reason' ],
		'properties' => [
			'verdict' => [
				'type' => 'string',
				'enum' => [ 'approve', 'flag', 'spam' ],
			],
			'confidence' => [
				'type'    => 'number',
				'minimum' => 0,
				'maximum' => 1,
			],
			'reason' => [
				'type'        => 'string',
				'description' => 'Brief explanation of the verdict.',
			],
		],
	];

	public function check_permission(): bool {
		return current_user_can( 'moderate_comments' );
	}

	public function execute( array $input ): array|WP_Error {
		$start        = $this->start_timer();
		$comment_text = sanitize_text_field( $input['comment_text'] ?? '' );
		$post_context = sanitize_text_field( $input['post_context'] ?? '' );

		if ( empty( $comment_text ) ) {
			return new WP_Error( 'missing_comment', __( 'Comment text is required.', 'abilityhub' ) );
		}

		$context_line = $post_context ? "\nPost context: \"{$post_context}\"" : '';

		$prompt = sprintf(
			"Classify this comment as approve, flag, or spam with a confidence score (0.0–1.0) and brief reason.%s\n\nComment: \"%s\"\n\nVerdicts:\n- approve: Genuine, on-topic comment that adds value\n- flag: Borderline — offensive language, off-topic, suspicious links, or potential misinformation\n- spam: Clear spam, excessive links, gibberish, purely promotional, or bot-generated",
			$context_line,
			$comment_text
		);

		$builder = $this->ai_client( $prompt );

		if ( is_wp_error( $builder ) ) {
			$this->log( 'error', $this->elapsed_ms( $start ) );
			return $builder;
		}

		$json = $builder
			->using_system_instruction( __( 'You are a content moderation expert. You make accurate, consistent moderation decisions based on content quality and community standards. When in doubt, flag rather than approve or reject.', 'abilityhub' ) )
			->using_temperature( 0.2 )
			->as_json_response( $this->output_schema )
			->generate_text();

		if ( is_wp_error( $json ) ) {
			$this->log( 'error', $this->elapsed_ms( $start ) );
			return $json;
		}

		$data = json_decode( $json, true );

		if ( json_last_error() !== JSON_ERROR_NONE || ! isset( $data['verdict'], $data['confidence'], $data['reason'] ) ) {
			$this->log( 'error', $this->elapsed_ms( $start ) );
			return new WP_Error( 'parse_error', __( 'AI returned an unexpected format. Please try again.', 'abilityhub' ) );
		}

		$allowed_verdicts = [ 'approve', 'flag', 'spam' ];
		$verdict          = sanitize_text_field( $data['verdict'] );

		if ( ! in_array( $verdict, $allowed_verdicts, true ) ) {
			$verdict = 'flag'; // Safe default.
		}

		$this->log( 'success', $this->elapsed_ms( $start ) );

		return [
			'verdict'    => $verdict,
			'confidence' => min( 1.0, max( 0.0, (float) $data['confidence'] ) ),
			'reason'     => sanitize_text_field( $data['reason'] ),
		];
	}
}
