<?php
declare(strict_types=1);

namespace McpForWordPress;

use McpForWordPress\Mcp\ServerBootstrap;
use McpForWordPress\OAuth\AuthorizeController;
use McpForWordPress\OAuth\DcrController;
use McpForWordPress\OAuth\DiscoveryController;
use McpForWordPress\OAuth\KeyManager;
use McpForWordPress\OAuth\Schema;
use McpForWordPress\OAuth\TokenController;

/**
 * Main plugin singleton. Wires all hooks on `plugins_loaded`.
 */
final class Plugin {

	private static ?self $instance = null;

	private function __construct() {
		// Intentionally empty — all wiring happens in init().
	}

	/**
	 * Boot the plugin. Called on `plugins_loaded`.
	 */
	public static function boot(): void {
		if ( self::$instance !== null ) {
			return;
		}

		self::$instance = new self();
		self::$instance->init();
	}

	/**
	 * Plugin activation hook — create DB tables, generate signing keys, etc.
	 */
	public static function activate(): void {
		Schema::create_tables();
		KeyManager::generate_keys();
		flush_rewrite_rules();
	}

	/**
	 * Wire up all plugin subsystems.
	 */
	private function init(): void {
		// MCP server bootstrap (registers abilities + OAuth-protected MCP server).
		ServerBootstrap::register();

		// OAuth discovery endpoints (.well-known + REST fallbacks).
		DiscoveryController::register();

		// OAuth endpoints (REST API).
		add_action( 'rest_api_init', [ DcrController::class, 'register' ] );
		add_action( 'rest_api_init', [ AuthorizeController::class, 'register' ] );
		add_action( 'rest_api_init', [ TokenController::class, 'register' ] );
	}
}
