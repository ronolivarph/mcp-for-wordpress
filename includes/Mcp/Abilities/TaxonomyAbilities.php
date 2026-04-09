<?php
declare(strict_types=1);

namespace McpForWordPress\Mcp\Abilities;

use McpForWordPress\Mcp\ToolRegistry;
use McpForWordPress\Support\Errors;
use McpForWordPress\Support\Pagination;
use McpForWordPress\Support\Schemas;

/**
 * MCP tools for WordPress taxonomies (categories, tags, custom).
 *
 * Tools: list-categories, get-category, create-category, update-category, delete-category,
 *        list-tags, get-tag, create-tag, update-tag, delete-tag,
 *        list-taxonomies, get-terms-for-post.
 */
final class TaxonomyAbilities {

	public static function register_tools( ToolRegistry $r ): void {
		// Categories.
		self::register_term_tools( $r, 'category', 'categories', 'manage_categories' );
		// Tags.
		self::register_term_tools( $r, 'post_tag', 'tags', 'manage_categories' );

		// List taxonomies.
		$r->register( 'wp_taxonomies_list', [
			'description'         => __( 'List all registered taxonomies.', 'mcp-for-wordpress' ),
			'input_schema'        => [ 'type' => 'object', 'properties' => new \stdClass() ],
			'permission_callback' => static fn(): bool => current_user_can( 'read' ),
			'execute_callback'    => [ self::class, 'execute_list_taxonomies' ],
		] );

		// Get terms for a post.
		$r->register( 'wp_taxonomies_get-terms-for-post', [
			'description'         => __( 'Get all taxonomy terms assigned to a post.', 'mcp-for-wordpress' ),
			'input_schema'        => [
				'type' => 'object',
				'properties' => [
					'post_id'  => [ 'type' => 'integer' ],
					'taxonomy' => [ 'type' => 'string' ],
				],
				'required' => [ 'post_id' ],
			],
			'permission_callback' => static fn(): bool => current_user_can( 'read' ),
			'execute_callback'    => [ self::class, 'execute_get_terms_for_post' ],
		] );
	}

	private static function register_term_tools( ToolRegistry $r, string $taxonomy, string $slug, string $capability ): void {
		$singular = $taxonomy === 'post_tag' ? 'tag' : 'category';

		$r->register( "wp_{$slug}_list", [
			'description'         => sprintf( __( 'List %s with pagination.', 'mcp-for-wordpress' ), $slug ),
			'input_schema'        => [
				'type' => 'object',
				'properties' => [
					'search'   => [ 'type' => 'string' ],
					'parent'   => [ 'type' => 'integer' ],
					'page'     => [ 'type' => 'integer', 'minimum' => 1, 'default' => 1 ],
					'per_page' => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ],
					'orderby'  => [ 'type' => 'string', 'enum' => [ 'name', 'count', 'id' ], 'default' => 'name' ],
				],
			],
			'permission_callback' => static fn(): bool => current_user_can( 'read' ),
			'execute_callback'    => static fn( array $input ) => self::execute_list_terms( $taxonomy, $input ),
		] );

		$r->register( "wp_{$slug}_get", [
			'description'         => sprintf( __( 'Get a single %s by ID.', 'mcp-for-wordpress' ), $singular ),
			'input_schema'        => [ 'type' => 'object', 'properties' => [ 'id' => [ 'type' => 'integer' ] ], 'required' => [ 'id' ] ],
			'permission_callback' => static fn(): bool => current_user_can( 'read' ),
			'execute_callback'    => static fn( array $input ) => self::execute_get_term( $taxonomy, $input ),
		] );

		$r->register( "wp_{$slug}_create", [
			'description'         => sprintf( __( 'Create a new %s.', 'mcp-for-wordpress' ), $singular ),
			'input_schema'        => [
				'type' => 'object',
				'properties' => [
					'name'        => [ 'type' => 'string' ],
					'slug'        => [ 'type' => 'string' ],
					'description' => [ 'type' => 'string' ],
					'parent'      => [ 'type' => 'integer' ],
				],
				'required' => [ 'name' ],
			],
			'permission_callback' => static fn(): bool => current_user_can( $capability ),
			'execute_callback'    => static fn( array $input ) => self::execute_create_term( $taxonomy, $input ),
		] );

		$r->register( "wp_{$slug}_update", [
			'description'         => sprintf( __( 'Update a %s.', 'mcp-for-wordpress' ), $singular ),
			'input_schema'        => [
				'type' => 'object',
				'properties' => [
					'id' => [ 'type' => 'integer' ], 'name' => [ 'type' => 'string' ],
					'slug' => [ 'type' => 'string' ], 'description' => [ 'type' => 'string' ], 'parent' => [ 'type' => 'integer' ],
				],
				'required' => [ 'id' ],
			],
			'permission_callback' => static fn(): bool => current_user_can( $capability ),
			'execute_callback'    => static fn( array $input ) => self::execute_update_term( $taxonomy, $input ),
		] );

