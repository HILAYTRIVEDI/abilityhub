<?php
/**
 * Ability: Write WP Hook Docs
 *
 * @package AbilityHub
 */

defined( 'ABSPATH' ) || exit;

class AbilityHub_Ability_Write_Hook_Docs extends AbilityHub_Ability_Base {

	protected string $name        = 'abilityhub/write-wp-hook-docs';
	protected string $label       = 'Write WP hook docs';
	protected string $description = 'Generates a PHPDoc docblock and usage example for a WordPress action or filter hook.';
	protected string $category    = 'developer';
	protected bool   $cacheable   = true;

	protected array $input_schema = [
		'type'       => 'object',
		'required'   => [ 'hook_name', 'hook_type' ],
		'properties' => [
			'hook_name' => [
				'type'        => 'string',
				'description' => 'The hook name, e.g. "save_post" or "the_content".',
			],
			'hook_type' => [
				'type'        => 'string',
				'enum'        => [ 'action', 'filter' ],
				'description' => 'Whether this is an action or filter hook.',
			],
			'parameters' => [
				'type'        => 'string',
				'description' => 'Optional: comma-separated list of parameters and their types (e.g. "int $post_id, WP_Post $post").',
			],
		],
	];

	protected array $output_schema = [
		'type'       => 'object',
		'required'   => [ 'docblock', 'usage_example' ],
		'properties' => [
			'docblock' => [
				'type'        => 'string',
				'description' => 'Full PHPDoc-format docblock for the hook.',
			],
			'usage_example' => [
				'type'        => 'string',
				'description' => 'PHP code showing how to use the hook with add_action/add_filter.',
			],
		],
	];

	public function execute( array $input ): array|WP_Error {
		$start      = $this->start_timer();
		$hook_name  = sanitize_text_field( $input['hook_name'] ?? '' );
		$hook_type  = sanitize_text_field( $input['hook_type'] ?? '' );
		$parameters = sanitize_text_field( $input['parameters'] ?? '' );

		if ( empty( $hook_name ) ) {
			return new WP_Error( 'missing_hook_name', __( 'Hook name is required.', 'abilityhub' ) );
		}

		if ( ! in_array( $hook_type, [ 'action', 'filter' ], true ) ) {
			return new WP_Error( 'invalid_hook_type', __( 'Hook type must be "action" or "filter".', 'abilityhub' ) );
		}

		$wp_function = 'action' === $hook_type ? 'do_action' : 'apply_filters';
		$params_line = $parameters ? "\nParameters: {$parameters}" : '';

		$prompt = sprintf(
			"Generate complete documentation for this WordPress %s hook.\n\nHook name: %s\nHook type: %s (triggered with %s())%s\n\nGenerate:\n- docblock: A complete PHPDoc comment with @since 1.0.0, @param tags, description of when/why it fires%s\n- usage_example: A practical PHP code example using add_%s() with a realistic callback",
			$hook_type,
			$hook_name,
			$hook_type,
			$wp_function,
			$params_line,
			'filter' === $hook_type ? ', and @return tag for the filtered value' : '',
			$hook_type
		);

		$builder = $this->ai_client( $prompt );

		if ( is_wp_error( $builder ) ) {
			$this->log( 'error', $this->elapsed_ms( $start ) );
			return $builder;
		}

		$json = $builder
			->using_system_instruction( __( 'You are a WordPress documentation expert who follows WordPress inline documentation standards exactly. You write clear, accurate PHPDoc blocks and practical usage examples.', 'abilityhub' ) )
			->using_temperature( 0.4 )
			->as_json_response( $this->output_schema )
			->generate_text();

		if ( is_wp_error( $json ) ) {
			$this->log( 'error', $this->elapsed_ms( $start ) );
			return $json;
		}

		$data = json_decode( $json, true );

		if ( json_last_error() !== JSON_ERROR_NONE || ! isset( $data['docblock'], $data['usage_example'] ) ) {
			$this->log( 'error', $this->elapsed_ms( $start ) );
			return new WP_Error( 'parse_error', __( 'AI returned an unexpected format. Please try again.', 'abilityhub' ) );
		}

		$this->log( 'success', $this->elapsed_ms( $start ) );

		return [
			'docblock'      => sanitize_textarea_field( $data['docblock'] ),
			'usage_example' => sanitize_textarea_field( $data['usage_example'] ),
		];
	}
}
