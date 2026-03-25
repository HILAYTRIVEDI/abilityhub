<?php
/**
 * Thin wrapper around the WordPress 7.0 AI Client fluent API.
 *
 * @package AbilityHub
 */

defined( 'ABSPATH' ) || exit;

class AbilityHub_AI_Client {

	/**
	 * Send a plain-text prompt and return the response.
	 *
	 * For full fluent control (temperature, schema, system instruction)
	 * use AbilityHub_AI_Client::get_builder() instead.
	 *
	 * @param string $prompt The prompt text.
	 * @return string|WP_Error Response text or WP_Error on failure.
	 */
	public static function prompt( string $prompt ): string|WP_Error {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return new WP_Error(
				'no_ai_client',
				__( 'WordPress AI Client is not available. Requires WordPress 7.0+.', 'abilityhub' )
			);
		}

		return wp_ai_client_prompt( $prompt )->generate_text();
	}

	/**
	 * Return the fluent AI prompt builder for full control.
	 *
	 * Usage:
	 *   $builder = AbilityHub_AI_Client::get_builder( $prompt );
	 *   if ( is_wp_error( $builder ) ) { return $builder; }
	 *   $result = $builder->using_system_instruction( '...' )
	 *                     ->using_temperature( 0.4 )
	 *                     ->as_json_response( $schema )
	 *                     ->generate_text();
	 *
	 * @param string $prompt The user-facing prompt text.
	 * @return object|WP_Error Fluent builder or WP_Error if API unavailable.
	 */
	public static function get_builder( string $prompt ) {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return new WP_Error(
				'no_ai_client',
				__( 'WordPress AI Client is not available. Requires WordPress 7.0+.', 'abilityhub' )
			);
		}

		return wp_ai_client_prompt( $prompt );
	}

	/**
	 * Check whether the WordPress AI Client is available.
	 *
	 * @return bool
	 */
	public static function is_available(): bool {
		return function_exists( 'wp_ai_client_prompt' );
	}

	/**
	 * Check whether text generation is supported by the active provider.
	 *
	 * @return bool
	 */
	public static function is_supported_for_text_generation(): bool {
		if ( ! self::is_available() ) {
			return false;
		}

		return wp_ai_client_prompt( '' )->is_supported_for_text_generation();
	}

	/**
	 * Get the name of the active AI provider.
	 *
	 * @return string Provider name or descriptive fallback.
	 */
	public static function get_provider_name(): string {
		if ( function_exists( 'wp_get_active_ai_provider' ) ) {
			$provider = wp_get_active_ai_provider();
			return $provider ? $provider->get_name() : __( 'Not configured', 'abilityhub' );
		}

		return self::is_available()
			? __( 'Configured (provider name unavailable)', 'abilityhub' )
			: __( 'Not available — requires WordPress 7.0+', 'abilityhub' );
	}
}
