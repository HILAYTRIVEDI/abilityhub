<?php
/**
 * Ability: Generate Block Pattern
 *
 * @package AbilityHub
 */

defined( 'ABSPATH' ) || exit;

class AbilityHub_Ability_Generate_Block_Pattern extends AbilityHub_Ability_Base {

	protected string $name        = 'abilityhub/generate-block-pattern';
	protected string $label       = 'Generate block pattern';
	protected string $description = 'Generates a complete WordPress block pattern with PHP registration code and block markup.';
	protected string $category    = 'developer';
	protected bool   $cacheable   = true;

	protected array $input_schema = [
		'type'       => 'object',
		'required'   => [ 'description' ],
		'properties' => [
			'description' => [
				'type'        => 'string',
				'description' => 'What the block pattern should look like and do.',
			],
			'block_types' => [
				'type'        => 'array',
				'items'       => [ 'type' => 'string' ],
				'description' => 'Optional: specific block types to include (e.g. ["core/heading", "core/paragraph"]).',
			],
		],
	];

	protected array $output_schema = [
		'type'       => 'object',
		'required'   => [ 'pattern_php', 'pattern_html' ],
		'properties' => [
			'pattern_php' => [
				'type'        => 'string',
				'description' => 'PHP code to register the block pattern using register_block_pattern().',
			],
			'pattern_html' => [
				'type'        => 'string',
				'description' => 'The block markup (HTML with block comments).',
			],
		],
	];

	public function check_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	public function execute( array $input ): array|WP_Error {
		$start       = $this->start_timer();
		$description = sanitize_text_field( $input['description'] ?? '' );
		$block_types = array_map( 'sanitize_text_field', (array) ( $input['block_types'] ?? [] ) );

		if ( empty( $description ) ) {
			return new WP_Error( 'missing_description', __( 'Pattern description is required.', 'abilityhub' ) );
		}

		$blocks_hint = ! empty( $block_types )
			? "\nPreferred block types: " . implode( ', ', $block_types )
			: '';

		$prompt = sprintf(
			"Generate a complete, valid WordPress block pattern.\n\nPattern description: %s%s\n\nProduce:\n- pattern_php: PHP code calling register_block_pattern() with title, description, categories, and content. Use a realistic slug like 'mytheme/descriptive-name'.\n- pattern_html: Valid WordPress block markup using block comment syntax (<!-- wp:block-name --> ... <!-- /wp:block-name -->).",
			$description,
			$blocks_hint
		);

		$builder = $this->ai_client( $prompt );

		if ( is_wp_error( $builder ) ) {
			$this->log( 'error', $this->elapsed_ms( $start ) );
			return $builder;
		}

		$json = $builder
			->using_system_instruction( __( 'You are a WordPress block editor expert. You write valid, production-ready block patterns that follow WordPress coding standards. All PHP is properly escaped and all block markup uses correct block comment syntax.', 'abilityhub' ) )
			->using_temperature( 0.5 )
			->as_json_response( $this->output_schema )
			->generate_text();

		if ( is_wp_error( $json ) ) {
			$this->log( 'error', $this->elapsed_ms( $start ) );
			return $json;
		}

		$data = json_decode( $json, true );

		if ( json_last_error() !== JSON_ERROR_NONE || ! isset( $data['pattern_php'], $data['pattern_html'] ) ) {
			$this->log( 'error', $this->elapsed_ms( $start ) );
			return new WP_Error( 'parse_error', __( 'AI returned an unexpected format. Please try again.', 'abilityhub' ) );
		}

		$this->log( 'success', $this->elapsed_ms( $start ) );

		return [
			'pattern_php'  => wp_kses_post( $data['pattern_php'] ),
			'pattern_html' => wp_kses_post( $data['pattern_html'] ),
		];
	}
}
