<?php
/**
 * Ability: Suggest Image Filename
 *
 * @package AbilityHub
 */

defined( 'ABSPATH' ) || exit;

class AbilityHub_Ability_Suggest_Filename extends AbilityHub_Ability_Base {

	protected string $name        = 'abilityhub/suggest-image-filename';
	protected string $label       = 'Suggest image filename';
	protected string $description = 'Suggests an SEO-friendly, descriptive filename for an image based on context.';
	protected string $category    = 'media';

	protected array $input_schema = [
		'type'       => 'object',
		'required'   => [ 'current_filename', 'image_context' ],
		'properties' => [
			'current_filename' => [
				'type'        => 'string',
				'description' => 'The current filename (e.g. "IMG_20240315_142301.jpg").',
			],
			'image_context' => [
				'type'        => 'string',
				'description' => 'Description of the image or surrounding post content.',
			],
		],
	];

	protected array $output_schema = [
		'type'       => 'object',
		'required'   => [ 'seo_filename', 'reason' ],
		'properties' => [
			'seo_filename' => [
				'type'        => 'string',
				'description' => 'SEO-friendly filename: lowercase, hyphenated, no extension.',
			],
			'reason' => [
				'type'        => 'string',
				'description' => 'Brief explanation of why this filename was chosen.',
			],
		],
	];

	public function execute( array $input ): array|WP_Error {
		$start            = $this->start_timer();
		$current_filename = sanitize_file_name( $input['current_filename'] ?? '' );
		$image_context    = sanitize_text_field( $input['image_context'] ?? '' );

		if ( empty( $current_filename ) ) {
			return new WP_Error( 'missing_filename', __( 'Current filename is required.', 'abilityhub' ) );
		}

		if ( empty( $image_context ) ) {
			return new WP_Error( 'missing_context', __( 'Image context is required.', 'abilityhub' ) );
		}

		$prompt = sprintf(
			"Suggest an SEO-friendly filename for this image.\n\nCurrent filename: %s\nImage context: %s\n\nFilename rules:\n- Lowercase only, words separated by hyphens\n- No file extension\n- Descriptive and keyword-rich (3-6 words)\n- No special characters except hyphens\n- Reflects the actual image content\n\nExample good filenames: \"red-leather-office-chair\", \"wordpress-dashboard-screenshot-2024\"",
			$current_filename,
			$image_context
		);

		$builder = $this->ai_client( $prompt );

		if ( is_wp_error( $builder ) ) {
			$this->log( 'error', $this->elapsed_ms( $start ) );
			return $builder;
		}

		$json = $builder
			->using_system_instruction( __( 'You are an SEO specialist with expertise in image optimisation. You suggest descriptive, keyword-rich filenames that accurately reflect image content and improve search visibility.', 'abilityhub' ) )
			->using_temperature( 0.3 )
			->as_json_response( $this->output_schema )
			->generate_text();

		if ( is_wp_error( $json ) ) {
			$this->log( 'error', $this->elapsed_ms( $start ) );
			return $json;
		}

		$data = json_decode( $json, true );

		if ( json_last_error() !== JSON_ERROR_NONE || ! isset( $data['seo_filename'], $data['reason'] ) ) {
			$this->log( 'error', $this->elapsed_ms( $start ) );
			return new WP_Error( 'parse_error', __( 'AI returned an unexpected format. Please try again.', 'abilityhub' ) );
		}

		// Sanitize filename: only lowercase alphanumeric and hyphens.
		$clean_filename = preg_replace( '/[^a-z0-9\-]/', '', strtolower( $data['seo_filename'] ?? '' ) );
		$clean_filename = trim( $clean_filename, '-' );

		if ( empty( $clean_filename ) ) {
			$this->log( 'error', $this->elapsed_ms( $start ) );
			return new WP_Error( 'invalid_filename', __( 'AI returned an invalid filename.', 'abilityhub' ) );
		}

		$this->log( 'success', $this->elapsed_ms( $start ) );

		return [
			'seo_filename' => $clean_filename,
			'reason'       => sanitize_text_field( $data['reason'] ),
		];
	}
}