		$r->register( "wp_{$slug}_delete", [
			'description'         => sprintf( __( 'Delete a %s.', 'mcp-for-wordpress' ), $singular ),
			'input_schema'        => [ 'type' => 'object', 'properties' => [ 'id' => [ 'type' => 'integer' ] ], 'required' => [ 'id' ] ],
			'permission_callback' => static fn(): bool => current_user_can( $capability ),
			'execute_callback'    => static fn( array $input ) => self::execute_delete_term( $taxonomy, $input ),
		] );
	}

	// --- Execute callbacks ---

	public static function execute_list_terms( string $taxonomy, array $input ): array {
		$per_page = $input['per_page'] ?? 20;
		$page     = $input['page'] ?? 1;
		$args     = [
			'taxonomy'   => $taxonomy,
			'number'     => $per_page,
			'offset'     => ( $page - 1 ) * $per_page,
			'orderby'    => $input['orderby'] ?? 'name',
			'order'      => 'ASC',
			'hide_empty' => false,
		];
		if ( ! empty( $input['search'] ) ) { $args['search'] = sanitize_text_field( $input['search'] ); }
		if ( isset( $input['parent'] ) )   { $args['parent'] = absint( $input['parent'] ); }

		$terms = get_terms( $args );
		if ( is_wp_error( $terms ) ) { return Errors::from_wp_error( $terms ); }

		$count_args           = $args;
		$count_args['number'] = 0;
		$count_args['offset'] = 0;
		$count_args['fields'] = 'count';
		$total                = (int) get_terms( $count_args );

		return Pagination::response( array_map( [ self::class, 'format_term' ], $terms ), $total, $page, $per_page );
	}

	public static function execute_get_term( string $taxonomy, array $input ): array {
		$term = get_term( absint( $input['id'] ), $taxonomy );
		if ( ! $term || is_wp_error( $term ) ) { return Errors::not_found( $taxonomy, $input['id'] ); }
		return self::format_term( $term );
	}

	public static function execute_create_term( string $taxonomy, array $input ): array {
		$args = [];
		if ( isset( $input['slug'] ) )        { $args['slug']        = sanitize_title( $input['slug'] ); }
		if ( isset( $input['description'] ) ) { $args['description'] = sanitize_textarea_field( $input['description'] ); }
		if ( isset( $input['parent'] ) )      { $args['parent']      = absint( $input['parent'] ); }

		$result = wp_insert_term( sanitize_text_field( $input['name'] ), $taxonomy, $args );
		if ( is_wp_error( $result ) ) { return Errors::from_wp_error( $result ); }
		return self::format_term( get_term( $result['term_id'], $taxonomy ) );
	}

	public static function execute_update_term( string $taxonomy, array $input ): array {
		$term = get_term( absint( $input['id'] ), $taxonomy );
		if ( ! $term || is_wp_error( $term ) ) { return Errors::not_found( $taxonomy, $input['id'] ); }

		$args = [];
		if ( isset( $input['name'] ) )        { $args['name']        = sanitize_text_field( $input['name'] ); }
		if ( isset( $input['slug'] ) )        { $args['slug']        = sanitize_title( $input['slug'] ); }
		if ( isset( $input['description'] ) ) { $args['description'] = sanitize_textarea_field( $input['description'] ); }
		if ( isset( $input['parent'] ) )      { $args['parent']      = absint( $input['parent'] ); }

		$result = wp_update_term( $term->term_id, $taxonomy, $args );
		if ( is_wp_error( $result ) ) { return Errors::from_wp_error( $result ); }
		return self::format_term( get_term( $result['term_id'], $taxonomy ) );
	}

	public static function execute_delete_term( string $taxonomy, array $input ): array {
		$result = wp_delete_term( absint( $input['id'] ), $taxonomy );
		if ( is_wp_error( $result ) ) { return Errors::from_wp_error( $result ); }
		return [ 'deleted' => (bool) $result ];
	}

	public static function execute_list_taxonomies( array $input ): array {
		$taxonomies = get_taxonomies( [ 'public' => true ], 'objects' );
		return array_values( array_map( static fn( $tax ) => [
			'name'        => $tax->name,
			'label'       => $tax->label,
			'description' => $tax->description,
			'hierarchical' => $tax->hierarchical,
			'post_types'  => $tax->object_type,
		], $taxonomies ) );
	}

	public static function execute_get_terms_for_post( array $input ): array {
		$post_id  = absint( $input['post_id'] );
		$taxonomy = ! empty( $input['taxonomy'] ) ? sanitize_key( $input['taxonomy'] ) : null;

		if ( $taxonomy ) {
			$terms = wp_get_post_terms( $post_id, $taxonomy );
		} else {
			$terms      = [];
			$taxonomies = get_object_taxonomies( get_post_type( $post_id ) );
			foreach ( $taxonomies as $tax ) {
				$tax_terms = wp_get_post_terms( $post_id, $tax );
				if ( ! is_wp_error( $tax_terms ) ) {
					$terms = array_merge( $terms, $tax_terms );
				}
			}
		}

		if ( is_wp_error( $terms ) ) { return Errors::from_wp_error( $terms ); }
		return array_map( [ self::class, 'format_term' ], $terms );
	}

	/**
	 * @param \WP_Term $term
	 * @return array<string, mixed>
	 */
	public static function format_term( \WP_Term $term ): array {
		return [
			'id'          => $term->term_id,
			'name'        => $term->name,
			'slug'        => $term->slug,
			'description' => $term->description,
			'parent'      => $term->parent,
			'count'       => $term->count,
			'taxonomy'    => $term->taxonomy,
		];
	}
}
