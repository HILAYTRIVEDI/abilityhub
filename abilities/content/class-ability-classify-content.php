<?php
/**
 * Ability: Classify Content
 *
 * Suggests relevant categories and tags (or any taxonomy) for a post,
 * preferring existing site terms and falling back to new ones.
 * Mirrors the Content Classification experiment added in WP AI plugin v0.7.0.
 *
 * @package AbilityHub
 */

defined( 'ABSPATH' ) || exit;

class AbilityHub_Ability_Classify_Content extends AbilityHub_Ability_Base {

	protected string $name        = 'abilityhub/classify-content';
	protected string $label       = 'Classify content';
	protected string $description = 'Suggests relevant categories and tags for a post based on its content.';
	protected string $category    = 'seo';
	protected bool   $cacheable   = true;

	protected array $input_schema = [
		'type'       => 'object',
		'required'   => [ 'post_id' ],
		'properties' => [
			'post_id'         => [
				'type'        => 'integer',
				'description' => 'ID of the post to classify.',
			],
			'taxonomies'      => [
				'type'        => 'array',
				'description' => 'Taxonomy slugs to suggest terms for. Defaults to [category, post_tag].',
				'items'       => [ 'type' => 'string' ],
			],
			'max_suggestions' => [
				'type'        => 'integer',
				'description' => 'Maximum term suggestions per taxonomy (1–10). Defaults to 5.',
			],
		],
	];

	protected array $output_schema = [
		'type'       => 'object',
		'required'   => [ 'suggestions' ],
		'properties' => [
			'suggestions' => [
				'type'        => 'object',
				'description' => 'Map of taxonomy slug → array of suggested term name strings.',
			],
		],
	];

	public function execute( array $input ): array|WP_Error {
		$start   = $this->start_timer();
		$post_id = absint( $input['post_id'] );
		$post    = get_post( $post_id );

		if ( ! $post ) {
			return new WP_Error( 'post_not_found', __( 'Post not found.', 'abilityhub' ) );
		}

		$taxonomies      = ! empty( $input['taxonomies'] ) ? (array) $input['taxonomies'] : [ 'category', 'post_tag' ];
		$max_suggestions = isset( $input['max_suggestions'] ) ? max( 1, min( 10, absint( $input['max_suggestions'] ) ) ) : 5;

		// Keep only taxonomies that exist and are registered for this post type.
		$valid_taxonomies = array_values( array_filter(
			$taxonomies,
			static fn( $t ) => taxonomy_exists( $t ) && is_object_in_taxonomy( $post->post_type, $t )
		) );

		if ( empty( $valid_taxonomies ) ) {
			return new WP_Error(
				'no_valid_taxonomies',
				__( 'None of the requested taxonomies are registered for this post type.', 'abilityhub' )
			);
		}

		// Strip HTML and run content through standard WP filters.
		$content = wp_strip_all_tags( apply_filters( 'the_content', $post->post_content ) );
		$title   = $post->post_title;
		$suggestions = [];

		foreach ( $valid_taxonomies as $taxonomy ) {
			$tax_obj   = get_taxonomy( $taxonomy );
			$tax_label = $tax_obj ? $tax_obj->label : $taxonomy;

			// Fetch up to 80 existing terms as candidates — AI prefers known terms.
			$existing = get_terms( [
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'number'     => 80,
				'fields'     => 'names',
			] );
			$existing_list = ( ! is_wp_error( $existing ) && ! empty( $existing ) )
				? implode( ', ', $existing )
				: '(none yet)';

			$prompt = sprintf(
				"Post title: %s\n\nPost content:\n%s\n\nExisting site %s: %s\n\nSuggest up to %d %s terms that best match this post. Prefer existing terms when relevant; only suggest new terms if none fit. Return a JSON array of term name strings only, e.g. [\"Term A\",\"Term B\"].",
				$title,
				mb_substr( $content, 0, 2000 ),
				$tax_label,
				$existing_list,
				$max_suggestions,
				$tax_label
			);

			$builder = $this->ai_client( $prompt );
			if ( is_wp_error( $builder ) ) {
				$this->log( 'error', $this->elapsed_ms( $start ) );
				return $builder;
			}

			$builder = $this->ensure_text_generation_supported( $builder );
			if ( is_wp_error( $builder ) ) {
				$this->log( 'error', $this->elapsed_ms( $start ) );
				return $builder;
			}

			$response = $builder
				->using_system_instruction( 'You are a content taxonomy expert. Respond in the same language as the post content. Return only a valid JSON array of strings — no explanation, no markdown.' )
				->using_temperature( 0.3 )
				->generate_text();

			if ( is_wp_error( $response ) ) {
				// Don't abort the whole batch — return empty for this taxonomy.
				$suggestions[ $taxonomy ] = [];
				continue;
			}

			$decoded              = $this->parse_json_response( $response );
			$suggestions[ $taxonomy ] = is_array( $decoded )
				? array_values( array_filter( $decoded, 'is_string' ) )
				: [];
		}

		$this->log( 'success', $this->elapsed_ms( $start ) );

		return [ 'suggestions' => $suggestions ];
	}
}
