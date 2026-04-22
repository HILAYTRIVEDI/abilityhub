<?php
/**
 * Ability: Get Posts
 *
 * @package AbilityHub
 */

defined( 'ABSPATH' ) || exit;

class AbilityHub_Ability_Get_Posts extends AbilityHub_Ability_Base {

	protected string $name        = 'abilityhub/get-posts';
	protected string $label       = 'Get posts';
	protected string $description = 'Retrieves a list of posts or pages with their IDs, titles, statuses, and URLs. Use this before managing posts to find the correct post ID.';
	protected string $category    = 'site';
	protected bool   $cacheable   = false;

	protected array $input_schema = [
		'type'       => 'object',
		'properties' => [
			'post_type'   => [
				'type'        => 'string',
				'description' => 'Post type to query: post, page, or any public CPT (default: post).',
			],
			'post_status' => [
				'type'        => 'string',
				'description' => 'Status filter: publish, draft, pending, private, trash, or any (default: any).',
			],
			'limit' => [
				'type'        => 'integer',
				'description' => 'Maximum number of results to return (default: 10, max: 50).',
			],
			'search' => [
				'type'        => 'string',
				'description' => 'Optional keyword to search in post title and content.',
			],
			'orderby' => [
				'type'        => 'string',
				'description' => 'Sort field: date, title, modified, ID (default: date).',
			],
			'order' => [
				'type'        => 'string',
				'description' => 'Sort direction: ASC or DESC (default: DESC).',
			],
		],
	];

	protected array $output_schema = [
		'type'     => 'object',
		'required' => [ 'posts', 'total' ],
		'properties' => [
			'posts' => [
				'type'        => 'array',
				'description' => 'Array of post objects with id, title, status, type, date, url, excerpt, author.',
			],
			'total' => [
				'type'        => 'integer',
				'description' => 'Total number of posts matching the query.',
			],
		],
	];

	public function execute( array $input ): array|WP_Error {
		$start = $this->start_timer();

		$post_type   = sanitize_key( $input['post_type']   ?? 'post' );
		$post_status = sanitize_key( $input['post_status'] ?? 'any'  );
		$limit       = min( 50, max( 1, absint( $input['limit'] ?? 10 ) ) );
		$search      = sanitize_text_field( $input['search'] ?? '' );
		$orderby     = sanitize_key( $input['orderby'] ?? 'date' );
		$order       = strtoupper( sanitize_key( $input['order'] ?? 'DESC' ) ) === 'ASC' ? 'ASC' : 'DESC';

		// Validate post type
		$valid_types = array_keys( get_post_types( [ 'public' => true ] ) );
		if ( ! in_array( $post_type, $valid_types, true ) ) {
			$post_type = 'post';
		}

		$valid_statuses = [ 'publish', 'draft', 'pending', 'private', 'trash', 'any' ];
		if ( ! in_array( $post_status, $valid_statuses, true ) ) {
			$post_status = 'any';
		}

		$valid_orderbys = [ 'date', 'title', 'modified', 'ID', 'name' ];
		if ( ! in_array( $orderby, $valid_orderbys, true ) ) {
			$orderby = 'date';
		}

		$query_args = [
			'post_type'      => $post_type,
			'post_status'    => $post_status,
			'posts_per_page' => $limit,
			'orderby'        => $orderby,
			'order'          => $order,
			'no_found_rows'  => false,
		];

		if ( ! empty( $search ) ) {
			$query_args['s'] = $search;
		}

		$query = new WP_Query( $query_args );
		$posts = [];

		foreach ( $query->posts as $post ) {
			$excerpt = $post->post_excerpt
				?: wp_trim_words( wp_strip_all_tags( $post->post_content ), 25, '…' );

			$posts[] = [
				'id'       => $post->ID,
				'title'    => $post->post_title,
				'status'   => $post->post_status,
				'type'     => $post->post_type,
				'date'     => $post->post_date,
				'modified' => $post->post_modified,
				'url'      => get_permalink( $post->ID ) ?: '',
				'excerpt'  => $excerpt,
				'author'   => get_the_author_meta( 'display_name', $post->post_author ),
			];
		}

		$this->log( 'success', $this->elapsed_ms( $start ) );

		return [
			'posts' => $posts,
			'total' => $query->found_posts,
		];
	}
}
