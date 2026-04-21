<?php
/**
 * Ability: Suggest Internal Links
 *
 * @package AbilityHub
 */

defined( 'ABSPATH' ) || exit;

class AbilityHub_Ability_Suggest_Links extends AbilityHub_Ability_Base {

	protected string $name        = 'abilityhub/suggest-internal-links';
	protected string $label       = 'Suggest internal links';
	protected string $description = 'Analyses post content and suggests 3-5 relevant internal links from existing posts.';
	protected string $category    = 'seo';
	protected bool   $cacheable   = true;

	protected array $input_schema = [
		'type'       => 'object',
		'required'   => [ 'content' ],
		'properties' => [
			'content' => [
				'type'        => 'string',
				'description' => 'The post content to analyse for link opportunities.',
			],
			'post_id' => [
				'type'        => 'integer',
				'description' => 'The current post ID (to exclude from suggestions).',
			],
		],
	];

	protected array $output_schema = [
		'type'       => 'object',
		'required'   => [ 'suggestions' ],
		'properties' => [
			'suggestions' => [
				'type'  => 'array',
				'items' => [
					'type'       => 'object',
					'required'   => [ 'anchor_text', 'suggested_slug', 'reason' ],
					'properties' => [
						'anchor_text'    => [ 'type' => 'string' ],
						'suggested_slug' => [ 'type' => 'string' ],
						'reason'         => [ 'type' => 'string' ],
					],
				],
			],
		],
	];

	public function execute( array $input ): array|WP_Error {
		$start   = $this->start_timer();
		$content = wp_kses_post( $input['content'] ?? '' );
		$post_id = absint( $input['post_id'] ?? 0 );

		if ( empty( $content ) ) {
			return new WP_Error( 'missing_content', __( 'Content is required.', 'abilityhub' ) );
		}

		// Fetch recent published posts (excluding the current one).
		$exclude = $post_id ? [ $post_id ] : [];
		$posts   = get_posts( [
			'numberposts'  => 20,
			'post_status'  => 'publish',
			'post__not_in' => $exclude,
		] );

		if ( empty( $posts ) ) {
			return new WP_Error( 'no_posts', __( 'No published posts found to suggest links from.', 'abilityhub' ) );
		}

		// Build a list of available posts for the AI.
		$post_list = '';
		foreach ( $posts as $post ) {
			$slug       = get_post_field( 'post_name', $post->ID );
			$post_list .= "- Title: \"{$post->post_title}\" | Slug: {$slug}\n";
		}

		$prompt = sprintf(
			"Suggest 3 to 5 internal links for the article below based on the available posts.\n\nFor each suggestion provide:\n- anchor_text: a natural phrase in the article that could be linked\n- suggested_slug: the post slug to link to\n- reason: one sentence explaining the relevance\n\nAvailable posts:\n%s\nArticle:\n%s",
			$post_list,
			wp_trim_words( $content, 400 )
		);

		$builder = $this->ai_client( $prompt );

		if ( is_wp_error( $builder ) ) {
			$this->log( 'error', $this->elapsed_ms( $start ) );
			return $builder;
		}

		$json = $builder
			->using_system_instruction( __( 'You are an SEO expert specialising in internal linking strategy. Suggest links that genuinely help readers and improve site structure.', 'abilityhub' ) )
			->using_temperature( 0.3 )
			->as_json_response( $this->output_schema )
			->generate_text();

		if ( is_wp_error( $json ) ) {
			$this->log( 'error', $this->elapsed_ms( $start ) );
			return $json;
		}

		$data = json_decode( $json, true );

		if ( json_last_error() !== JSON_ERROR_NONE || ! isset( $data['suggestions'] ) ) {
			$this->log( 'error', $this->elapsed_ms( $start ) );
			return new WP_Error( 'parse_error', __( 'AI returned an unexpected format. Please try again.', 'abilityhub' ) );
		}

		// Sanitize each suggestion.
		$suggestions = [];

		foreach ( (array) $data['suggestions'] as $item ) {
			if ( ! isset( $item['anchor_text'], $item['suggested_slug'], $item['reason'] ) ) {
				continue;
			}

			$suggestions[] = [
				'anchor_text'    => sanitize_text_field( $item['anchor_text'] ),
				'suggested_slug' => sanitize_title( $item['suggested_slug'] ),
				'reason'         => sanitize_text_field( $item['reason'] ),
			];
		}

		$this->log( 'success', $this->elapsed_ms( $start ) );

		return [ 'suggestions' => $suggestions ];
	}
}
