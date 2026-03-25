<?php
/**
 * Ability: Generate Meta Description
 *
 * @package AbilityHub
 */

defined( 'ABSPATH' ) || exit;

class AbilityHub_Ability_Generate_Meta extends AbilityHub_Ability_Base {

	protected string $name        = 'abilityhub/generate-meta-description';
	protected string $label       = 'Generate meta description';
	protected string $description = 'Generates an SEO title and 155-character meta description from post content.';
	protected string $category    = 'seo';

	protected array $input_schema = [
		'type'       => 'object',
		'required'   => [ 'content' ],
		'properties' => [
			'content' => [
				'type'        => 'string',
				'description' => 'The post content to generate meta for.',
			],
			'keyword' => [
				'type'        => 'string',
				'description' => 'Optional target SEO keyword.',
			],
		],
	];

	protected array $output_schema = [
		'type'       => 'object',
		'required'   => [ 'meta_title', 'meta_description' ],
		'properties' => [
			'meta_title' => [
				'type'        => 'string',
				'description' => 'SEO-optimised title, max 60 characters.',
			],
			'meta_description' => [
				'type'        => 'string',
				'description' => 'Meta description, max 155 characters.',
			],
		],
	];

	public function execute( array $input ): array|WP_Error {
		$start   = $this->start_timer();
		$content = wp_kses_post( $input['content'] ?? '' );
		$keyword = sanitize_text_field( $input['keyword'] ?? '' );

		if ( empty( $content ) ) {
			return new WP_Error( 'missing_content', __( 'Content is required.', 'abilityhub' ) );
		}

		$keyword_line = $keyword ? "\nTarget keyword: {$keyword}" : '';

		$prompt = sprintf(
			"Generate an SEO title (max 60 characters) and meta description (max 155 characters) for the following content.%s\n\nContent:\n%s",
			$keyword_line,
			wp_trim_words( $content, 500 )
		);

		$builder = $this->ai_client( $prompt );

		if ( is_wp_error( $builder ) ) {
			$this->log( 'error', $this->elapsed_ms( $start ) );
			return $builder;
		}

		$json = $builder
			->using_system_instruction( __( 'You are an SEO expert. Write concise, compelling copy that maximises click-through rate. Always respect character limits exactly.', 'abilityhub' ) )
			->using_temperature( 0.3 )
			->as_json_response( $this->output_schema )
			->generate_text();

		if ( is_wp_error( $json ) ) {
			$this->log( 'error', $this->elapsed_ms( $start ) );
			return $json;
		}

		$data = json_decode( $json, true );

		if ( json_last_error() !== JSON_ERROR_NONE || ! isset( $data['meta_title'], $data['meta_description'] ) ) {
			$this->log( 'error', $this->elapsed_ms( $start ) );
			return new WP_Error( 'parse_error', __( 'AI returned an unexpected format. Please try again.', 'abilityhub' ) );
		}

		$this->log( 'success', $this->elapsed_ms( $start ) );

		return [
			'meta_title'       => sanitize_text_field( $data['meta_title'] ),
			'meta_description' => sanitize_text_field( $data['meta_description'] ),
		];
	}
}
