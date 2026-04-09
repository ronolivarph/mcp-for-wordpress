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

/**
 * Registers the MCP endpoint and all tools.
 *
 * Uses our own McpEndpoint + ToolRegistry instead of the WordPress
 * mcp-adapter plugin, which requires the Abilities API that may not
 * be available on all WordPress installations.
 */
final class ServerBootstrap {

	/**
	 * Wire everything up.
	 */
	public static function register(): void {
		// Register the MCP JSON-RPC endpoint.
		McpEndpoint::register();

		// Register tools on init so WordPress is fully loaded.
		add_action( 'init', [ self::class, 'register_tools' ] );
	}

	/**
	 * Register all MCP tools with the ToolRegistry.
	 */
	public static function register_tools(): void {
		$r = ToolRegistry::instance();

		// Ping — health check.
		$r->register( 'wp_ping', [
			'description'         => 'Health-check tool. Returns pong.',
			'input_schema'        => [ 'type' => 'object', 'properties' => new \stdClass() ],
			'permission_callback' => static fn(): bool => current_user_can( 'read' ),
			'execute_callback'    => static fn( array $input ): array => [ 'message' => 'pong' ],
		] );

		// Register all ability categories.
		PostsAbilities::register_tools( $r );
		PagesAbilities::register_tools( $r );
		TaxonomyAbilities::register_tools( $r );
		MediaAbilities::register_tools( $r );
		CommentAbilities::register_tools( $r );
		UserAbilities::register_tools( $r );
		MenuAbilities::register_tools( $r );
		SettingsAbilities::register_tools( $r );
		SearchAbilities::register_tools( $r );
	}
}
