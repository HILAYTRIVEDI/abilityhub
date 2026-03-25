<?php
/**
 * Ability: Generate Alt Text
 *
 * @package AbilityHub
 */

defined( 'ABSPATH' ) || exit;

class AbilityHub_Ability_Generate_Alt_Text extends AbilityHub_Ability_Base {

	protected string $name        = 'abilityhub/generate-alt-text';
	protected string $label       = 'Generate alt text';
	protected string $description = 'Generates accessible, descriptive alt text and optional caption for an image using AI vision.';
	protected string $category    = 'accessibility';

	protected array $input_schema = [
		'type'       => 'object',
		'required'   => [ 'image_url' ],
		'properties' => [
			'image_url' => [
				'type'        => 'string',
				'description' => 'URL of the image to describe.',
			],
			'image_context' => [
				'type'        => 'string',
				'description' => 'Optional: surrounding post content to give context for the image.',
			],
		],
	];

	protected array $output_schema = [
		'type'       => 'object',
		'required'   => [ 'alt_text' ],
		'properties' => [
			'alt_text' => [
				'type'        => 'string',
				'description' => 'Descriptive alt text, max 125 characters.',
			],
			'caption' => [
				'type'        => 'string',
				'description' => 'Optional one-sentence caption for the image.',
			],
		],
	];

	public function execute( array $input ): array|WP_Error {
		$start         = $this->start_timer();
		$image_url     = esc_url_raw( $input['image_url'] ?? '' );
		$image_context = sanitize_text_field( $input['image_context'] ?? '' );

		if ( empty( $image_url ) ) {
			return new WP_Error( 'missing_image_url', __( 'Image URL is required.', 'abilityhub' ) );
		}

		if ( ! filter_var( $image_url, FILTER_VALIDATE_URL ) ) {
			return new WP_Error( 'invalid_url', __( 'A valid image URL is required.', 'abilityhub' ) );
		}

		$context_line = $image_context ? "\nSurrounding content context: \"{$image_context}\"" : '';

		$prompt = sprintf(
			"Describe the image at this URL for use as HTML alt text (max 125 characters). Also write a short one-sentence caption.\n\nImage URL: %s%s\n\nAlt text rules:\n- Maximum 125 characters\n- Be specific and descriptive\n- Do NOT start with \"Image of\", \"Photo of\", or \"Picture of\"\n- Use empty string if purely decorative",
			$image_url,
			$context_line
		);

		$builder = $this->ai_client( $prompt );

		if ( is_wp_error( $builder ) ) {
			$this->log( 'error', $this->elapsed_ms( $start ) );
			return $builder;
		}

		$json = $builder
			->using_system_instruction( __( 'You are an accessibility expert specialising in image descriptions for the web. You write concise, meaningful alt text that accurately describes images for screen reader users and meets WCAG 2.1 guidelines.', 'abilityhub' ) )
			->using_temperature( 0.3 )
			->as_json_response( $this->output_schema )
			->generate_text();

		if ( is_wp_error( $json ) ) {
			$this->log( 'error', $this->elapsed_ms( $start ) );
			return $json;
		}

		$data = json_decode( $json, true );

		if ( json_last_error() !== JSON_ERROR_NONE || ! isset( $data['alt_text'] ) ) {
			$this->log( 'error', $this->elapsed_ms( $start ) );
			return new WP_Error( 'parse_error', __( 'AI returned an unexpected format. Please try again.', 'abilityhub' ) );
		}

		$this->log( 'success', $this->elapsed_ms( $start ) );

		return [
			'alt_text' => sanitize_text_field( $data['alt_text'] ?? '' ),
			'caption'  => sanitize_text_field( $data['caption'] ?? '' ),
		];
	}
}
