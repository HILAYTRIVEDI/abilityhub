<?php
/**
 * Ability: MCP Capability Manifest
 *
 * Turns any WordPress site into a fully-discoverable MCP server by exposing
 * all registered abilities as MCP-compatible tool definitions.
 *
 * @package AbilityHub
 */

defined( 'ABSPATH' ) || exit;

class AbilityHub_Ability_Mcp_Manifest extends AbilityHub_Ability_Base {

    protected string $name        = 'abilityhub/mcp-capability-manifest';
    protected string $label       = 'MCP capability manifest';
    protected string $description = 'Returns a full MCP-compatible manifest of all AI abilities registered on this WordPress site, making it a discoverable MCP server.';
    protected string $category    = 'site';

    protected array $input_schema = [
        'type'       => 'object',
        'properties' => [],
    ];

    protected array $output_schema = [
        'type'       => 'object',
        'required'   => [ 'manifest', 'total_abilities', 'mcp_endpoint' ],
        'properties' => [
            'manifest' => [
                'type'        => 'object',
                'description' => 'MCP-compatible tool manifest.',
            ],
            'total_abilities' => [
                'type'        => 'integer',
                'description' => 'Total number of registered abilities.',
            ],
            'mcp_endpoint' => [
                'type'        => 'string',
                'description' => 'REST API endpoint for ability discovery.',
            ],
        ],
    ];

    public function check_permission(): bool {
        return current_user_can( 'manage_options' );
    }

    public function execute( array $input ): array|WP_Error {
        $start = $this->start_timer();

        if ( ! function_exists( 'wp_get_abilities' ) ) {
            return new WP_Error(
                'no_abilities_api',
                __( 'WordPress Abilities API not available. Requires WordPress 7.0+.', 'abilityhub' )
            );
        }

        // Get ALL registered abilities on this site (not just AbilityHub's)
        $abilities = wp_get_abilities();

        if ( empty( $abilities ) ) {
            $this->log( 'success', $this->elapsed_ms( $start ) );
            return [
                'manifest'        => [ 'tools' => [] ],
                'total_abilities' => 0,
                'mcp_endpoint'    => rest_url( 'wp-abilities/v1/abilities' ),
            ];
        }

        // Transform each ability into MCP tool format
        $mcp_tools = [];

        foreach ( $abilities as $ability_name => $ability ) {
            $mcp_tools[] = $this->to_mcp_tool( $ability_name, $ability );
        }

        $manifest = [
            'protocol_version' => '2024-11-05',
            'server_info'      => [
                'name'    => get_bloginfo( 'name' ) . ' (WordPress + AbilityHub)',
                'version' => ABILITYHUB_VERSION,
                'url'     => get_site_url(),
            ],
            'capabilities'   => [
                'tools' => new \stdClass(), // MCP tools capability flag
            ],
            'tools'          => $mcp_tools,
        ];

        $this->log( 'success', $this->elapsed_ms( $start ) );

        return [
            'manifest'        => $manifest,
            'total_abilities' => count( $mcp_tools ),
            'mcp_endpoint'    => rest_url( 'wp-abilities/v1/abilities' ),
        ];
    }

    /**
     * Convert a WordPress ability to MCP tool format.
     *
     * @param string $name    Ability slug.
     * @param mixed  $ability Ability object or array from wp_get_abilities().
     * @return array MCP tool definition.
     */
    private function to_mcp_tool( string $name, $ability ): array {
        // Handle both object and array representations
        if ( is_object( $ability ) ) {
            $label        = method_exists( $ability, 'get_label' )       ? $ability->get_label()        : $name;
            $description  = method_exists( $ability, 'get_description' ) ? $ability->get_description()  : '';
            $input_schema = method_exists( $ability, 'get_input_schema' ) ? $ability->get_input_schema() : [];
        } elseif ( is_array( $ability ) ) {
            $label        = $ability['label']        ?? $name;
            $description  = $ability['description']  ?? '';
            $input_schema = $ability['input_schema'] ?? [];
        } else {
            $label        = $name;
            $description  = '';
            $input_schema = [];
        }

        return [
            'name'        => $name,
            'description' => $description ?: $label,
            'inputSchema' => ! empty( $input_schema )
                ? $input_schema
                : [ 'type' => 'object', 'properties' => new \stdClass() ],
        ];
    }
}
