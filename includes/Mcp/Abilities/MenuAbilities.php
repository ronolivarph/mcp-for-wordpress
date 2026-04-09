<?php
declare(strict_types=1);

namespace McpForWordPress\Mcp\Abilities;

use McpForWordPress\Support\Errors;
use McpForWordPress\Support\Schemas;

/**
 * MCP tools for WordPress nav menus.
 *
 * Tools: list, get, create, update, delete, list-items, add-item, update-item, remove-item, assign-location.
 */
final class MenuAbilities {

	public static function register(): void {
		add_action( 'wp_abilities_api_init', [ self::class, 'on_init' ] );
	}

	public static function on_init(): void {
		wp_register_ability( 'mcp-for-wordpress/menus.list', [
			'label' => __( 'List Menus', 'mcp-for-wordpress' ), 'description' => __( 'List all nav menus.', 'mcp-for-wordpress' ),
			'input_schema' => [ 'type' => 'object', 'properties' => new \stdClass() ],
			'output_schema' => [ 'type' => 'array', 'items' => [ 'type' => 'object' ] ],
			'permission_callback' => static fn(): bool => current_user_can( 'edit_theme_options' ),
			'execute_callback' => [ self::class, 'execute_list' ], 'meta' => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		] );

		wp_register_ability( 'mcp-for-wordpress/menus.get', [
			'label' => __( 'Get Menu', 'mcp-for-wordpress' ), 'description' => __( 'Get a menu by ID with its items.', 'mcp-for-wordpress' ),
			'input_schema' => [ 'type' => 'object', 'properties' => [ 'id' => [ 'type' => 'integer' ] ], 'required' => [ 'id' ] ],
			'output_schema' => [ 'type' => 'object' ],
			'permission_callback' => static fn(): bool => current_user_can( 'edit_theme_options' ),
			'execute_callback' => [ self::class, 'execute_get' ], 'meta' => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		] );

		wp_register_ability( 'mcp-for-wordpress/menus.create', [
			'label' => __( 'Create Menu', 'mcp-for-wordpress' ), 'description' => __( 'Create a new nav menu.', 'mcp-for-wordpress' ),
			'input_schema' => [ 'type' => 'object', 'properties' => [ 'name' => [ 'type' => 'string' ] ], 'required' => [ 'name' ] ],
			'output_schema' => [ 'type' => 'object' ],
			'permission_callback' => static fn(): bool => current_user_can( 'edit_theme_options' ),
			'execute_callback' => [ self::class, 'execute_create' ], 'meta' => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		] );

		wp_register_ability( 'mcp-for-wordpress/menus.update', [
			'label' => __( 'Update Menu', 'mcp-for-wordpress' ), 'description' => __( 'Rename a nav menu.', 'mcp-for-wordpress' ),
			'input_schema' => [ 'type' => 'object', 'properties' => [ 'id' => [ 'type' => 'integer' ], 'name' => [ 'type' => 'string' ] ], 'required' => [ 'id', 'name' ] ],
			'output_schema' => [ 'type' => 'object' ],
			'permission_callback' => static fn(): bool => current_user_can( 'edit_theme_options' ),
			'execute_callback' => [ self::class, 'execute_update' ], 'meta' => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		] );

		wp_register_ability( 'mcp-for-wordpress/menus.delete', [
			'label' => __( 'Delete Menu', 'mcp-for-wordpress' ), 'description' => __( 'Delete a nav menu.', 'mcp-for-wordpress' ),
			'input_schema' => [ 'type' => 'object', 'properties' => [ 'id' => [ 'type' => 'integer' ] ], 'required' => [ 'id' ] ],
			'output_schema' => [ 'type' => 'object', 'properties' => [ 'deleted' => [ 'type' => 'boolean' ] ] ],
			'permission_callback' => static fn(): bool => current_user_can( 'edit_theme_options' ),
			'execute_callback' => [ self::class, 'execute_delete' ], 'meta' => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		] );

		wp_register_ability( 'mcp-for-wordpress/menus.list-items', [
			'label' => __( 'List Menu Items', 'mcp-for-wordpress' ), 'description' => __( 'List items in a menu.', 'mcp-for-wordpress' ),
			'input_schema' => [ 'type' => 'object', 'properties' => [ 'menu_id' => [ 'type' => 'integer' ] ], 'required' => [ 'menu_id' ] ],
			'output_schema' => [ 'type' => 'array', 'items' => Schemas::menu_item() ],
			'permission_callback' => static fn(): bool => current_user_can( 'edit_theme_options' ),
			'execute_callback' => [ self::class, 'execute_list_items' ], 'meta' => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		] );

		wp_register_ability( 'mcp-for-wordpress/menus.add-item', [
			'label' => __( 'Add Menu Item', 'mcp-for-wordpress' ), 'description' => __( 'Add an item to a nav menu.', 'mcp-for-wordpress' ),
			'input_schema' => [
				'type' => 'object',
				'properties' => [
					'menu_id' => [ 'type' => 'integer' ], 'title' => [ 'type' => 'string' ], 'url' => [ 'type' => 'string' ],
					'object_type' => [ 'type' => 'string' ], 'object_id' => [ 'type' => 'integer' ],
					'parent' => [ 'type' => 'integer', 'default' => 0 ], 'position' => [ 'type' => 'integer' ],
				],
				'required' => [ 'menu_id', 'title' ],
			],
			'output_schema' => Schemas::menu_item(),
			'permission_callback' => static fn(): bool => current_user_can( 'edit_theme_options' ),
			'execute_callback' => [ self::class, 'execute_add_item' ], 'meta' => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		] );

		wp_register_ability( 'mcp-for-wordpress/menus.update-item', [
			'label' => __( 'Update Menu Item', 'mcp-for-wordpress' ), 'description' => __( 'Update a menu item.', 'mcp-for-wordpress' ),
			'input_schema' => [
				'type' => 'object',
				'properties' => [
					'item_id' => [ 'type' => 'integer' ], 'title' => [ 'type' => 'string' ], 'url' => [ 'type' => 'string' ],
					'parent' => [ 'type' => 'integer' ], 'position' => [ 'type' => 'integer' ],
				],
				'required' => [ 'item_id' ],
			],
			'output_schema' => Schemas::menu_item(),
			'permission_callback' => static fn(): bool => current_user_can( 'edit_theme_options' ),
			'execute_callback' => [ self::class, 'execute_update_item' ], 'meta' => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		] );

		wp_register_ability( 'mcp-for-wordpress/menus.remove-item', [
			'label' => __( 'Remove Menu Item', 'mcp-for-wordpress' ), 'description' => __( 'Remove an item from a menu.', 'mcp-for-wordpress' ),
			'input_schema' => [ 'type' => 'object', 'properties' => [ 'item_id' => [ 'type' => 'integer' ] ], 'required' => [ 'item_id' ] ],
			'output_schema' => [ 'type' => 'object', 'properties' => [ 'deleted' => [ 'type' => 'boolean' ] ] ],
			'permission_callback' => static fn(): bool => current_user_can( 'edit_theme_options' ),
			'execute_callback' => [ self::class, 'execute_remove_item' ], 'meta' => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		] );

		wp_register_ability( 'mcp-for-wordpress/menus.assign-location', [
			'label' => __( 'Assign Menu Location', 'mcp-for-wordpress' ), 'description' => __( 'Assign a menu to a theme location.', 'mcp-for-wordpress' ),
			'input_schema' => [
				'type' => 'object',
				'properties' => [ 'menu_id' => [ 'type' => 'integer' ], 'location' => [ 'type' => 'string' ] ],
				'required' => [ 'menu_id', 'location' ],
			],
			'output_schema' => [ 'type' => 'object', 'properties' => [ 'success' => [ 'type' => 'boolean' ] ] ],
			'permission_callback' => static fn(): bool => current_user_can( 'edit_theme_options' ),
			'execute_callback' => [ self::class, 'execute_assign_location' ], 'meta' => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		] );
	}

