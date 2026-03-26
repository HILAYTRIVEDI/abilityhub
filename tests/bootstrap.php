<?php
/**
 * PHPUnit bootstrap for AbilityHub plugin tests.
 *
 * Defines stub WordPress functions and classes so plugin source files
 * can be loaded and tested without a full WordPress installation.
 *
 * @package AbilityHub
 */

// Patchwork (used by Brain\Monkey) must be loaded first.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// ---------------------------------------------------------------------------
// Plugin constants
// ---------------------------------------------------------------------------
define( 'ABSPATH',               '/tmp/' );
define( 'ABILITYHUB_VERSION',    '1.0.0' );
define( 'ABILITYHUB_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'ABILITYHUB_PLUGIN_URL', 'http://example.com/wp-content/plugins/abilityhub/' );
define( 'ABILITYHUB_NAMESPACE',  'abilityhub' );

// ---------------------------------------------------------------------------
// Core WordPress class stubs
// ---------------------------------------------------------------------------

class WP_Error {
    private string $code;
    private string $message;

    public function __construct( string $code = '', string $message = '', $data = null ) {
        $this->code    = $code;
        $this->message = $message;
    }

    public function get_error_code(): string  { return $this->code; }
    public function get_error_codes(): array  { return [ $this->code ]; }
    public function get_error_message( string $code = '' ): string { return $this->message; }
    public function get_error_messages(): array { return [ $this->message ]; }
}

// ---------------------------------------------------------------------------
// Core WordPress function stubs
// These pure-utility stubs never touch a DB or external service, so they are
// safe to define once for the entire test run. Brain\Monkey (via Patchwork)
// can still override any of them in individual tests that need specific values.
// ---------------------------------------------------------------------------

function is_wp_error( $thing ): bool {
    return $thing instanceof WP_Error;
}

function sanitize_text_field( $str ): string {
    return trim( strip_tags( (string) $str ) );
}

function sanitize_textarea_field( $str ): string {
    return trim( strip_tags( (string) $str ) );
}

function sanitize_file_name( string $filename ): string {
    return preg_replace( '/[^A-Za-z0-9._\-]/', '', basename( $filename ) );
}

function sanitize_title( string $title ): string {
    return strtolower( preg_replace( '/\s+/', '-', trim( $title ) ) );
}

function wp_kses_post( $data ): string {
    return (string) $data;
}

function absint( $maybeint ): int {
    return abs( (int) $maybeint );
}

function esc_url_raw( string $url ): string {
    return $url;
}

function wp_trim_words( string $text, int $num_words = 55, string $more = '' ): string {
    $words = preg_split( '/[\n\r\t ]+/', $text, -1, PREG_SPLIT_NO_EMPTY );
    return implode( ' ', array_slice( $words, 0, $num_words ) );
}

function wp_parse_args( $args, array $defaults = [] ): array {
    if ( is_string( $args ) ) {
        parse_str( $args, $args );
    }
    return array_merge( $defaults, (array) $args );
}

function current_time( string $type, bool $gmt = false ): string {
    return 'mysql' === $type ? date( 'Y-m-d H:i:s' ) : (string) time();
}

// ---------------------------------------------------------------------------
// Minimal $wpdb stub for Logger tests
// ---------------------------------------------------------------------------

class Abilityhub_Test_Wpdb {
    public string $prefix      = 'wp_';
    public array  $last_insert = [];
    public array  $queries     = [];

    public function get_charset_collate(): string { return ''; }

    public function prepare( string $query ): string { return $query; }

    public function insert( string $table, array $data, array $format = [] ): int {
        $this->last_insert = $data;
        return 1;
    }

    public function get_var( string $query ): string { return '0'; }

    public function get_results( string $query, string $output = '' ): array { return []; }

    public function query( string $query ): bool {
        $this->queries[] = $query;
        return true;
    }
}

$GLOBALS['wpdb'] = new Abilityhub_Test_Wpdb();

// ---------------------------------------------------------------------------
// Load plugin source files
// ---------------------------------------------------------------------------

require_once ABILITYHUB_PLUGIN_DIR . 'includes/class-abilityhub-logger.php';
require_once ABILITYHUB_PLUGIN_DIR . 'includes/class-abilityhub-ai-client.php';
require_once ABILITYHUB_PLUGIN_DIR . 'abilities/class-abilityhub-ability-base.php';

// Content abilities
require_once ABILITYHUB_PLUGIN_DIR . 'abilities/content/class-ability-generate-meta.php';
require_once ABILITYHUB_PLUGIN_DIR . 'abilities/content/class-ability-rewrite-tone.php';
require_once ABILITYHUB_PLUGIN_DIR . 'abilities/content/class-ability-summarise-post.php';
require_once ABILITYHUB_PLUGIN_DIR . 'abilities/content/class-ability-suggest-links.php';
require_once ABILITYHUB_PLUGIN_DIR . 'abilities/content/class-ability-translate-block.php';

// WooCommerce abilities
require_once ABILITYHUB_PLUGIN_DIR . 'abilities/woocommerce/class-ability-product-description.php';
require_once ABILITYHUB_PLUGIN_DIR . 'abilities/woocommerce/class-ability-review-response.php';
require_once ABILITYHUB_PLUGIN_DIR . 'abilities/woocommerce/class-ability-upsell-copy.php';
require_once ABILITYHUB_PLUGIN_DIR . 'abilities/woocommerce/class-ability-moderate-comment.php';

// Developer abilities
require_once ABILITYHUB_PLUGIN_DIR . 'abilities/developer/class-ability-generate-block-pattern.php';
require_once ABILITYHUB_PLUGIN_DIR . 'abilities/developer/class-ability-explain-php-error.php';
require_once ABILITYHUB_PLUGIN_DIR . 'abilities/developer/class-ability-write-hook-docs.php';

// Media abilities
require_once ABILITYHUB_PLUGIN_DIR . 'abilities/media/class-ability-generate-alt-text.php';
require_once ABILITYHUB_PLUGIN_DIR . 'abilities/media/class-ability-suggest-filename.php';

// Site abilities
require_once ABILITYHUB_PLUGIN_DIR . 'abilities/site/class-ability-mcp-manifest.php';

// Workflow engine
require_once ABILITYHUB_PLUGIN_DIR . 'includes/workflows/class-workflow.php';
require_once ABILITYHUB_PLUGIN_DIR . 'includes/workflows/class-workflow-registry.php';
require_once ABILITYHUB_PLUGIN_DIR . 'includes/workflows/functions-workflow-api.php';
