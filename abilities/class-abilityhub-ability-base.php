<?php
/**
 * Abstract base class for all AbilityHub abilities.
 *
 * @package AbilityHub
 */

defined( 'ABSPATH' ) || exit;

abstract class AbilityHub_Ability_Base {

    /** @var string Ability slug, e.g. 'abilityhub/generate-meta-description' */
    protected string $name;

    /** @var string Human-readable label. */
    protected string $label;

    /** @var string Short description. */
    protected string $description;

    /** @var string Category slug. */
    protected string $category;

    /** @var array JSON Schema for inputs. */
    protected array $input_schema = [];

    /** @var array JSON Schema for outputs. */
    protected array $output_schema = [];

    /** @var bool Whether to expose via REST API. */
    protected bool $show_in_rest = true;

    /**
     * Whether to cache AI responses for identical inputs.
     * Set to true in subclasses to enable transient-based response caching.
     */
    protected bool $cacheable = false;

    /**
     * How long (in seconds) to cache AI responses when $cacheable is true.
     * Defaults to 24 hours. Creative abilities (temp ≥ 0.6) should use HOUR_IN_SECONDS.
     */
    protected int $cache_ttl = DAY_IN_SECONDS;

    /**
     * Tracks which ability is currently executing so the token tracker can
     * attribute AI calls to the correct ability slug. PHP is single-threaded,
     * so a static property is safe here.
     */
    public static string $current_ability = '';

    /**
     * Static registry of all instantiated and registered abilities, keyed by
     * ability slug. Populated in register(). Lets the intent executor call
     * abilities directly without depending on wp_execute_ability().
     *
     * @var array<string, static>
     */
    public static array $registry = [];

    /**
     * Register this ability with WordPress.
     * Must be called inside wp_abilities_api_init.
     */
    public function register(): void {
        // Respect admin's enable/disable setting for this ability.
        $disabled = (array) get_option( 'abilityhub_disabled_abilities', [] );
        if ( in_array( $this->name, $disabled, true ) ) {
            return;
        }

        // Always store in the static registry so the intent executor can call
        // abilities directly without depending on wp_execute_ability() or even
        // having the WP Abilities API available.
        self::$registry[ $this->name ] = $this;

        if ( ! function_exists( 'wp_register_ability' ) ) {
            return; // Registry populated; WP Abilities API registration skipped.
        }

        // Avoid duplicate registration if called more than once (e.g. init + wp_abilities_api_init).
        if ( function_exists( 'wp_get_ability' ) && wp_get_ability( $this->name ) ) {
            return;
        }

        wp_register_ability( $this->name, [
            'label'               => __( $this->label,       'abilityhub' ),
            'description'         => __( $this->description, 'abilityhub' ),
            'category'            => $this->category,
            'input_schema'        => $this->input_schema,
            'output_schema'       => $this->output_schema,
            'execute_callback'    => [ $this, 'run' ],
            'permission_callback' => [ $this, 'check_permission' ],
            'meta'                => [ 'show_in_rest' => $this->show_in_rest ],
        ] );
    }

    /**
     * Entry point registered with WordPress. Sets the static context for the
     * token tracker, then routes through caching or directly to execute().
     *
     * @param array $input Validated input matching input_schema.
     * @return array|WP_Error
     */
    public function run( array $input ): array|WP_Error {
        self::$current_ability = $this->name;
        $result = $this->cacheable ? $this->cached_execute( $input ) : $this->execute( $input );
        self::$current_ability = '';
        return $result;
    }

    /**
     * Cache-aware wrapper around execute().
     * Results are stored as transients keyed by ability name + sorted input hash.
     *
     * @param array $input Validated input matching input_schema.
     * @return array|WP_Error
     */
    public function cached_execute( array $input ): array|WP_Error {
        ksort( $input );
        $cache_key = 'abilityhub_resp_' . md5( $this->name . wp_json_encode( $input ) );

        $cached = get_transient( $cache_key );
        if ( false !== $cached ) {
            return $cached;
        }

        $result = $this->execute( $input );

        if ( ! is_wp_error( $result ) ) {
            set_transient( $cache_key, $result, $this->cache_ttl );
        }

        return $result;
    }

    /**
     * Execute the ability.
     *
     * @param array $input Validated input matching input_schema.
     * @return array|WP_Error Result matching output_schema, or WP_Error.
     */
    abstract public function execute( array $input ): array|WP_Error;

    /**
     * Permission check. Override for custom requirements.
     *
     * @return bool
     */
    public function check_permission(): bool {
        return current_user_can( 'edit_posts' );
    }

