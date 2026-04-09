<?php
declare(strict_types=1);

namespace McpForWordPress\Mcp;

use McpForWordPress\OAuth\DiscoveryController;
use McpForWordPress\OAuth\TokenIntrospector;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Self-contained MCP JSON-RPC 2.0 endpoint over WordPress REST API.
 *
 * Implements the MCP protocol (2025-06-18 spec) without depending on
 * the WordPress mcp-adapter plugin. Handles:
 * - initialize (session creation + capability negotiation)
 * - ping
 * - tools/list
 * - tools/call
 *
 * All requests are authenticated via OAuth Bearer token (TokenIntrospector).
 *
 * Endpoint: POST /wp-json/mcpwp/mcp
 */
final class McpEndpoint {

	/**
	 * Register the REST route.
	 */
	public static function register(): void {
		add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
	}

	/**
	 * Register REST API routes.
	 */
	public static function register_routes(): void {
		register_rest_route(
			'mcpwp',
			'/mcp',
			[
				[
					'methods'             => 'POST',
					'callback'            => [ self::class, 'handle_post' ],
					'permission_callback' => [ self::class, 'check_auth' ],
				],
				[
					'methods'             => 'DELETE',
					'callback'            => [ self::class, 'handle_delete' ],
					'permission_callback' => [ self::class, 'check_auth' ],
				],
				[
					'methods'             => 'GET',
					'callback'            => [ self::class, 'handle_get' ],
					'permission_callback' => '__return_true',
				],
			]
		);
	}

	/**
	 * OAuth Bearer token authentication.
	 *
	 * @return true|\WP_Error
	 */
	public static function check_auth( WP_REST_Request $request ) {
		// Initialize requests may come without a token on first contact.
		// Return 401 with WWW-Authenticate to trigger the OAuth discovery flow.
		$auth_header = $request->get_header( 'Authorization' );

		if ( ! $auth_header || ! preg_match( '/^Bearer\s+/i', $auth_header ) ) {
			return new \WP_Error(
				'mcpwp_unauthorized',
				'Bearer token required.',
				[
					'status'  => 401,
					'headers' => [
						'WWW-Authenticate' => DiscoveryController::www_authenticate_header(),
					],
				]
			);
		}

		return TokenIntrospector::validate( $request );
	}

	/**
	 * Handle POST — JSON-RPC 2.0 messages.
	 */
	public static function handle_post( WP_REST_Request $request ): WP_REST_Response {
		$body = $request->get_json_params();

		if ( empty( $body ) ) {
			return self::jsonrpc_error( null, -32700, 'Parse error: invalid JSON' );
		}

		// Batch request support.
		if ( isset( $body[0] ) ) {
			$responses = [];
			foreach ( $body as $msg ) {
				$result = self::dispatch_message( $msg, $request );
				if ( $result !== null ) {
					$responses[] = $result;
				}
			}
			return new WP_REST_Response( $responses, 200 );
		}

		// Single request.
		$result = self::dispatch_message( $body, $request );

		if ( $result === null ) {
			// Notification — no response needed.
			return new WP_REST_Response( null, 202 );
		}

		// Map error codes to HTTP status.
		$http_status = 200;
		if ( isset( $result['error'] ) ) {
			$code = $result['error']['code'] ?? 0;
			$http_status = self::error_code_to_http_status( $code );
		}

		return new WP_REST_Response( $result, $http_status );
	}

	/**
	 * Handle GET — return 405 (reserved for SSE in future).
	 */
	public static function handle_get( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response(
			[ 'error' => 'GET not supported. Use POST for MCP JSON-RPC messages.' ],
			405
		);
	}

	/**
	 * Handle DELETE — session termination.
	 */
	public static function handle_delete( WP_REST_Request $request ): WP_REST_Response {
		$session_id = $request->get_header( 'Mcp-Session-Id' );
		if ( $session_id ) {
			delete_transient( 'mcpwp_session_' . sanitize_key( $session_id ) );
		}
		return new WP_REST_Response( null, 200 );
	}

	/**
	 * Dispatch a single JSON-RPC message to the appropriate handler.
	 *
	 * @param array<string, mixed> $msg    The JSON-RPC message.
	 * @param WP_REST_Request      $request The WP request (for headers).
	 * @return array<string, mixed>|null Response array, or null for notifications.
	 */
	private static function dispatch_message( array $msg, WP_REST_Request $request ): ?array {
		// Validate JSON-RPC 2.0 structure.
		if ( ( $msg['jsonrpc'] ?? '' ) !== '2.0' ) {
			return self::jsonrpc_error_array( $msg['id'] ?? null, -32600, 'Invalid Request: missing jsonrpc 2.0' );
		}

		$method = $msg['method'] ?? null;
		$params = $msg['params'] ?? [];
		$id     = $msg['id'] ?? null;

		if ( $method === null ) {
			return self::jsonrpc_error_array( $id, -32600, 'Invalid Request: missing method' );
		}

		// Notifications have no id — no response expected.
		$is_notification = ( $id === null );

		// Session validation for non-initialize requests.
		if ( $method !== 'initialize' ) {
			$session_id = $request->get_header( 'Mcp-Session-Id' );
			if ( ! $session_id ) {
				return $is_notification ? null : self::jsonrpc_error_array( $id, -32600, 'Missing Mcp-Session-Id header' );
			}

			$session = get_transient( 'mcpwp_session_' . sanitize_key( $session_id ) );
			if ( ! $session ) {
				return $is_notification ? null : self::jsonrpc_error_array( $id, -32005, 'Session not found or expired' );
			}
		}

		// Route to handler.
		$result = match ( $method ) {
			'initialize'   => self::handle_initialize( $params ),
			'ping'         => [ 'result' => new \stdClass() ],
			'tools/list'   => self::handle_tools_list(),
			'tools/call'   => self::handle_tools_call( $params ),
			default        => [ 'error' => [ 'code' => -32601, 'message' => "Method not found: $method" ] ],
		};

		if ( $is_notification ) {
			return null;
		}

		// Build JSON-RPC response.
		if ( isset( $result['error'] ) ) {
			return self::jsonrpc_error_array( $id, $result['error']['code'], $result['error']['message'], $result['error']['data'] ?? null );
		}

		return [
			'jsonrpc' => '2.0',
			'id'      => $id,
			'result'  => $result['result'],
		];
	}