	public static function execute_list( array $input ): array {
		$menus = wp_get_nav_menus();
		return array_map( static fn( $m ) => [ 'id' => $m->term_id, 'name' => $m->name, 'slug' => $m->slug, 'count' => $m->count ], $menus );
	}

	public static function execute_get( array $input ): array {
		$menu = wp_get_nav_menu_object( absint( $input['id'] ) );
		if ( ! $menu ) { return Errors::not_found( 'menu', $input['id'] ); }
		$items = wp_get_nav_menu_items( $menu->term_id );
		return [
			'id' => $menu->term_id, 'name' => $menu->name, 'slug' => $menu->slug,
			'items' => $items ? array_map( [ self::class, 'format_item' ], $items ) : [],
		];
	}

	public static function execute_create( array $input ): array {
		$result = wp_create_nav_menu( sanitize_text_field( $input['name'] ) );
		if ( is_wp_error( $result ) ) { return Errors::from_wp_error( $result ); }
		$menu = wp_get_nav_menu_object( $result );
		return [ 'id' => $menu->term_id, 'name' => $menu->name, 'slug' => $menu->slug ];
	}

	public static function execute_update( array $input ): array {
		$result = wp_update_nav_menu_object( absint( $input['id'] ), [ 'menu-name' => sanitize_text_field( $input['name'] ) ] );
		if ( is_wp_error( $result ) ) { return Errors::from_wp_error( $result ); }
		$menu = wp_get_nav_menu_object( $result );
		return [ 'id' => $menu->term_id, 'name' => $menu->name, 'slug' => $menu->slug ];
	}

