<?php
declare(strict_types=1);

namespace McpForWordPress\Mcp\Abilities;

use McpForWordPress\Mcp\ToolRegistry;
use McpForWordPress\Support\Errors;

/**
 * MCP tools for WordPress site settings and configuration.
 *
 * Tools: get-settings, update-setting, list-post-types, list-post-statuses,
 *        list-plugins, list-themes, get-active-theme, list-widgets, list-sidebars, get-site-info.
 */
final class SettingsAbilities {

	public static function register_tools( ToolRegistry $r ): void {
		$ro_tools = [
			'settings.get'           => [ 'Get core WordPress settings.', 'manage_options' ],
			'settings.list-post-types'  => [ 'List registered post types.', 'read' ],
			'settings.list-post-statuses' => [ 'List registered post statuses.', 'read' ],
			'settings.list-plugins'  => [ 'List installed plugins.', 'activate_plugins' ],
			'settings.list-themes'   => [ 'List installed themes.', 'switch_themes' ],
			'settings.get-active-theme' => [ 'Get the active theme details.', 'read' ],
			'settings.list-widgets'  => [ 'List registered widgets.', 'edit_theme_options' ],
			'settings.list-sidebars' => [ 'List registered sidebars.', 'edit_theme_options' ],
			'settings.get-site-info' => [ 'Get general site information.', 'read' ],
		];

		foreach ( $ro_tools as $slug => [ $desc, $cap ] ) {
			$method     = 'execute_' . str_replace( [ '.', '-' ], '_', $slug );
			$safe_slug  = str_replace( [ '.', '-' ], '_', $slug );
			$r->register( "wp_{$safe_slug}", [
				'description'         => __( $desc, 'mcp-for-wordpress' ), // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
				'input_schema'        => [ 'type' => 'object', 'properties' => new \stdClass() ],
				'permission_callback' => static fn(): bool => current_user_can( $cap ),
				'execute_callback'    => [ self::class, $method ],
			] );
		}

		$r->register( 'wp_settings_update', [
			'description' => __( 'Update a WordPress option.', 'mcp-for-wordpress' ),
			'input_schema' => [
				'type' => 'object',
				'properties' => [
					'option' => [ 'type' => 'string' ], 'value' => [],
				],
				'required' => [ 'option', 'value' ],
			],
			'permission_callback' => static fn(): bool => current_user_can( 'manage_options' ),
			'execute_callback' => [ self::class, 'execute_settings_update' ],
		] );
	}

	public static function execute_settings_get( array $input ): array {
		$settings = [
			'blogname', 'blogdescription', 'siteurl', 'home', 'admin_email',
			'timezone_string', 'date_format', 'time_format', 'posts_per_page',
			'permalink_structure', 'default_comment_status', 'WPLANG',
		];
		$result = [];
		foreach ( $settings as $key ) {
			$result[ $key ] = get_option( $key );
		}
		return $result;
	}

	public static function execute_settings_update( array $input ): array {
		$allowed = [
			'blogname', 'blogdescription', 'admin_email', 'timezone_string',
			'date_format', 'time_format', 'posts_per_page', 'permalink_structure',
			'default_comment_status',
		];
		$option = sanitize_key( $input['option'] );
		if ( ! in_array( $option, $allowed, true ) ) {
			return [ 'code' => 'forbidden_option', 'message' => __( 'This option cannot be modified via MCP.', 'mcp-for-wordpress' ) ];
		}
		return [ 'updated' => update_option( $option, sanitize_text_field( (string) $input['value'] ) ) ];
	}

	public static function execute_settings_list_post_types( array $input ): array {
		$types = get_post_types( [ 'public' => true ], 'objects' );
		return array_values( array_map( static fn( $t ) => [
			'name' => $t->name, 'label' => $t->label, 'description' => $t->description,
			'hierarchical' => $t->hierarchical, 'has_archive' => $t->has_archive,
		], $types ) );
	}

	public static function execute_settings_list_post_statuses( array $input ): array {
		$statuses = get_post_stati( [], 'objects' );
		return array_values( array_map( static fn( $s ) => [
			'name' => $s->name, 'label' => $s->label, 'public' => $s->public, 'internal' => $s->internal,
		], $statuses ) );
	}

	public static function execute_settings_list_plugins( array $input ): array {
		if ( ! function_exists( 'get_plugins' ) ) { require_once ABSPATH . 'wp-admin/includes/plugin.php'; }
		$plugins = get_plugins();
		$active  = get_option( 'active_plugins', [] );
		$result  = [];
		foreach ( $plugins as $file => $data ) {
			$result[] = [
				'file' => $file, 'name' => $data['Name'], 'version' => $data['Version'],
				'author' => $data['Author'], 'active' => in_array( $file, $active, true ),
			];
		}
		return $result;
	}

	public static function execute_settings_list_themes( array $input ): array {
		$themes = wp_get_themes();
		return array_values( array_map( static fn( $t ) => [
			'slug' => $t->get_stylesheet(), 'name' => $t->get( 'Name' ), 'version' => $t->get( 'Version' ),
			'author' => $t->get( 'Author' ), 'active' => ( get_stylesheet() === $t->get_stylesheet() ),
		], $themes ) );
	}

	public static function execute_settings_get_active_theme( array $input ): array {
		$theme = wp_get_theme();
		return [
			'slug' => $theme->get_stylesheet(), 'name' => $theme->get( 'Name' ), 'version' => $theme->get( 'Version' ),
			'author' => $theme->get( 'Author' ), 'template' => $theme->get_template(),
			'text_domain' => $theme->get( 'TextDomain' ),
		];
	}

	public static function execute_settings_list_widgets( array $input ): array {
		global $wp_widget_factory;
		$result = [];
		foreach ( $wp_widget_factory->widgets as $widget ) {
			$result[] = [ 'id_base' => $widget->id_base, 'name' => $widget->name, 'description' => $widget->widget_options['description'] ?? '' ];
		}
		return $result;
	}

	public static function execute_settings_list_sidebars( array $input ): array {
		global $wp_registered_sidebars;
		return array_values( array_map( static fn( $s ) => [
			'id' => $s['id'], 'name' => $s['name'], 'description' => $s['description'] ?? '',
		], $wp_registered_sidebars ?? [] ) );
	}

	public static function execute_settings_get_site_info( array $input ): array {
		global $wp_version;
		return [
			'name'            => get_bloginfo( 'name' ),
			'description'     => get_bloginfo( 'description' ),
			'url'             => home_url(),
			'admin_email'     => get_option( 'admin_email' ),
			'wp_version'      => $wp_version,
			'php_version'     => PHP_VERSION,
			'timezone'        => get_option( 'timezone_string' ) ?: wp_timezone_string(),
			'language'        => get_locale(),
			'is_multisite'    => is_multisite(),
			'active_plugins'  => count( get_option( 'active_plugins', [] ) ),
		];
	}
}
