<?php
declare(strict_types=1);

namespace McpForWordPress\Mcp;

/**
 * Central registry for MCP tools.
 *
 * Replaces wp_register_ability() — tools are registered here and served
 * directly by McpEndpoint without the WordPress Abilities API.
 *
 * Usage:
 *   ToolRegistry::instance()->register('mcp-for-wordpress/posts.list', [
 *       'description'         => 'List posts with filtering.',
 *       'input_schema'        => [...],
 *       'permission_callback' => fn() => current_user_can('edit_posts'),
 *       'execute_callback'    => [PostsAbilities::class, 'execute_list'],
 *   ]);
 */
final class ToolRegistry {

	private static ?self $instance = null;

	/** @var array<string, array{description: string, input_schema: array, permission_callback: callable, execute_callback: callable}> */
	private array $tools = [];

	private function __construct() {}

	public static function instance(): self {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register a tool.
	 *
	 * @param string               $name Tool name (e.g. 'mcp-for-wordpress/posts.list').
	 * @param array<string, mixed> $args {
	 *     @type string   $description         Human-readable description.
	 *     @type array    $input_schema        JSON Schema for tool arguments.
	 *     @type callable $permission_callback  Returns bool — checked before execution.
	 *     @type callable $execute_callback     Receives array $arguments, returns mixed.
	 * }
	 */
	public function register( string $name, array $args ): void {
		$this->tools[ $name ] = [
			'description'         => $args['description'] ?? '',
			'input_schema'        => $args['input_schema'] ?? [ 'type' => 'object', 'properties' => new \stdClass() ],
			'permission_callback' => $args['permission_callback'] ?? '__return_true',
			'execute_callback'    => $args['execute_callback'],
		];
	}

	/**
	 * Get a tool by name.
	 *
	 * @return array<string, mixed>|null
	 */
	public function get( string $name ): ?array {
		return $this->tools[ $name ] ?? null;
	}

	/**
	 * Get all registered tools.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_all(): array {
		return $this->tools;
	}

	/**
	 * Reset (for tests).
	 */
	public static function reset(): void {
		self::$instance = null;
	}
}
