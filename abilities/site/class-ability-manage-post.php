<?php
/**
 * Ability: Manage Post
 *
 * Direct WordPress post management — create, update, publish, draft, trash.
 * No AI call — pure WordPress CRUD with permission checks.
 *
 * @package AbilityHub
 */

defined( 'ABSPATH' ) || exit;

class AbilityHub_Ability_Manage_Post extends AbilityHub_Ability_Base {

	protected string $name        = 'abilityhub/manage-post';
	protected string $label       = 'Manage post';
	protected string $description = 'Create, update, publish, draft, or trash WordPress posts and pages. Can also set post metadata and custom fields.';
	protected string $category    = 'site';
	protected bool   $cacheable   = false;

	protected array $input_schema = [
		'type'     => 'object',
		'required' => [ 'action' ],
		'properties' => [
			'action' => [
				'type'        => 'string',
				'enum'        => [ 'create', 'update', 'publish', 'draft', 'trash' ],
				'description' => 'Action to perform: create, update, publish (change status to published), draft (move to draft), or trash.',
			],
			'post_id' => [
				'type'        => 'integer',
				'description' => 'The post ID (required for update, publish, draft, trash).',
			],
			'title' => [
				'type'        => 'string',
				'description' => 'Post title.',
			],
			'content' => [
				'type'        => 'string',
				'description' => 'Post body content. HTML is allowed.',
			],
			'excerpt' => [
				'type'        => 'string',
				'description' => 'Short post excerpt.',
			],
			'post_type' => [
				'type'        => 'string',
				'description' => 'Post type for create action (default: post). E.g. post, page.',
			],
			'status' => [
				'type'        => 'string',
				'description' => 'Post status for create/update: publish, draft, pending, private.',
			],
			'meta' => [
				'type'        => 'object',
				'description' => 'Key-value object of post meta fields to set. E.g. {"_yoast_wpseo_metadesc": "...", "_custom_field": "value"}.',
			],
		],
	];

	protected array $output_schema = [
		'type'     => 'object',
		'required' => [ 'success', 'post_id' ],
		'properties' => [
			'success'     => [ 'type' => 'boolean' ],
			'post_id'     => [ 'type' => 'integer' ],
			'post_title'  => [ 'type' => 'string' ],
			'post_status' => [ 'type' => 'string' ],
			'post_url'    => [ 'type' => 'string' ],
			'message'     => [ 'type' => 'string' ],
		],
	];

	public function execute( array $input ): array|WP_Error {
		$start  = $this->start_timer();
		$action = sanitize_key( $input['action'] ?? '' );

		$allowed = [ 'create', 'update', 'publish', 'draft', 'trash' ];
		if ( ! in_array( $action, $allowed, true ) ) {
			return new WP_Error(
				'invalid_action',
				__( 'Invalid action. Must be: create, update, publish, draft, or trash.', 'abilityhub' )
			);
		}

		$result = match ( $action ) {
			'create'  => $this->do_create( $input ),
			'update'  => $this->do_update( $input ),
			'publish' => $this->do_set_status( $input, 'publish' ),
			'draft'   => $this->do_set_status( $input, 'draft' ),
			'trash'   => $this->do_trash( $input ),
		};

		if ( is_wp_error( $result ) ) {
			$this->log( 'error', $this->elapsed_ms( $start ) );
			return $result;
		}

		$this->log( 'success', $this->elapsed_ms( $start ) );
		return $result;
	}

	// -------------------------------------------------------------------------
	// Action handlers
	// -------------------------------------------------------------------------

	private function do_create( array $input ): array|WP_Error {
		if ( empty( $input['title'] ) && empty( $input['content'] ) ) {
			return new WP_Error(
				'missing_data',
				__( 'At least a title or content is required to create a post.', 'abilityhub' )
			);
		}

		$post_type = sanitize_key( $input['post_type'] ?? 'post' );
		if ( ! post_type_exists( $post_type ) ) {
			$post_type = 'post';
		}

		$status         = sanitize_key( $input['status'] ?? 'draft' );
		$valid_statuses = [ 'publish', 'draft', 'pending', 'private' ];
		if ( ! in_array( $status, $valid_statuses, true ) ) {
			$status = 'draft';
		}

		// Use manage_options cap to publish; edit_posts cap to draft.
		if ( 'publish' === $status && ! current_user_can( 'publish_posts' ) ) {
			$status = 'pending';
		}

		$post_data = [
			'post_title'   => sanitize_text_field( $input['title']   ?? '' ),
			'post_content' => wp_kses_post( $input['content']  ?? '' ),
			'post_excerpt' => sanitize_text_field( $input['excerpt']  ?? '' ),
			'post_status'  => $status,
			'post_type'    => $post_type,
		];

		if ( ! empty( $input['meta'] ) && is_array( $input['meta'] ) ) {
			$post_data['meta_input'] = $this->sanitize_meta( $input['meta'] );
		}

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		return $this->build_result( $post_id, __( 'Post created successfully.', 'abilityhub' ) );
	}