	/**
	 * Handle `initialize` — create session, return capabilities.
	 *
	 * @param array<string, mixed> $params
	 * @return array<string, mixed>
	 */
	private static function handle_initialize( array $params ): array {
		$session_id = wp_generate_uuid4();

		// Store session (24-hour TTL).
		set_transient( 'mcpwp_session_' . $session_id, [
			'user_id'    => get_current_user_id(),
			'created_at' => time(),
			'client'     => $params['clientInfo'] ?? [],
		], DAY_IN_SECONDS );

		// Send session ID via header.
		header( 'Mcp-Session-Id: ' . $session_id );

		$protocol_version = $params['protocolVersion'] ?? '2024-11-05';
		$supported        = [ '2025-11-25', '2025-06-18', '2024-11-05' ];
		if ( ! in_array( $protocol_version, $supported, true ) ) {
			$protocol_version = '2024-11-05';
		}

		return [
			'result' => [
				'protocolVersion' => $protocol_version,
				'capabilities'    => [
					'tools' => [ 'listChanged' => false ],
				],
				'serverInfo'      => [
					'name'    => 'MCP for WordPress',
					'version' => MCPWP_VERSION,
				],
				'instructions'    => 'WordPress MCP server. Use tools/list to discover available tools.',
			],
		];
	}

	/**
	 * Handle `tools/list` — return all registered tools.
	 *
	 * @return array<string, mixed>
	 */
	private static function handle_tools_list(): array {
		$registry = ToolRegistry::instance();
		$tools    = [];

		foreach ( $registry->get_all() as $name => $tool ) {
			$tools[] = [
				'name'        => $name,
				'description' => $tool['description'],
				'inputSchema' => $tool['input_schema'],
			];
		}

		return [ 'result' => [ 'tools' => $tools ] ];
	}

	/**
	 * Handle `tools/call` — execute a tool.
	 *
	 * @param array<string, mixed> $params Must contain `name` and optionally `arguments`.
	 * @return array<string, mixed>
	 */
	private static function handle_tools_call( array $params ): array {
		$tool_name = $params['name'] ?? '';
		$arguments = $params['arguments'] ?? [];

		$registry = ToolRegistry::instance();
		$tool     = $registry->get( $tool_name );

		if ( $tool === null ) {
			return [ 'error' => [ 'code' => -32003, 'message' => "Tool not found: $tool_name" ] ];
		}

		// Check permission.
		if ( ! call_user_func( $tool['permission_callback'] ) ) {
			return [
				'result' => [
					'content' => [ [ 'type' => 'text', 'text' => 'Permission denied. Your role does not have the required capability.' ] ],
					'isError' => true,
				],
			];
		}

		// Execute.
		try {
			$output = call_user_func( $tool['execute_callback'], $arguments );

			// Convert result to MCP content format.
			$text = is_string( $output ) ? $output : wp_json_encode( $output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

			return [
				'result' => [
					'content' => [ [ 'type' => 'text', 'text' => $text ] ],
					'isError' => false,
				],
			];
		} catch ( \Exception $e ) {
			return [
				'result' => [
					'content' => [ [ 'type' => 'text', 'text' => 'Error: ' . $e->getMessage() ] ],
					'isError' => true,
				],
			];
		}
	}

	/**
	 * Build a JSON-RPC error WP_REST_Response.
	 */
	private static function jsonrpc_error( $id, int $code, string $message ): WP_REST_Response {
		return new WP_REST_Response(
			self::jsonrpc_error_array( $id, $code, $message ),
			self::error_code_to_http_status( $code )
		);
	}

	/**
	 * Build a JSON-RPC error array.
	 *
	 * @return array<string, mixed>
	 */
	private static function jsonrpc_error_array( $id, int $code, string $message, $data = null ): array {
		return [
			'jsonrpc' => '2.0',
			'id'      => $id,
			'error'   => [
				'code'    => $code,
				'message' => $message,
				'data'    => $data,
			],
		];
	}

	/**
	 * Map JSON-RPC error codes to HTTP status codes.
	 */
	private static function error_code_to_http_status( int $code ): int {
		return match ( $code ) {
			-32700    => 400, // Parse error
			-32600    => 400, // Invalid request
			-32601    => 404, // Method not found
			-32010    => 401, // Unauthorized
			-32008    => 403, // Permission denied
			-32003, -32002, -32004, -32005 => 404, // Not found variants
			-32001    => 504, // Timeout
			default   => 200, // App-level errors stay 200
		};
	}
}
