<?php
/**
 * Ability: Summarise Post
 *
 * @package AbilityHub
 */

defined( 'ABSPATH' ) || exit;

class AbilityHub_Ability_Summarise_Post extends AbilityHub_Ability_Base {

	protected string $name        = 'abilityhub/summarise-post';
	protected string $label       = 'Summarise post';
	protected string $description = 'Generates a concise summary and one-sentence TL;DR from post content.';
	protected string $category    = 'editorial';
	protected bool   $cacheable   = true;

	protected array $input_schema = [
		'type'       => 'object',
		'required'   => [ 'content' ],
		'properties' => [
			'content' => [
				'type'        => 'string',
				'description' => 'The post content to summarise.',
			],
			'max_words' => [
				'type'        => 'integer',
				'description' => 'Maximum number of words in the summary. Default 50.',
				'default'     => 50,
			],
		],
	];

	protected array $output_schema = [
		'type'       => 'object',
		'required'   => [ 'summary', 'tldr' ],
		'properties' => [
			'summary' => [
				'type'        => 'string',
				'description' => 'Concise summary of the post.',
			],
			'tldr' => [
				'type'        => 'string',
				'description' => 'One-sentence TL;DR.',
			],
		],
	];

	public function execute( array $input ): array|WP_Error {
		$start     = $this->start_timer();
		$content   = wp_kses_post( $input['content'] ?? '' );
		$max_words = absint( $input['max_words'] ?? 50 );

		if ( empty( $content ) ) {
			return new WP_Error( 'missing_content', __( 'Content is required.', 'abilityhub' ) );
		}

		if ( $max_words < 10 || $max_words > 500 ) {
			$max_words = 50;
		}

		$prompt = sprintf(
			"Summarise the following content in no more than %d words, and write a single-sentence TL;DR.\n\nContent:\n%s",
			$max_words,
			wp_trim_words( $content, 500 )
		);

		$builder = $this->ai_client( $prompt );

		if ( is_wp_error( $builder ) ) {
			$this->log( 'error', $this->elapsed_ms( $start ) );
			return $builder;
		}

		$json = $builder
			->using_system_instruction( __( 'You are an expert editor. Write clear, accurate summaries that capture the essential information without losing important nuance.', 'abilityhub' ) )
			->using_temperature( 0.3 )
			->as_json_response( $this->output_schema )
			->generate_text();

		if ( is_wp_error( $json ) ) {
			$this->log( 'error', $this->elapsed_ms( $start ) );
			return $json;
		}

		$data = json_decode( $json, true );

		if ( json_last_error() !== JSON_ERROR_NONE || ! isset( $data['summary'], $data['tldr'] ) ) {
			$this->log( 'error', $this->elapsed_ms( $start ) );
			return new WP_Error( 'parse_error', __( 'AI returned an unexpected format. Please try again.', 'abilityhub' ) );
		}

		$this->log( 'success', $this->elapsed_ms( $start ) );

		return [
			'summary' => sanitize_text_field( $data['summary'] ),
			'tldr'    => sanitize_text_field( $data['tldr'] ),
		];
	}
}