	private function do_update( array $input ): array|WP_Error {
		$post_id = absint( $input['post_id'] ?? 0 );
		if ( ! $post_id ) {
			return new WP_Error( 'missing_post_id', __( 'post_id is required for the update action.', 'abilityhub' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', __( 'Post not found.', 'abilityhub' ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'permission_denied', __( 'You do not have permission to edit this post.', 'abilityhub' ) );
		}

		$post_data = [ 'ID' => $post_id ];

		if ( isset( $input['title'] ) ) {
			$post_data['post_title'] = sanitize_text_field( $input['title'] );
		}
		if ( isset( $input['content'] ) ) {
			$post_data['post_content'] = wp_kses_post( $input['content'] );
		}
		if ( isset( $input['excerpt'] ) ) {
			$post_data['post_excerpt'] = sanitize_text_field( $input['excerpt'] );
		}
		if ( isset( $input['status'] ) ) {
			$valid = [ 'publish', 'draft', 'pending', 'private' ];
			$s     = sanitize_key( $input['status'] );
			if ( in_array( $s, $valid, true ) ) {
				$post_data['post_status'] = $s;
			}
		}

		$result = wp_update_post( $post_data, true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( ! empty( $input['meta'] ) && is_array( $input['meta'] ) ) {
			foreach ( $this->sanitize_meta( $input['meta'] ) as $key => $value ) {
				update_post_meta( $post_id, $key, $value );
			}
		}

		return $this->build_result( $post_id, __( 'Post updated successfully.', 'abilityhub' ) );
	}

	private function do_set_status( array $input, string $new_status ): array|WP_Error {
		$post_id = absint( $input['post_id'] ?? 0 );
		if ( ! $post_id ) {
			return new WP_Error(
				'missing_post_id',
				/* translators: %s: action name */
				sprintf( __( 'post_id is required for the %s action.', 'abilityhub' ), $new_status )
			);
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', __( 'Post not found.', 'abilityhub' ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'permission_denied', __( 'You do not have permission to edit this post.', 'abilityhub' ) );
		}

		if ( 'publish' === $new_status && ! current_user_can( 'publish_posts' ) ) {
			return new WP_Error( 'permission_denied', __( 'You do not have permission to publish posts.', 'abilityhub' ) );
		}

		$result = wp_update_post( [ 'ID' => $post_id, 'post_status' => $new_status ], true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$message = 'publish' === $new_status
			? __( 'Post published successfully.', 'abilityhub' )
			: __( 'Post moved to draft.', 'abilityhub' );

		return $this->build_result( $post_id, $message );
	}

	private function do_trash( array $input ): array|WP_Error {
		$post_id = absint( $input['post_id'] ?? 0 );
		if ( ! $post_id ) {
			return new WP_Error( 'missing_post_id', __( 'post_id is required for the trash action.', 'abilityhub' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', __( 'Post not found.', 'abilityhub' ) );
		}

		if ( ! current_user_can( 'delete_post', $post_id ) ) {
			return new WP_Error( 'permission_denied', __( 'You do not have permission to delete this post.', 'abilityhub' ) );
		}

		$title = $post->post_title;
		$trashed = wp_trash_post( $post_id );

		if ( ! $trashed ) {
			return new WP_Error( 'trash_failed', __( 'Failed to trash the post.', 'abilityhub' ) );
		}

		return [
			'success'     => true,
			'post_id'     => $post_id,
			'post_title'  => $title,
			'post_status' => 'trash',
			'post_url'    => '',
			'message'     => __( 'Post moved to trash.', 'abilityhub' ),
		];
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function build_result( int $post_id, string $message ): array {
		$post = get_post( $post_id );
		return [
			'success'     => true,
			'post_id'     => $post_id,
			'post_title'  => $post ? $post->post_title : '',
			'post_status' => $post ? $post->post_status : '',
			'post_url'    => (string) ( get_permalink( $post_id ) ?: '' ),
			'message'     => $message,
		];
	}

	/**
	 * Sanitize meta key-value pairs.
	 * Keys: alphanumeric, underscores, hyphens, max 255 chars.
	 * Values: sanitize_text_field (arrays of strings also supported).
	 *
	 * @param array $meta
	 * @return array
	 */
	private function sanitize_meta( array $meta ): array {
		$safe = [];
		foreach ( $meta as $key => $value ) {
			// Allow leading underscore (private meta) but sanitize the rest
			$clean_key = preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $key );
			if ( empty( $clean_key ) || strlen( $clean_key ) > 255 ) {
				continue;
			}
			if ( is_array( $value ) ) {
				$safe[ $clean_key ] = array_map( 'sanitize_text_field', $value );
			} else {
				$safe[ $clean_key ] = sanitize_text_field( (string) $value );
			}
		}
		return $safe;
	}
}
