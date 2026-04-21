<?php
/**
 * Ability: Generate Upsell Copy
 *
 * @package AbilityHub
 */

defined( 'ABSPATH' ) || exit;

class AbilityHub_Ability_Upsell_Copy extends AbilityHub_Ability_Base {

	protected string $name        = 'abilityhub/generate-upsell-copy';
	protected string $label       = 'Generate upsell copy';
	protected string $description = 'Creates compelling upsell and cross-sell copy for related WooCommerce products.';
	protected string $category    = 'ecommerce';
	protected bool   $cacheable   = true;
	protected int    $cache_ttl   = HOUR_IN_SECONDS;

	protected array $input_schema = [
		'type'       => 'object',
		'required'   => [ 'product_name', 'related_products' ],
		'properties' => [
			'product_name' => [
				'type'        => 'string',
				'description' => 'The primary product the customer is viewing.',
			],
			'related_products' => [
				'type'        => 'array',
				'items'       => [ 'type' => 'string' ],
				'description' => 'Array of related product names to upsell.',
			],
		],
	];

	protected array $output_schema = [
		'type'       => 'object',
		'required'   => [ 'upsell_headline', 'upsell_body' ],
		'properties' => [
			'upsell_headline' => [
				'type'        => 'string',
				'description' => 'Compelling headline for the upsell section.',
			],
			'upsell_body' => [
				'type'        => 'string',
				'description' => '1-2 sentences per related product explaining why it pairs well.',
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

		$start            = $this->start_timer();
		$product_name     = sanitize_text_field( $input['product_name'] ?? '' );
		$related_products = array_map( 'sanitize_text_field', (array) ( $input['related_products'] ?? [] ) );

		if ( empty( $product_name ) ) {
			return new WP_Error( 'missing_product', __( 'Product name is required.', 'abilityhub' ) );
		}

		if ( empty( $related_products ) ) {
			return new WP_Error( 'missing_related', __( 'At least one related product is required.', 'abilityhub' ) );
		}

		$related_list = '- ' . implode( "\n- ", $related_products );

		$prompt = sprintf(
			"Write upsell copy for a WooCommerce product page.\n\nPrimary product: %s\nRelated products to upsell:\n%s\n\nGenerate:\n- upsell_headline: A specific, compelling headline (e.g. \"Complete the look\" or \"Frequently bought together\")\n- upsell_body: For each related product, 1-2 sentences explaining why it pairs well with the primary product",
			$product_name,
			$related_list
		);

		$builder = $this->ai_client( $prompt );

		if ( is_wp_error( $builder ) ) {
			$this->log( 'error', $this->elapsed_ms( $start ) );
			return $builder;
		}

		$json = $builder
			->using_system_instruction( __( 'You are an eCommerce conversion specialist. You write punchy, specific upsell copy that drives add-to-cart behaviour without feeling pushy.', 'abilityhub' ) )
			->using_temperature( 0.7 )
			->as_json_response( $this->output_schema )
			->generate_text();

		if ( is_wp_error( $json ) ) {
			$this->log( 'error', $this->elapsed_ms( $start ) );
			return $json;
		}

		$data = json_decode( $json, true );

		if ( json_last_error() !== JSON_ERROR_NONE || ! isset( $data['upsell_headline'], $data['upsell_body'] ) ) {
			$this->log( 'error', $this->elapsed_ms( $start ) );
			return new WP_Error( 'parse_error', __( 'AI returned an unexpected format. Please try again.', 'abilityhub' ) );
		}

		$this->log( 'success', $this->elapsed_ms( $start ) );

		return [
			'upsell_headline' => sanitize_text_field( $data['upsell_headline'] ),
			'upsell_body'     => wp_kses_post( $data['upsell_body'] ),
		];
	}
}
