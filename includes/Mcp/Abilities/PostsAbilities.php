<?php
declare(strict_types=1);

namespace McpForWordPress\Mcp\Abilities;

use McpForWordPress\Mcp\ToolRegistry;
use McpForWordPress\Support\Errors;
use McpForWordPress\Support\Pagination;
use McpForWordPress\Support\Schemas;

/**
 * MCP tools for WordPress posts.
 *
 * Tools: list, get, create, update, delete, list-revisions, restore-revision, autosave.
 */
final class PostsAbilities {

	public static function register_tools( ToolRegistry $r ): void {
		$r->register( 'mcp-for-wordpress/posts.list', [
			'description'         => __( 'List posts with filtering and pagination.', 'mcp-for-wordpress' ),
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'status'   => [ 'type' => 'string', 'enum' => [ 'publish', 'draft', 'pending', 'future', 'private', 'trash', 'any' ], 'default' => 'any' ],
					'search'   => [ 'type' => 'string' ],
					'author'   => [ 'type' => 'integer' ],
					'category' => [ 'type' => 'integer' ],
					'tag'      => [ 'type' => 'integer' ],
					'orderby'  => [ 'type' => 'string', 'enum' => [ 'date', 'title', 'modified', 'ID' ], 'default' => 'date' ],
					'order'    => [ 'type' => 'string', 'enum' => [ 'ASC', 'DESC' ], 'default' => 'DESC' ],
					'page'     => [ 'type' => 'integer', 'minimum' => 1, 'default' => 1 ],
					'per_page' => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ],
				],
			],
			'permission_callback' => static fn(): bool => current_user_can( 'edit_posts' ),
			'execute_callback'    => [ self::class, 'execute_list' ],
		] );

		$r->register( 'mcp-for-wordpress/posts.get', [
			'description'         => __( 'Retrieve a single post by ID.', 'mcp-for-wordpress' ),
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [ 'id' => [ 'type' => 'integer' ] ],
				'required'   => [ 'id' ],
			],
			'permission_callback' => static fn(): bool => current_user_can( 'edit_posts' ),
			'execute_callback'    => [ self::class, 'execute_get' ],
		] );

		$r->register( 'mcp-for-wordpress/posts.create', [
			'description'         => __( 'Create a new post.', 'mcp-for-wordpress' ),
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'title'      => [ 'type' => 'string' ],
					'content'    => [ 'type' => 'string' ],
					'excerpt'    => [ 'type' => 'string' ],
					'status'     => [ 'type' => 'string', 'enum' => [ 'publish', 'draft', 'pending', 'future', 'private' ], 'default' => 'draft' ],
					'categories' => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
					'tags'       => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
					'format'     => [ 'type' => 'string' ],
				],
				'required'   => [ 'title' ],
			],
			'permission_callback' => static fn(): bool => current_user_can( 'edit_posts' ),
			'execute_callback'    => [ self::class, 'execute_create' ],
		] );

		$r->register( 'mcp-for-wordpress/posts.update', [
			'description'         => __( 'Update an existing post.', 'mcp-for-wordpress' ),
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'id'         => [ 'type' => 'integer' ],
					'title'      => [ 'type' => 'string' ],
					'content'    => [ 'type' => 'string' ],
					'excerpt'    => [ 'type' => 'string' ],
					'status'     => [ 'type' => 'string', 'enum' => [ 'publish', 'draft', 'pending', 'future', 'private', 'trash' ] ],
					'categories' => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
					'tags'       => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
				],
				'required'   => [ 'id' ],
			],
			'permission_callback' => static fn(): bool => current_user_can( 'edit_posts' ),
			'execute_callback'    => [ self::class, 'execute_update' ],
		] );

		$r->register( 'mcp-for-wordpress/posts.delete', [
			'description'         => __( 'Move a post to trash or permanently delete it.', 'mcp-for-wordpress' ),
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'id'    => [ 'type' => 'integer' ],
					'force' => [ 'type' => 'boolean', 'default' => false ],
				],
				'required'   => [ 'id' ],
			],
			'permission_callback' => static fn(): bool => current_user_can( 'delete_posts' ),
			'execute_callback'    => [ self::class, 'execute_delete' ],
		] );

		$r->register( 'mcp-for-wordpress/posts.list-revisions', [
			'description'         => __( 'List revisions for a post.', 'mcp-for-wordpress' ),
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [ 'id' => [ 'type' => 'integer' ] ],
				'required'   => [ 'id' ],
			],
			'permission_callback' => static fn(): bool => current_user_can( 'edit_posts' ),
			'execute_callback'    => [ self::class, 'execute_list_revisions' ],
		] );

		$r->register( 'mcp-for-wordpress/posts.restore-revision', [
			'description'         => __( 'Restore a post to a specific revision.', 'mcp-for-wordpress' ),
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [ 'revision_id' => [ 'type' => 'integer' ] ],
				'required'   => [ 'revision_id' ],
			],
			'permission_callback' => static fn(): bool => current_user_can( 'edit_posts' ),
			'execute_callback'    => [ self::class, 'execute_restore_revision' ],
		] );

		$r->register( 'mcp-for-wordpress/posts.autosave', [
			'description'         => __( 'Create an autosave for a post.', 'mcp-for-wordpress' ),
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'id'      => [ 'type' => 'integer' ],
					'title'   => [ 'type' => 'string' ],
					'content' => [ 'type' => 'string' ],
					'excerpt' => [ 'type' => 'string' ],
				],
				'required'   => [ 'id' ],
			],
			'permission_callback' => static fn(): bool => current_user_can( 'edit_posts' ),
			'execute_callback'    => [ self::class, 'execute_autosave' ],
		] );
	}

	// --- Execute callbacks ---

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public static function execute_list( array $input ): array {
		$args = [
			'post_type'      => 'post',
			'post_status'    => $input['status'] ?? 'any',
			'posts_per_page' => $input['per_page'] ?? 20,
			'paged'          => $input['page'] ?? 1,
			'orderby'        => $input['orderby'] ?? 'date',
			'order'          => $input['order'] ?? 'DESC',
		];

		if ( ! empty( $input['search'] ) ) {
			$args['s'] = sanitize_text_field( $input['search'] );
		}
		if ( ! empty( $input['author'] ) ) {
			$args['author'] = absint( $input['author'] );
		}
		if ( ! empty( $input['category'] ) ) {
			$args['cat'] = absint( $input['category'] );
		}
		if ( ! empty( $input['tag'] ) ) {
			$args['tag_id'] = absint( $input['tag'] );
		}

		$query = new \WP_Query( $args );
		$items = array_map( [ self::class, 'format_post' ], $query->posts );

		return Pagination::response( $items, (int) $query->found_posts, $args['paged'], $args['posts_per_page'] );
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public static function execute_get( array $input ): array {
		$post = get_post( absint( $input['id'] ) );

		if ( ! $post || $post->post_type !== 'post' ) {
			return Errors::not_found( 'post', $input['id'] );
		}

		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return Errors::permission_denied( 'edit_post' );
		}

		return self::format_post( $post );
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public static function execute_create( array $input ): array {
		$post_data = [
			'post_type'    => 'post',
			'post_title'   => sanitize_text_field( $input['title'] ),
			'post_content' => wp_kses_post( $input['content'] ?? '' ),
			'post_excerpt' => sanitize_textarea_field( $input['excerpt'] ?? '' ),
			'post_status'  => sanitize_key( $input['status'] ?? 'draft' ),
		];

		if ( ! empty( $input['categories'] ) ) {
			$post_data['post_category'] = array_map( 'absint', $input['categories'] );
		}

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			return Errors::from_wp_error( $post_id );
		}

		if ( ! empty( $input['tags'] ) ) {
			wp_set_post_tags( $post_id, array_map( 'absint', $input['tags'] ) );
		}

		if ( ! empty( $input['format'] ) ) {
			set_post_format( $post_id, sanitize_key( $input['format'] ) );
		}

		return self::format_post( get_post( $post_id ) );
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public static function execute_update( array $input ): array {
		$post = get_post( absint( $input['id'] ) );

		if ( ! $post || $post->post_type !== 'post' ) {
			return Errors::not_found( 'post', $input['id'] );
		}

		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return Errors::permission_denied( 'edit_post' );
		}

		$post_data = [ 'ID' => $post->ID ];

		if ( isset( $input['title'] ) ) {
			$post_data['post_title'] = sanitize_text_field( $input['title'] );
		}
		if ( isset( $input['content'] ) ) {
			$post_data['post_content'] = wp_kses_post( $input['content'] );
		}
		if ( isset( $input['excerpt'] ) ) {
			$post_data['post_excerpt'] = sanitize_textarea_field( $input['excerpt'] );
		}
		if ( isset( $input['status'] ) ) {
			$post_data['post_status'] = sanitize_key( $input['status'] );
		}
		if ( isset( $input['categories'] ) ) {
			$post_data['post_category'] = array_map( 'absint', $input['categories'] );
		}

		$result = wp_update_post( $post_data, true );

		if ( is_wp_error( $result ) ) {
			return Errors::from_wp_error( $result );
		}

		if ( isset( $input['tags'] ) ) {
			wp_set_post_tags( $post->ID, array_map( 'absint', $input['tags'] ) );
		}

		return self::format_post( get_post( $post->ID ) );
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public static function execute_delete( array $input ): array {
		$post = get_post( absint( $input['id'] ) );

		if ( ! $post || $post->post_type !== 'post' ) {
			return Errors::not_found( 'post', $input['id'] );
		}

		if ( ! current_user_can( 'delete_post', $post->ID ) ) {
			return Errors::permission_denied( 'delete_post' );
		}

		$force  = ! empty( $input['force'] );
		$result = wp_delete_post( $post->ID, $force );

		return [ 'deleted' => $result !== false && $result !== null, 'id' => $post->ID ];
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<mixed>
	 */
	public static function execute_list_revisions( array $input ): array {
		$post = get_post( absint( $input['id'] ) );

		if ( ! $post || $post->post_type !== 'post' ) {
			return Errors::not_found( 'post', $input['id'] );
		}

		$revisions = wp_get_post_revisions( $post->ID );

		return array_values( array_map( static function ( $rev ) {
			return [
				'id'      => $rev->ID,
				'author'  => (int) $rev->post_author,
				'date'    => $rev->post_date_gmt,
				'title'   => $rev->post_title,
				'content' => $rev->post_content,
			];
		}, $revisions ) );
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public static function execute_restore_revision( array $input ): array {
		$revision_id = absint( $input['revision_id'] );
		$revision    = wp_get_post_revision( $revision_id );

		if ( ! $revision ) {
			return Errors::not_found( 'revision', $revision_id );
		}

		if ( ! current_user_can( 'edit_post', $revision->post_parent ) ) {
			return Errors::permission_denied( 'edit_post' );
		}

		$post_id = wp_restore_post_revision( $revision_id );

		if ( ! $post_id || is_wp_error( $post_id ) ) {
			return [ 'code' => 'restore_failed', 'message' => __( 'Failed to restore revision.', 'mcp-for-wordpress' ) ];
		}

		return self::format_post( get_post( $revision->post_parent ) );
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public static function execute_autosave( array $input ): array {
		$post = get_post( absint( $input['id'] ) );

		if ( ! $post || $post->post_type !== 'post' ) {
			return Errors::not_found( 'post', $input['id'] );
		}

		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return Errors::permission_denied( 'edit_post' );
		}

		$autosave_data = [
			'post_ID'      => $post->ID,
			'post_title'   => sanitize_text_field( $input['title'] ?? $post->post_title ),
			'post_content' => wp_kses_post( $input['content'] ?? $post->post_content ),
			'post_excerpt' => sanitize_textarea_field( $input['excerpt'] ?? $post->post_excerpt ),
			'post_type'    => 'post',
			'post_author'  => get_current_user_id(),
		];

		$autosave_id = wp_create_post_autosave( $autosave_data );

		if ( is_wp_error( $autosave_id ) ) {
			return Errors::from_wp_error( $autosave_id );
		}

		return self::format_post( get_post( $autosave_id ) );
	}

	/**
	 * Format a WP_Post into the standard post schema.
	 *
	 * @param \WP_Post $post The post object.
	 * @return array<string, mixed>
	 */
	public static function format_post( \WP_Post $post ): array {
		return [
			'id'             => $post->ID,
			'title'          => $post->post_title,
			'content'        => $post->post_content,
			'excerpt'        => $post->post_excerpt,
			'status'         => $post->post_status,
			'slug'           => $post->post_name,
			'author'         => (int) $post->post_author,
			'date'           => $post->post_date_gmt,
			'modified'       => $post->post_modified_gmt,
			'categories'     => wp_get_post_categories( $post->ID ),
			'tags'           => wp_get_post_tags( $post->ID, [ 'fields' => 'ids' ] ),
			'featured_media' => (int) get_post_thumbnail_id( $post->ID ),
			'format'         => get_post_format( $post->ID ) ?: 'standard',
			'link'           => get_permalink( $post->ID ),
		];
	}
}