    /**
     * Call the WordPress AI Client and return the response as a plain string.
     *
     * For full fluent control (temperature, JSON schema, system instruction)
     * use $this->ai_client() instead.
     *
     * @param string $prompt The prompt text.
     * @return string|WP_Error
     */
    protected function ai_prompt( string $prompt ): string|WP_Error {
        return AbilityHub_AI_Client::prompt( $prompt );
    }

    /**
     * Return the fluent AI prompt builder for this ability.
     *
     * Usage:
     *   $builder = $this->ai_client( $prompt );
     *   if ( is_wp_error( $builder ) ) { return $builder; }
     *   $result = $builder->using_system_instruction( '...' )
     *                     ->using_temperature( 0.4 )
     *                     ->as_json_response( $schema )
     *                     ->generate_text();
     *
     * @param string $prompt The user-facing prompt text.
     * @return object|WP_Error Fluent builder or WP_Error if API unavailable.
     */
    protected function ai_client( string $prompt ) {
        return AbilityHub_AI_Client::get_builder( $prompt );
    }

    /**
     * Guard: ensure the active provider supports text generation before calling generate_text().
     * Mirrors the pattern introduced in WP AI plugin v0.7.0 (PR #362).
     *
     * @param object $builder The fluent prompt builder from ai_client().
     * @param string $message Optional custom error message.
     * @return object|WP_Error The builder unchanged, or WP_Error if unsupported.
     */
    protected function ensure_text_generation_supported( $builder, string $message = '' ) {
        if ( method_exists( $builder, 'is_supported_for_text_generation' ) && ! $builder->is_supported_for_text_generation() ) {
            return new WP_Error(
                'unsupported_provider',
                $message ?: __( 'The active AI provider does not support text generation.', 'abilityhub' )
            );
        }
        return $builder;
    }

    /**
     * Guard: ensure the active provider supports image generation.
     * Mirrors the pattern introduced in WP AI plugin v0.7.0 (PR #362).
     *
     * @param object $builder The fluent prompt builder from ai_client().
     * @param string $message Optional custom error message.
     * @return object|WP_Error The builder unchanged, or WP_Error if unsupported.
     */
    protected function ensure_image_generation_supported( $builder, string $message = '' ) {
        if ( method_exists( $builder, 'is_supported_for_image_generation' ) && ! $builder->is_supported_for_image_generation() ) {
            return new WP_Error(
                'unsupported_provider',
                $message ?: __( 'The active AI provider does not support image generation.', 'abilityhub' )
            );
        }
        return $builder;
    }

    /**
     * Log this ability's execution.
     *
     * @param string $status      'success' or 'error'.
     * @param int    $duration_ms Execution duration in ms.
     */
    protected function log( string $status, int $duration_ms ): void {
        AbilityHub_Logger::log( [
            'ability'     => $this->name,
            'status'      => $status,
            'duration_ms' => $duration_ms,
        ] );
    }

    /**
     * Helper: get start time for duration tracking.
     *
     * @return float
     */
    protected function start_timer(): float {
        return microtime( true );
    }

    /**
     * Helper: calculate elapsed ms since start_timer().
     *
     * @param float $start Result of start_timer().
     * @return int
     */
    protected function elapsed_ms( float $start ): int {
        return (int) ( ( microtime( true ) - $start ) * 1000 );
    }

    /**
     * Helper: decode JSON response from AI and validate required keys.
     *
     * @param string $response      Raw AI response string.
     * @param array  $required_keys Keys that must be present.
     * @return array|WP_Error
     */
    protected function parse_json_response( string $response, array $required_keys = [] ): array|WP_Error {
        // Strip markdown code fences if present
        $clean = preg_replace( '/^```(?:json)?\s*/i', '', trim( $response ) );
        $clean = preg_replace( '/\s*```$/', '', $clean );

        $decoded = json_decode( $clean, true );

        if ( ! is_array( $decoded ) ) {
            return new WP_Error(
                'parse_error',
                __( 'AI returned an unexpected format. Please try again.', 'abilityhub' )
            );
        }

        foreach ( $required_keys as $key ) {
            if ( ! array_key_exists( $key, $decoded ) ) {
                return new WP_Error(
                    'missing_key',
                    /* translators: %s: missing JSON key name */
                    sprintf( __( 'AI response missing required field: %s', 'abilityhub' ), $key )
                );
            }
        }

        return $decoded;
    }

    /**
     * Expose ability metadata for the store UI.
     *
     * @return array
     */
    public function get_meta(): array {
        return [
            'name'          => $this->name,
            'label'         => $this->label,
            'description'   => $this->description,
            'category'      => $this->category,
            'input_schema'  => $this->input_schema,
            'output_schema' => $this->output_schema,
        ];
    }
}
