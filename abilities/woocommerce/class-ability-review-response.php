<?php
/**
 * Ability: Write Review Response
 *
 * @package AbilityHub
 */

defined( 'ABSPATH' ) || exit;

class AbilityHub_Ability_Review_Response extends AbilityHub_Ability_Base {

	protected string $name        = 'abilityhub/write-review-response';
	protected string $label       = 'Write review response';
	protected string $description = 'Generates a professional, brand-appropriate response to a customer product review.';
	protected string $category    = 'ecommerce';

	protected array $input_schema = [
		'type'       => 'object',
		'required'   => [ 'review_text', 'rating', 'product_name' ],
		'properties' => [
			'review_text' => [
				'type'        => 'string',
				'description' => 'The customer review text.',
			],
			'rating' => [
				'type'        => 'integer',
				'minimum'     => 1,
				'maximum'     => 5,
				'description' => 'Star rating from 1 (lowest) to 5 (highest).',
			],
			'product_name' => [
				'type'        => 'string',
				'description' => 'The product being reviewed.',
			],
		],
	];

	protected array $output_schema = [
		'type'       => 'object',
		'required'   => [ 'response' ],
		'properties' => [
			'response' => [
				'type'        => 'string',
				'description' => '50-80 word professional, warm response to the review.',
			],
		],
	];

	public function execute( array $input ): array|WP_Error {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return new WP_Error(
				'woocommerce_inactive',
				__( 'WooCommerce must be active to use this ability.', 'abilityhub' )
			);
		}

		$start        = $this->start_timer();
		$review_text  = sanitize_text_field( $input['review_text'] ?? '' );
		$rating       = absint( $input['rating'] ?? 0 );
		$product_name = sanitize_text_field( $input['product_name'] ?? '' );

		if ( empty( $review_text ) ) {
			return new WP_Error( 'missing_review', __( 'Review text is required.', 'abilityhub' ) );
		}

		if ( $rating < 1 || $rating > 5 ) {
			return new WP_Error( 'invalid_rating', __( 'Rating must be between 1 and 5.', 'abilityhub' ) );
		}

		$tone_instruction = $rating <= 2
			? __( 'The customer is dissatisfied. Be empathetic and understanding. Acknowledge their disappointment sincerely, apologise for their experience, and offer a path to resolution.', 'abilityhub' )
			: __( 'The customer is satisfied. Be genuinely grateful and warm. Thank them sincerely and invite them back.', 'abilityhub' );

		$prompt = sprintf(
			"Write a 50-80 word response to this product review.\n\nProduct: %s\nStar rating: %d/5\nReview: \"%s\"\n\nTone guidance: %s",
			$product_name,
			$rating,
			$review_text,
			$tone_instruction
		);

		$builder = $this->ai_client( $prompt );

		if ( is_wp_error( $builder ) ) {
			$this->log( 'error', $this->elapsed_ms( $start ) );
			return $builder;
		}

		$json = $builder
			->using_system_instruction( __( 'You are a customer service expert writing responses to product reviews. You write professional, warm, and personal responses — never generic templates. Keep responses between 50-80 words.', 'abilityhub' ) )
			->using_temperature( 0.6 )
			->as_json_response( $this->output_schema )
			->generate_text();

		if ( is_wp_error( $json ) ) {
			$this->log( 'error', $this->elapsed_ms( $start ) );
			return $json;
		}

		$data = json_decode( $json, true );

		if ( json_last_error() !== JSON_ERROR_NONE || ! isset( $data['response'] ) ) {
			$this->log( 'error', $this->elapsed_ms( $start ) );
			return new WP_Error( 'parse_error', __( 'AI returned an unexpected format. Please try again.', 'abilityhub' ) );
		}

		$this->log( 'success', $this->elapsed_ms( $start ) );

		return [
			'response' => sanitize_text_field( trim( $data['response'] ) ),
		];
	}
}
