<?php
declare(strict_types=1);

namespace McpForWordPress\Mcp\Abilities;

use McpForWordPress\Mcp\ToolRegistry;
use McpForWordPress\Support\Errors;
use McpForWordPress\Support\Pagination;
use McpForWordPress\Support\Schemas;

/**
 * MCP tools for WordPress pages.
 *
 * Tools: list, get, create, update, delete, list-revisions, restore-revision, autosave.
 */
final class PagesAbilities {

	public static function register_tools( ToolRegistry $r ): void {
		$r->register( 'wp_pages_list', [
			'description'         => __( 'List pages with filtering and pagination.', 'mcp-for-wordpress' ),
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'status'   => [ 'type' => 'string', 'enum' => [ 'publish', 'draft', 'pending', 'private', 'trash', 'any' ], 'default' => 'any' ],
					'search'   => [ 'type' => 'string' ],
					'parent'   => [ 'type' => 'integer' ],
					'orderby'  => [ 'type' => 'string', 'enum' => [ 'date', 'title', 'modified', 'menu_order' ], 'default' => 'menu_order' ],
					'order'    => [ 'type' => 'string', 'enum' => [ 'ASC', 'DESC' ], 'default' => 'ASC' ],
					'page'     => [ 'type' => 'integer', 'minimum' => 1, 'default' => 1 ],
					'per_page' => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ],
				],
			],
			'permission_callback' => static fn(): bool => current_user_can( 'edit_pages' ),
			'execute_callback'    => [ self::class, 'execute_list' ],
		] );

		$r->register( 'wp_pages_get', [
			'description'         => __( 'Retrieve a single page by ID.', 'mcp-for-wordpress' ),
			'input_schema'        => [
				'type' => 'object', 'properties' => [ 'id' => [ 'type' => 'integer' ] ], 'required' => [ 'id' ],
			],
			'permission_callback' => static fn(): bool => current_user_can( 'edit_pages' ),
			'execute_callback'    => [ self::class, 'execute_get' ],
		] );

		$r->register( 'wp_pages_create', [
			'description'         => __( 'Create a new page.', 'mcp-for-wordpress' ),
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'title'      => [ 'type' => 'string' ],
					'content'    => [ 'type' => 'string' ],
					'status'     => [ 'type' => 'string', 'enum' => [ 'publish', 'draft', 'pending', 'private' ], 'default' => 'draft' ],
					'parent'     => [ 'type' => 'integer', 'default' => 0 ],
					'menu_order' => [ 'type' => 'integer', 'default' => 0 ],
					'template'   => [ 'type' => 'string' ],
				],
				'required'   => [ 'title' ],
			],
			'permission_callback' => static fn(): bool => current_user_can( 'edit_pages' ),
			'execute_callback'    => [ self::class, 'execute_create' ],
		] );

		$r->register( 'wp_pages_update', [
			'description'         => __( 'Update an existing page.', 'mcp-for-wordpress' ),
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'id'         => [ 'type' => 'integer' ],
					'title'      => [ 'type' => 'string' ],
					'content'    => [ 'type' => 'string' ],
					'status'     => [ 'type' => 'string' ],
					'parent'     => [ 'type' => 'integer' ],
					'menu_order' => [ 'type' => 'integer' ],
					'template'   => [ 'type' => 'string' ],
				],
				'required'   => [ 'id' ],
			],
			'permission_callback' => static fn(): bool => current_user_can( 'edit_pages' ),
			'execute_callback'    => [ self::class, 'execute_update' ],
		] );

		$r->register( 'wp_pages_delete', [
			'description'         => __( 'Move a page to trash or permanently delete it.', 'mcp-for-wordpress' ),
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'id'    => [ 'type' => 'integer' ],
					'force' => [ 'type' => 'boolean', 'default' => false ],
				],
				'required'   => [ 'id' ],
			],
			'permission_callback' => static fn(): bool => current_user_can( 'delete_pages' ),
			'execute_callback'    => [ self::class, 'execute_delete' ],
		] );

		$r->register( 'wp_pages_list-revisions', [
			'description'         => __( 'List revisions for a page.', 'mcp-for-wordpress' ),
			'input_schema'        => [ 'type' => 'object', 'properties' => [ 'id' => [ 'type' => 'integer' ] ], 'required' => [ 'id' ] ],
			'permission_callback' => static fn(): bool => current_user_can( 'edit_pages' ),
			'execute_callback'    => [ self::class, 'execute_list_revisions' ],
		] );

		$r->register( 'wp_pages_restore-revision', [
			'description'         => __( 'Restore a page to a specific revision.', 'mcp-for-wordpress' ),
			'input_schema'        => [ 'type' => 'object', 'properties' => [ 'revision_id' => [ 'type' => 'integer' ] ], 'required' => [ 'revision_id' ] ],
			'permission_callback' => static fn(): bool => current_user_can( 'edit_pages' ),
			'execute_callback'    => [ self::class, 'execute_restore_revision' ],
		] );

		$r->register( 'wp_pages_autosave', [
			'description'         => __( 'Create an autosave for a page.', 'mcp-for-wordpress' ),
			'input_schema'        => [
				'type' => 'object',
				'properties' => [ 'id' => [ 'type' => 'integer' ], 'title' => [ 'type' => 'string' ], 'content' => [ 'type' => 'string' ] ],
				'required' => [ 'id' ],
			],
			'permission_callback' => static fn(): bool => current_user_can( 'edit_pages' ),
			'execute_callback'    => [ self::class, 'execute_autosave' ],
		] );
	}

	// --- Execute callbacks --- (delegate to PostsAbilities with post_type=page)

	public static function execute_list( array $input ): array {
		$args = [
			'post_type'      => 'page',
			'post_status'    => $input['status'] ?? 'any',
			'posts_per_page' => $input['per_page'] ?? 20,
			'paged'          => $input['page'] ?? 1,
			'orderby'        => $input['orderby'] ?? 'menu_order',
			'order'          => $input['order'] ?? 'ASC',
		];
		if ( ! empty( $input['search'] ) ) { $args['s'] = sanitize_text_field( $input['search'] ); }
		if ( isset( $input['parent'] ) )    { $args['post_parent'] = absint( $input['parent'] ); }

		$query = new \WP_Query( $args );
		$items = array_map( [ PostsAbilities::class, 'format_post' ], $query->posts );
		return Pagination::response( $items, (int) $query->found_posts, $args['paged'], $args['posts_per_page'] );
	}

	public static function execute_get( array $input ): array {
		$post = get_post( absint( $input['id'] ) );
		if ( ! $post || $post->post_type !== 'page' ) { return Errors::not_found( 'page', $input['id'] ); }
		if ( ! current_user_can( 'edit_page', $post->ID ) ) { return Errors::permission_denied( 'edit_page' ); }
		return PostsAbilities::format_post( $post );
	}

	public static function execute_create( array $input ): array {
		$data = [
			'post_type'    => 'page',
			'post_title'   => sanitize_text_field( $input['title'] ),
			'post_content' => wp_kses_post( $input['content'] ?? '' ),
			'post_status'  => sanitize_key( $input['status'] ?? 'draft' ),
			'post_parent'  => absint( $input['parent'] ?? 0 ),
			'menu_order'   => absint( $input['menu_order'] ?? 0 ),
		];
		$post_id = wp_insert_post( $data, true );
		if ( is_wp_error( $post_id ) ) { return Errors::from_wp_error( $post_id ); }
		if ( ! empty( $input['template'] ) ) { update_post_meta( $post_id, '_wp_page_template', sanitize_text_field( $input['template'] ) ); }
		return PostsAbilities::format_post( get_post( $post_id ) );
	}

	public static function execute_update( array $input ): array {
		$post = get_post( absint( $input['id'] ) );
		if ( ! $post || $post->post_type !== 'page' ) { return Errors::not_found( 'page', $input['id'] ); }
		if ( ! current_user_can( 'edit_page', $post->ID ) ) { return Errors::permission_denied( 'edit_page' ); }

		$data = [ 'ID' => $post->ID ];
		if ( isset( $input['title'] ) )      { $data['post_title']   = sanitize_text_field( $input['title'] ); }
		if ( isset( $input['content'] ) )    { $data['post_content'] = wp_kses_post( $input['content'] ); }
		if ( isset( $input['status'] ) )     { $data['post_status']  = sanitize_key( $input['status'] ); }
		if ( isset( $input['parent'] ) )     { $data['post_parent']  = absint( $input['parent'] ); }
		if ( isset( $input['menu_order'] ) ) { $data['menu_order']   = absint( $input['menu_order'] ); }

		$result = wp_update_post( $data, true );
		if ( is_wp_error( $result ) ) { return Errors::from_wp_error( $result ); }
		if ( isset( $input['template'] ) ) { update_post_meta( $post->ID, '_wp_page_template', sanitize_text_field( $input['template'] ) ); }
		return PostsAbilities::format_post( get_post( $post->ID ) );
	}

	public static function execute_delete( array $input ): array {
		$post = get_post( absint( $input['id'] ) );
		if ( ! $post || $post->post_type !== 'page' ) { return Errors::not_found( 'page', $input['id'] ); }
		if ( ! current_user_can( 'delete_page', $post->ID ) ) { return Errors::permission_denied( 'delete_page' ); }
		$result = wp_delete_post( $post->ID, ! empty( $input['force'] ) );
		return [ 'deleted' => $result !== false && $result !== null, 'id' => $post->ID ];
	}

	public static function execute_list_revisions( array $input ): array {
		return PostsAbilities::execute_list_revisions( $input );
	}

	public static function execute_restore_revision( array $input ): array {
		return PostsAbilities::execute_restore_revision( $input );
	}

	public static function execute_autosave( array $input ): array {
		$post = get_post( absint( $input['id'] ) );
		if ( ! $post || $post->post_type !== 'page' ) { return Errors::not_found( 'page', $input['id'] ); }
		if ( ! current_user_can( 'edit_page', $post->ID ) ) { return Errors::permission_denied( 'edit_page' ); }
		$autosave_data = [
			'post_ID' => $post->ID, 'post_title' => sanitize_text_field( $input['title'] ?? $post->post_title ),
			'post_content' => wp_kses_post( $input['content'] ?? $post->post_content ), 'post_type' => 'page', 'post_author' => get_current_user_id(),
		];
		$id = wp_create_post_autosave( $autosave_data );
		if ( is_wp_error( $id ) ) { return Errors::from_wp_error( $id ); }
		return PostsAbilities::format_post( get_post( $id ) );
	}
}
