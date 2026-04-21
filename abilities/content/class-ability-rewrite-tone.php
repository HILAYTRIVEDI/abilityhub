<?php
/**
 * Ability: Rewrite Tone
 *
 * @package AbilityHub
 */

defined( 'ABSPATH' ) || exit;

class AbilityHub_Ability_Rewrite_Tone extends AbilityHub_Ability_Base {

	protected string $name        = 'abilityhub/rewrite-tone';
	protected string $label       = 'Rewrite tone';
	protected string $description = 'Rewrites content in a specified tone while preserving all facts and meaning.';
	protected string $category    = 'editorial';
	protected bool   $cacheable   = true;
	protected int    $cache_ttl   = HOUR_IN_SECONDS;

	protected array $input_schema = [
		'type'       => 'object',
		'required'   => [ 'content', 'tone' ],
		'properties' => [
			'content' => [
				'type'        => 'string',
				'description' => 'The content to rewrite.',
			],
			'tone' => [
				'type'        => 'string',
				'enum'        => [ 'professional', 'casual', 'friendly', 'authoritative', 'humorous' ],
				'description' => 'The desired tone for the rewritten content.',
			],
		],
	];

	protected array $output_schema = [
		'type'       => 'object',
		'required'   => [ 'rewritten_content' ],
		'properties' => [
			'rewritten_content' => [
				'type'        => 'string',
				'description' => 'The content rewritten in the specified tone.',
			],
		],
	];

	public function execute( array $input ): array|WP_Error {
		$start   = $this->start_timer();
		$content = wp_kses_post( $input['content'] ?? '' );
		$tone    = sanitize_text_field( $input['tone'] ?? '' );

		if ( empty( $content ) ) {
			return new WP_Error( 'missing_content', __( 'Content is required.', 'abilityhub' ) );
		}

		$allowed_tones = [ 'professional', 'casual', 'friendly', 'authoritative', 'humorous' ];

		if ( ! in_array( $tone, $allowed_tones, true ) ) {
			return new WP_Error(
				'invalid_tone',
				/* translators: %s: allowed tone values */
				sprintf( __( 'Invalid tone. Allowed values: %s', 'abilityhub' ), implode( ', ', $allowed_tones ) )
			);
		}

		$prompt = sprintf(
			"Rewrite the following content in a %s tone.\nPreserve ALL facts, data, and meaning exactly — only change the style and voice.\n\nContent:\n%s",
			$tone,
			wp_trim_words( $content, 500 )
		);

		$builder = $this->ai_client( $prompt );

		if ( is_wp_error( $builder ) ) {
			$this->log( 'error', $this->elapsed_ms( $start ) );
			return $builder;
		}

		$json = $builder
			->using_system_instruction( __( 'You are a professional copywriter. Your task is to rewrite content in a specified tone while preserving every fact and piece of information exactly.', 'abilityhub' ) )
			->using_temperature( 0.7 )
			->as_json_response( $this->output_schema )
			->generate_text();

		if ( is_wp_error( $json ) ) {
			$this->log( 'error', $this->elapsed_ms( $start ) );
			return $json;
		}

		$data = json_decode( $json, true );

		if ( json_last_error() !== JSON_ERROR_NONE || ! isset( $data['rewritten_content'] ) ) {
			$this->log( 'error', $this->elapsed_ms( $start ) );
			return new WP_Error( 'parse_error', __( 'AI returned an unexpected format. Please try again.', 'abilityhub' ) );
		}

		$this->log( 'success', $this->elapsed_ms( $start ) );

		return [
			'rewritten_content' => wp_kses_post( $data['rewritten_content'] ),
		];
	}
}
