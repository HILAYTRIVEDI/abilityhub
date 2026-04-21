<?php
/**
 * Ability: Generate Product Description
 *
 * @package AbilityHub
 */

defined( 'ABSPATH' ) || exit;

class AbilityHub_Ability_Product_Description extends AbilityHub_Ability_Base {

	protected string $name        = 'abilityhub/generate-product-description';
	protected string $label       = 'Generate product description';
	protected string $description = 'Generates WooCommerce short description, long description, and meta description for a product.';
	protected string $category    = 'ecommerce';
	protected bool   $cacheable   = true;
	protected int    $cache_ttl   = HOUR_IN_SECONDS;

	protected array $input_schema = [
		'type'       => 'object',
		'required'   => [ 'product_name' ],
		'properties' => [
			'product_name' => [
				'type'        => 'string',
				'description' => 'The product name.',
			],
			'attributes' => [
				'type'        => 'string',
				'description' => 'Optional product attributes, comma-separated (e.g. "color: red, size: XL, material: cotton").',
			],
			'keyword' => [
				'type'        => 'string',
				'description' => 'Optional target SEO keyword.',
			],
		],
	];

	protected array $output_schema = [
		'type'       => 'object',
		'required'   => [ 'short_description', 'long_description', 'meta_description' ],
		'properties' => [
			'short_description' => [
				'type'        => 'string',
				'description' => '1-2 sentence product summary.',
			],
			'long_description' => [
				'type'        => 'string',
				'description' => '150-200 word detailed product description.',
			],
			'meta_description' => [
				'type'        => 'string',
				'description' => 'SEO meta description, max 155 characters.',
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
		$product_name = sanitize_text_field( $input['product_name'] ?? '' );
		$attributes   = sanitize_text_field( $input['attributes'] ?? '' );
		$keyword      = sanitize_text_field( $input['keyword'] ?? '' );

		if ( empty( $product_name ) ) {
			return new WP_Error( 'missing_product_name', __( 'Product name is required.', 'abilityhub' ) );
		}

		$attrs_line   = $attributes ? "\nProduct attributes: {$attributes}" : '';
		$keyword_line = $keyword ? "\nTarget SEO keyword: {$keyword}" : '';

		$prompt = sprintf(
			"Write product copy for WooCommerce.\n\nProduct name: %s%s%s\n\nGenerate:\n- short_description: 1-2 engaging sentences that hook the buyer\n- long_description: 150-200 words highlighting features, benefits, and use cases\n- meta_description: SEO meta description, max 155 characters%s",
			$product_name,
			$attrs_line,
			$keyword_line,
			$keyword ? ', include keyword' : ''
		);

		$builder = $this->ai_client( $prompt );

		if ( is_wp_error( $builder ) ) {
			$this->log( 'error', $this->elapsed_ms( $start ) );
			return $builder;
		}

		$json = $builder
			->using_system_instruction( __( 'You are an expert eCommerce copywriter specialising in WooCommerce product pages. You write compelling, conversion-focused copy that highlights genuine product benefits.', 'abilityhub' ) )
			->using_temperature( 0.6 )
			->as_json_response( $this->output_schema )
			->generate_text();

		if ( is_wp_error( $json ) ) {
			$this->log( 'error', $this->elapsed_ms( $start ) );
			return $json;
		}

		$data = json_decode( $json, true );

		if ( json_last_error() !== JSON_ERROR_NONE || ! isset( $data['short_description'], $data['long_description'], $data['meta_description'] ) ) {
			$this->log( 'error', $this->elapsed_ms( $start ) );
			return new WP_Error( 'parse_error', __( 'AI returned an unexpected format. Please try again.', 'abilityhub' ) );
		}

		$this->log( 'success', $this->elapsed_ms( $start ) );

		return [
			'short_description' => wp_kses_post( $data['short_description'] ),
			'long_description'  => wp_kses_post( $data['long_description'] ),
			'meta_description'  => sanitize_text_field( $data['meta_description'] ),
		];
	}
}