	public static function execute_delete( array $input ): array {
		$result = wp_delete_nav_menu( absint( $input['id'] ) );
		return [ 'deleted' => is_wp_error( $result ) ? false : (bool) $result ];
	}

	public static function execute_list_items( array $input ): array {
		$items = wp_get_nav_menu_items( absint( $input['menu_id'] ) );
		if ( ! $items ) { return []; }
		return array_map( [ self::class, 'format_item' ], $items );
	}

	public static function execute_add_item( array $input ): array {
		$item_data = [
			'menu-item-title'     => sanitize_text_field( $input['title'] ),
			'menu-item-url'       => esc_url_raw( $input['url'] ?? '' ),
			'menu-item-status'    => 'publish',
			'menu-item-parent-id' => absint( $input['parent'] ?? 0 ),
			'menu-item-position'  => absint( $input['position'] ?? 0 ),
		];
		if ( ! empty( $input['object_type'] ) ) {
			$item_data['menu-item-type']      = sanitize_key( $input['object_type'] );
			$item_data['menu-item-object-id'] = absint( $input['object_id'] ?? 0 );
		} else {
			$item_data['menu-item-type'] = 'custom';
		}

		$item_id = wp_update_nav_menu_item( absint( $input['menu_id'] ), 0, $item_data );
		if ( is_wp_error( $item_id ) ) { return Errors::from_wp_error( $item_id ); }
		return self::format_item( wp_setup_nav_menu_item( get_post( $item_id ) ) );
	}

	public static function execute_update_item( array $input ): array {
		$item = get_post( absint( $input['item_id'] ) );
		if ( ! $item || $item->post_type !== 'nav_menu_item' ) { return Errors::not_found( 'menu item', $input['item_id'] ); }

		$menus = wp_get_object_terms( $item->ID, 'nav_menu' );
		$menu_id = ! empty( $menus ) ? $menus[0]->term_id : 0;

		$item_data = [];
		if ( isset( $input['title'] ) )    { $item_data['menu-item-title']     = sanitize_text_field( $input['title'] ); }
		if ( isset( $input['url'] ) )      { $item_data['menu-item-url']       = esc_url_raw( $input['url'] ); }
		if ( isset( $input['parent'] ) )   { $item_data['menu-item-parent-id'] = absint( $input['parent'] ); }
		if ( isset( $input['position'] ) ) { $item_data['menu-item-position']  = absint( $input['position'] ); }

		$result = wp_update_nav_menu_item( $menu_id, $item->ID, $item_data );
		if ( is_wp_error( $result ) ) { return Errors::from_wp_error( $result ); }
		return self::format_item( wp_setup_nav_menu_item( get_post( $item->ID ) ) );
	}

	public static function execute_remove_item( array $input ): array {
		return [ 'deleted' => (bool) wp_delete_post( absint( $input['item_id'] ), true ) ];
	}

	public static function execute_assign_location( array $input ): array {
		$locations = get_nav_menu_locations();
		$locations[ sanitize_key( $input['location'] ) ] = absint( $input['menu_id'] );
		set_theme_mod( 'nav_menu_locations', $locations );
		return [ 'success' => true ];
	}

	private static function format_item( $item ): array {
		return [
			'id'        => (int) $item->ID,
			'title'     => $item->title ?? $item->post_title,
			'url'       => $item->url ?? '',
			'menu_id'   => 0, // populated by context
			'parent'    => (int) ( $item->menu_item_parent ?? $item->post_parent ),
			'position'  => (int) ( $item->menu_order ?? 0 ),
			'type'      => $item->type ?? $item->post_type,
			'object'    => $item->object ?? '',
			'object_id' => (int) ( $item->object_id ?? 0 ),
		];
	}
}
