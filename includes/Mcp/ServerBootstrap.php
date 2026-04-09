<?php
declare(strict_types=1);

namespace McpForWordPress\Mcp;

use McpForWordPress\Mcp\Abilities\CommentAbilities;
use McpForWordPress\Mcp\Abilities\MediaAbilities;
use McpForWordPress\Mcp\Abilities\MenuAbilities;
use McpForWordPress\Mcp\Abilities\PagesAbilities;
use McpForWordPress\Mcp\Abilities\PostsAbilities;
use McpForWordPress\Mcp\Abilities\SearchAbilities;
use McpForWordPress\Mcp\Abilities\SettingsAbilities;
use McpForWordPress\Mcp\Abilities\TaxonomyAbilities;
use McpForWordPress\Mcp\Abilities\UserAbilities;
use McpForWordPress\OAuth\TokenIntrospector;

/**
 * Initialises the MCP adapter and registers our OAuth-protected MCP server + abilities.
 *
 * Two integration points:
 * 1. `wp_abilities_api_init` — register all MCP abilities (tools).
 * 2. `mcp_adapter_init` — create a custom MCP server with our OAuth permission callback.
 */
final class ServerBootstrap {

	public const SERVER_ID              = 'mcp-for-wordpress';
	public const SERVER_ROUTE_NAMESPACE = 'mcpwp';
	public const SERVER_ROUTE           = 'mcp';

	/**
	 * Hook into WordPress to register MCP abilities and the OAuth-protected server.
	 */
	public static function register(): void {
		add_action( 'wp_abilities_api_init', [ self::class, 'register_abilities' ] );
		add_action( 'mcp_adapter_init', [ self::class, 'register_server' ] );

		// Disable the default unauthenticated MCP server — we replace it with ours.
		add_filter( 'mcp_adapter_create_default_server', '__return_false' );

		// Register all ability categories.
		PostsAbilities::register();
		PagesAbilities::register();
		TaxonomyAbilities::register();
		MediaAbilities::register();
		CommentAbilities::register();
		UserAbilities::register();
		MenuAbilities::register();
		SettingsAbilities::register();
		SearchAbilities::register();
	}

	/**
	 * Register all MCP abilities (tools) with the Abilities API.
	 */
	public static function register_abilities(): void {
		// Ping — smoke-test tool; will be replaced by real abilities in Phase 5.
		wp_register_ability(
			'mcp-for-wordpress/ping',
			[
				'label'               => __( 'Ping', 'mcp-for-wordpress' ),
				'description'         => __( 'Health-check tool. Returns pong.', 'mcp-for-wordpress' ),
				'input_schema'        => [
					'type'       => 'object',
					'properties' => new \stdClass(), // no inputs
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'message' => [ 'type' => 'string' ],
					],
				],
				'permission_callback' => static fn(): bool => current_user_can( 'read' ),
				'execute_callback'    => [ self::class, 'execute_ping' ],
				'meta'                => [
					'mcp' => [
						'public' => true,
						'type'   => 'tool',
					],
				],
			]
		);
	}

	/**
	 * Create the OAuth-protected MCP server via the mcp-adapter API.
	 *
	 * @param \WP\MCP\Core\McpAdapter $adapter The MCP adapter instance.
	 */
	public static function register_server( $adapter ): void {
		$adapter->create_server(
			self::SERVER_ID,
			self::SERVER_ROUTE_NAMESPACE,
			self::SERVER_ROUTE,
			__( 'MCP for WordPress', 'mcp-for-wordpress' ),
			__( 'OAuth 2.1-protected MCP server exposing WordPress tools.', 'mcp-for-wordpress' ),
			MCPWP_VERSION,
			[ \WP\MCP\Transport\HttpTransport::class ],
			null, // error handler — use default
			null, // observability — use default
			[
				'mcp-adapter/discover-abilities',
				'mcp-adapter/get-ability-info',
				'mcp-adapter/execute-ability',
			],
			[], // resources
			[], // prompts
			[ TokenIntrospector::class, 'validate' ] // OAuth permission callback
		);
	}

	/**
	 * Execute the ping ability.
	 *
	 * @param array<string, mixed> $input Unused.
	 * @return array{message: string}
	 */
	public static function execute_ping( array $input ): array {
		return [ 'message' => 'pong' ];
	}
}
