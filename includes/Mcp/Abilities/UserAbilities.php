<?php
declare(strict_types=1);

namespace McpForWordPress\Mcp\Abilities;

use McpForWordPress\Mcp\ToolRegistry;
use McpForWordPress\Support\Errors;
use McpForWordPress\Support\Pagination;
use McpForWordPress\Support\Schemas;

/**
 * MCP tools for WordPress users.
 *
 * Tools: list, get, get-current, create, update, delete, list-app-passwords, change-role.
 */
final class UserAbilities {

	public static function register_tools( ToolRegistry $r ): void {
		$r->register( 'mcp-for-wordpress/users.list', [
			'description' => __( 'List users with pagination.', 'mcp-for-wordpress' ),
			'input_schema' => [
				'type' => 'object',
				'properties' => [
					'role'     => [ 'type' => 'string' ],
					'search'   => [ 'type' => 'string' ],
					'orderby'  => [ 'type' => 'string', 'enum' => [ 'ID', 'display_name', 'registered', 'login' ], 'default' => 'display_name' ],
					'page'     => [ 'type' => 'integer', 'minimum' => 1, 'default' => 1 ],
					'per_page' => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ],
				],
			],
			'permission_callback' => static fn(): bool => current_user_can( 'list_users' ),
			'execute_callback' => [ self::class, 'execute_list' ],
		] );

		$r->register( 'mcp-for-wordpress/users.get', [
			'description' => __( 'Retrieve a user by ID.', 'mcp-for-wordpress' ),
			'input_schema' => [ 'type' => 'object', 'properties' => [ 'id' => [ 'type' => 'integer' ] ], 'required' => [ 'id' ] ],
			'permission_callback' => static fn(): bool => current_user_can( 'list_users' ),
			'execute_callback' => [ self::class, 'execute_get' ],
		] );

		$r->register( 'mcp-for-wordpress/users.get-current', [
			'description' => __( 'Get the currently authenticated user.', 'mcp-for-wordpress' ),
			'input_schema' => [ 'type' => 'object', 'properties' => new \stdClass() ],
			'permission_callback' => static fn(): bool => current_user_can( 'read' ),
			'execute_callback' => [ self::class, 'execute_get_current' ],
		] );

		$r->register( 'mcp-for-wordpress/users.create', [
			'description' => __( 'Create a new user.', 'mcp-for-wordpress' ),
			'input_schema' => [
				'type' => 'object',
				'properties' => [
					'username' => [ 'type' => 'string' ], 'email' => [ 'type' => 'string', 'format' => 'email' ],
					'password' => [ 'type' => 'string' ], 'name' => [ 'type' => 'string' ],
					'role' => [ 'type' => 'string', 'default' => 'subscriber' ],
				],
				'required' => [ 'username', 'email', 'password' ],
			],
			'permission_callback' => static fn(): bool => current_user_can( 'create_users' ),
			'execute_callback' => [ self::class, 'execute_create' ],
		] );

		$r->register( 'mcp-for-wordpress/users.update', [
			'description' => __( 'Update user details.', 'mcp-for-wordpress' ),
			'input_schema' => [
				'type' => 'object',
				'properties' => [
					'id' => [ 'type' => 'integer' ], 'email' => [ 'type' => 'string' ], 'name' => [ 'type' => 'string' ],
					'first_name' => [ 'type' => 'string' ], 'last_name' => [ 'type' => 'string' ],
				],
				'required' => [ 'id' ],
			],
			'permission_callback' => static fn(): bool => current_user_can( 'edit_users' ),
			'execute_callback' => [ self::class, 'execute_update' ],
		] );

		$r->register( 'mcp-for-wordpress/users.delete', [
			'description' => __( 'Delete a user and reassign their content.', 'mcp-for-wordpress' ),
			'input_schema' => [
				'type' => 'object',
				'properties' => [ 'id' => [ 'type' => 'integer' ], 'reassign' => [ 'type' => 'integer' ] ],
				'required' => [ 'id' ],
			],
			'permission_callback' => static fn(): bool => current_user_can( 'delete_users' ),
			'execute_callback' => [ self::class, 'execute_delete' ],
		] );

		$r->register( 'mcp-for-wordpress/users.list-app-passwords', [
			'description' => __( 'List app passwords for a user.', 'mcp-for-wordpress' ),
			'input_schema' => [ 'type' => 'object', 'properties' => [ 'user_id' => [ 'type' => 'integer' ] ], 'required' => [ 'user_id' ] ],
			'permission_callback' => static fn(): bool => current_user_can( 'edit_users' ),
			'execute_callback' => [ self::class, 'execute_list_app_passwords' ],
		] );

