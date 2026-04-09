<?php
/**
 * Plugin Name: MCP for WordPress
 * Plugin URI:  https://github.com/ronolivarph/mcp-for-wordpress
 * Description: Turns a self-hosted WordPress site into an OAuth 2.1-protected remote MCP server for Claude Desktop and other MCP clients.
 * Version:     0.1.1
 * Requires PHP: 8.1
 * Requires at least: 6.4
 * Author:      Ron Olivar
 * Author URI:  https://github.com/ronolivarph
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: mcp-for-wordpress
 * Domain Path: /languages
 *
 * @package McpForWordPress
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MCPWP_VERSION', '0.1.1' );
define( 'MCPWP_PLUGIN_FILE', __FILE__ );
define( 'MCPWP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MCPWP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Jetpack Autoloader for safe dependency loading in multi-plugin environments.
if ( file_exists( __DIR__ . '/vendor/autoload_packages.php' ) ) {
	require_once __DIR__ . '/vendor/autoload_packages.php';
} elseif ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

add_action( 'plugins_loaded', [ McpForWordPress\Plugin::class, 'boot' ] );

// Activation hook — create custom DB tables.
register_activation_hook( __FILE__, [ McpForWordPress\Plugin::class, 'activate' ] );
