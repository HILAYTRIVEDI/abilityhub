<?php
/**
 * Ability: Explain PHP Error
 *
 * @package AbilityHub
 */

defined( 'ABSPATH' ) || exit;

class AbilityHub_Ability_Explain_Php_Error extends AbilityHub_Ability_Base {

	protected string $name        = 'abilityhub/explain-php-error';
	protected string $label       = 'Explain PHP error';
	protected string $description = 'Explains a PHP or WordPress error in plain language and suggests a fix with a code example.';
	protected string $category    = 'developer';
	protected bool   $cacheable   = true;

	protected array $input_schema = [
		'type'       => 'object',
		'required'   => [ 'error_message' ],
		'properties' => [
			'error_message' => [
				'type'        => 'string',
				'description' => 'The PHP or WordPress error message.',
			],
			'file_context' => [
				'type'        => 'string',
				'description' => 'Optional: the code snippet surrounding the error.',
			],
		],
	];

	protected array $output_schema = [
		'type'       => 'object',
		'required'   => [ 'plain_explanation', 'likely_cause', 'fix_suggestion', 'code_example' ],
		'properties' => [
			'plain_explanation' => [
				'type'        => 'string',
				'description' => 'Plain English explanation of what the error means.',
			],
			'likely_cause' => [
				'type'        => 'string',
				'description' => 'The most likely root cause.',
			],
			'fix_suggestion' => [
				'type'        => 'string',
				'description' => 'Step-by-step guidance to resolve the error.',
			],
			'code_example' => [
				'type'        => 'string',
				'description' => 'A corrected code snippet demonstrating the fix.',
			],
		],
	];

	public function check_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	public function execute( array $input ): array|WP_Error {
		$start         = $this->start_timer();
		$error_message = sanitize_text_field( $input['error_message'] ?? '' );
		$file_context  = sanitize_textarea_field( $input['file_context'] ?? '' );

		if ( empty( $error_message ) ) {
			return new WP_Error( 'missing_error', __( 'Error message is required.', 'abilityhub' ) );
		}

		$context_section = $file_context
			? "\n\nCode context:\n```php\n{$file_context}\n```"
			: '';

		$prompt = sprintf(
			"Analyse this PHP/WordPress error and explain it clearly.\n\nError message:\n%s%s\n\nProvide:\n- plain_explanation: What this error means in plain English\n- likely_cause: The most probable root cause in a WordPress context\n- fix_suggestion: Clear, actionable steps to resolve it\n- code_example: A PHP snippet showing the corrected approach",
			$error_message,
			$context_section
		);

		$builder = $this->ai_client( $prompt );

		if ( is_wp_error( $builder ) ) {
			$this->log( 'error', $this->elapsed_ms( $start ) );
			return $builder;
		}

		$json = $builder
			->using_system_instruction( __( 'You are a senior WordPress/PHP developer and excellent teacher. You explain errors in plain language accessible to developers of all levels, and always provide practical, working code examples.', 'abilityhub' ) )
			->using_temperature( 0.3 )
			->as_json_response( $this->output_schema )
			->generate_text();

		if ( is_wp_error( $json ) ) {
			$this->log( 'error', $this->elapsed_ms( $start ) );
			return $json;
		}

		$data = json_decode( $json, true );

		if ( json_last_error() !== JSON_ERROR_NONE || ! isset( $data['plain_explanation'], $data['likely_cause'], $data['fix_suggestion'], $data['code_example'] ) ) {
			$this->log( 'error', $this->elapsed_ms( $start ) );
			return new WP_Error( 'parse_error', __( 'AI returned an unexpected format. Please try again.', 'abilityhub' ) );
		}

		$this->log( 'success', $this->elapsed_ms( $start ) );

		return [
			'plain_explanation' => sanitize_textarea_field( $data['plain_explanation'] ),
			'likely_cause'      => sanitize_textarea_field( $data['likely_cause'] ),
			'fix_suggestion'    => sanitize_textarea_field( $data['fix_suggestion'] ),
			'code_example'      => sanitize_textarea_field( $data['code_example'] ),
		];
	}
}