		$r->register( 'mcp-for-wordpress/users.change-role', [
			'description' => __( 'Change a user\'s role.', 'mcp-for-wordpress' ),
			'input_schema' => [
				'type' => 'object',
				'properties' => [ 'id' => [ 'type' => 'integer' ], 'role' => [ 'type' => 'string' ] ],
				'required' => [ 'id', 'role' ],
			],
			'permission_callback' => static fn(): bool => current_user_can( 'promote_users' ),
			'execute_callback' => [ self::class, 'execute_change_role' ],
		] );
	}

	public static function execute_list( array $input ): array {
		$per_page = $input['per_page'] ?? 20;
		$page     = $input['page'] ?? 1;
		$args     = [ 'number' => $per_page, 'paged' => $page, 'orderby' => $input['orderby'] ?? 'display_name' ];
		if ( ! empty( $input['role'] ) )   { $args['role']   = sanitize_text_field( $input['role'] ); }
		if ( ! empty( $input['search'] ) ) { $args['search'] = '*' . sanitize_text_field( $input['search'] ) . '*'; }

		$query = new \WP_User_Query( $args );
		return Pagination::response( array_map( [ self::class, 'format_user' ], $query->get_results() ), (int) $query->get_total(), $page, $per_page );
	}

	public static function execute_get( array $input ): array {
		$user = get_userdata( absint( $input['id'] ) );
		if ( ! $user ) { return Errors::not_found( 'user', $input['id'] ); }
		return self::format_user( $user );
	}

	public static function execute_get_current( array $input ): array {
		return self::format_user( wp_get_current_user() );
	}

	public static function execute_create( array $input ): array {
		$user_id = wp_insert_user( [
			'user_login'   => sanitize_user( $input['username'] ),
			'user_email'   => sanitize_email( $input['email'] ),
			'user_pass'    => $input['password'],
			'display_name' => sanitize_text_field( $input['name'] ?? $input['username'] ),
			'role'         => sanitize_key( $input['role'] ?? 'subscriber' ),
		] );
		if ( is_wp_error( $user_id ) ) { return Errors::from_wp_error( $user_id ); }
		return self::format_user( get_userdata( $user_id ) );
	}

	public static function execute_update( array $input ): array {
		$user = get_userdata( absint( $input['id'] ) );
		if ( ! $user ) { return Errors::not_found( 'user', $input['id'] ); }
		$data = [ 'ID' => $user->ID ];
		if ( isset( $input['email'] ) )      { $data['user_email']   = sanitize_email( $input['email'] ); }
		if ( isset( $input['name'] ) )       { $data['display_name'] = sanitize_text_field( $input['name'] ); }
		if ( isset( $input['first_name'] ) ) { $data['first_name']   = sanitize_text_field( $input['first_name'] ); }
		if ( isset( $input['last_name'] ) )  { $data['last_name']    = sanitize_text_field( $input['last_name'] ); }
		$result = wp_update_user( $data );
		if ( is_wp_error( $result ) ) { return Errors::from_wp_error( $result ); }
		return self::format_user( get_userdata( $user->ID ) );
	}

	public static function execute_delete( array $input ): array {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		$reassign = isset( $input['reassign'] ) ? absint( $input['reassign'] ) : null;
		return [ 'deleted' => wp_delete_user( absint( $input['id'] ), $reassign ) ];
	}

	public static function execute_list_app_passwords( array $input ): array {
		$passwords = \WP_Application_Passwords::get_user_application_passwords( absint( $input['user_id'] ) );
		return array_map( static fn( $p ) => [
			'uuid'       => $p['uuid'],
			'name'       => $p['name'],
			'created'    => $p['created'],
			'last_used'  => $p['last_used'] ?? null,
			'last_ip'    => $p['last_ip'] ?? null,
		], $passwords );
	}

	public static function execute_change_role( array $input ): array {
		$user = get_userdata( absint( $input['id'] ) );
		if ( ! $user ) { return Errors::not_found( 'user', $input['id'] ); }
		$user->set_role( sanitize_key( $input['role'] ) );
		return self::format_user( get_userdata( $user->ID ) );
	}

	public static function format_user( \WP_User $user ): array {
		return [
			'id'         => $user->ID,
			'username'   => $user->user_login,
			'name'       => $user->display_name,
			'email'      => $user->user_email,
			'roles'      => array_values( $user->roles ),
			'registered' => $user->user_registered,
			'avatar_url' => get_avatar_url( $user->ID ),
		];
	}
}
