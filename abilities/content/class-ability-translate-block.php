<?php
/**
 * Ability: Translate Block
 *
 * @package AbilityHub
 */

defined( 'ABSPATH' ) || exit;

class AbilityHub_Ability_Translate_Block extends AbilityHub_Ability_Base {

	protected string $name        = 'abilityhub/translate-block';
	protected string $label       = 'Translate block';
	protected string $description = 'Translates block content into a target language, preserving block markup.';
	protected string $category    = 'multilingual';

	protected array $input_schema = [
		'type'       => 'object',
		'required'   => [ 'content', 'target_language' ],
		'properties' => [
			'content' => [
				'type'        => 'string',
				'description' => 'The block content (HTML or plain text) to translate.',
			],
			'target_language' => [
				'type'        => 'string',
				'description' => 'BCP-47 language code, e.g. "hi", "ja", "es", "fr".',
			],
		],
	];

	protected array $output_schema = [
		'type'       => 'object',
		'required'   => [ 'translated_content', 'detected_language' ],
		'properties' => [
			'translated_content' => [
				'type'        => 'string',
				'description' => 'Content translated into the target language.',
			],
			'detected_language' => [
				'type'        => 'string',
				'description' => 'BCP-47 code of the detected source language.',
			],
		],
	];

	public function execute( array $input ): array|WP_Error {
		$start           = $this->start_timer();
		$content         = wp_kses_post( $input['content'] ?? '' );
		$target_language = sanitize_text_field( $input['target_language'] ?? '' );

		if ( empty( $content ) ) {
			return new WP_Error( 'missing_content', __( 'Content is required.', 'abilityhub' ) );
		}

		if ( empty( $target_language ) ) {
			return new WP_Error( 'missing_language', __( 'Target language is required.', 'abilityhub' ) );
		}

		$prompt = sprintf(
			"Translate the following content into the language with BCP-47 code \"%s\".\nPreserve all HTML tags and markup exactly — only translate the visible text.\nAlso detect and report the source language BCP-47 code.\n\nContent:\n%s",
			$target_language,
			$content
		);

		$builder = $this->ai_client( $prompt );

		if ( is_wp_error( $builder ) ) {
			$this->log( 'error', $this->elapsed_ms( $start ) );
			return $builder;
		}

		$json = $builder
			->using_system_instruction( __( 'You are a professional translator with expertise in web content. You preserve all HTML structure and only translate visible text. You are accurate and natural-sounding in all languages.', 'abilityhub' ) )
			->using_temperature( 0.2 )
			->as_json_response( $this->output_schema )
			->generate_text();

		if ( is_wp_error( $json ) ) {
			$this->log( 'error', $this->elapsed_ms( $start ) );
			return $json;
		}

		$data = json_decode( $json, true );

		if ( json_last_error() !== JSON_ERROR_NONE || ! isset( $data['translated_content'], $data['detected_language'] ) ) {
			$this->log( 'error', $this->elapsed_ms( $start ) );
			return new WP_Error( 'parse_error', __( 'AI returned an unexpected format. Please try again.', 'abilityhub' ) );
		}

		$this->log( 'success', $this->elapsed_ms( $start ) );

		return [
			'translated_content' => wp_kses_post( $data['translated_content'] ),
			'detected_language'  => sanitize_text_field( $data['detected_language'] ),
		];
	}
}
