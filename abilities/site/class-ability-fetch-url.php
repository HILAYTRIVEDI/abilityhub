<?php
/**
 * Ability: Fetch URL
 *
 * Retrieves real-time content from any public URL — RSS/Atom feeds, web pages,
 * or JSON APIs. Results are NOT cached so the data is always fresh.
 *
 * @package AbilityHub
 */

defined( 'ABSPATH' ) || exit;

class AbilityHub_Ability_Fetch_Url extends AbilityHub_Ability_Base {

	protected string $name        = 'abilityhub/fetch-url';
	protected string $label       = 'Fetch URL';
	protected string $description = 'Retrieves real-time content from a public URL — news RSS feeds, web pages, or JSON APIs.';
	protected string $category    = 'site';
	protected bool   $cacheable   = false;

	protected array $input_schema = [
		'type'       => 'object',
		'required'   => [ 'url' ],
		'properties' => [
			'url'    => [
				'type'        => 'string',
				'description' => 'The public URL to fetch.',
			],
			'format' => [
				'type'        => 'string',
				'enum'        => [ 'auto', 'rss', 'text', 'json' ],
				'description' => '"auto" detects RSS vs text automatically. Defaults to "auto".',
			],
		],
	];

	protected array $output_schema = [
		'type'       => 'object',
		'properties' => [
			'url'        => [ 'type' => 'string' ],
			'content'    => [ 'type' => 'string', 'description' => 'Plain text page content (text/json mode).' ],
			'feed_title' => [ 'type' => 'string', 'description' => 'Feed title (rss mode).' ],
			'items'      => [ 'type' => 'array',  'description' => 'Feed items: title, link, date, summary (rss mode).' ],
		],
	];

	public function execute( array $input ): array|WP_Error {
		$url    = esc_url_raw( trim( $input['url'] ) );
		$format = sanitize_key( $input['format'] ?? 'auto' );

		if ( ! wp_http_validate_url( $url ) ) {
			return new WP_Error(
				'invalid_url',
				__( 'The URL is not valid or is not a publicly accessible address.', 'abilityhub' )
			);
		}

		$parsed = wp_parse_url( $url );

		if ( ! in_array( $parsed['scheme'] ?? '', [ 'http', 'https' ], true ) ) {
			return new WP_Error(
				'invalid_scheme',
				__( 'Only HTTP and HTTPS URLs are supported.', 'abilityhub' )
			);
		}

		// Route by format, auto-detecting feeds from URL patterns.
		if ( 'rss' === $format || ( 'auto' === $format && $this->looks_like_feed( $url ) ) ) {
			return $this->fetch_rss( $url );
		}

		if ( 'json' === $format ) {
			return $this->fetch_json( $url );
		}

		return $this->fetch_text( $url );
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	private function looks_like_feed( string $url ): bool {
		$lower = strtolower( $url );
		return str_contains( $lower, '/feed' )
			|| str_contains( $lower, 'rss'   )
			|| str_contains( $lower, 'atom'  )
			|| str_ends_with( $lower, '.xml' );
	}

	private function fetch_rss( string $url ): array|WP_Error {
		if ( ! function_exists( 'fetch_feed' ) ) {
			require_once ABSPATH . WPINC . '/feed.php';
		}

		$feed = fetch_feed( $url );

		if ( is_wp_error( $feed ) ) {
			// Graceful fallback: try plain-text fetch.
			return $this->fetch_text( $url );
		}

		$count = min( 20, $feed->get_item_quantity() );
		$items = [];

		for ( $i = 0; $i < $count; $i++ ) {
			$item    = $feed->get_item( $i );
			$items[] = [
				'title'   => $item->get_title(),
				'link'    => $item->get_permalink(),
				'date'    => $item->get_date( 'Y-m-d H:i:s' ),
				'summary' => wp_trim_words(
					wp_strip_all_tags( (string) $item->get_description() ),
					60
				),
			];
		}

		return [
			'url'        => $url,
			'feed_title' => $feed->get_title(),
			'items'      => $items,
		];
	}

	private function fetch_text( string $url ): array|WP_Error {
		$response = wp_remote_get( $url, $this->http_args() );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'http_error',
				/* translators: %d: HTTP status code */
				sprintf( __( 'URL returned HTTP %d.', 'abilityhub' ), $code )
			);
		}

		$text = preg_replace( '/\s+/', ' ', wp_strip_all_tags( wp_remote_retrieve_body( $response ) ) );

		return [
			'url'     => $url,
			'content' => mb_substr( trim( $text ), 0, 10000 ),
		];
	}

	private function fetch_json( string $url ): array|WP_Error {
		$args             = $this->http_args();
		$args['headers']  = [ 'Accept' => 'application/json' ];

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body    = wp_remote_retrieve_body( $response );
		$decoded = json_decode( $body, true );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return new WP_Error( 'json_parse_error', __( 'Could not parse JSON response.', 'abilityhub' ) );
		}

		return [
			'url'     => $url,
			'content' => mb_substr( $body, 0, 10000 ),
			'data'    => $decoded,
		];
	}

	private function http_args(): array {
		return [
			'timeout'             => 15,
			'limit_response_size' => 500000, // 500 KB cap.
			'user-agent'          => 'AbilityHub/1.0 WordPress/' . get_bloginfo( 'version' ),
		];
	}
}
