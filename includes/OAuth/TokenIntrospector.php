<?php
declare(strict_types=1);

namespace McpForWordPress\OAuth;

use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResourceServer;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use WP_Error;
use WP_REST_Request;

/**
 * Validates Bearer tokens on incoming MCP requests and sets wp_set_current_user().
 *
 * This is used as the `transport_permission_callback` when creating the MCP server
 * via McpAdapter::create_server(). It runs before any MCP tool execution.
 */
final class TokenIntrospector {

	private static ?ResourceServer $resource_server = null;

	/**
	 * Permission callback for the MCP transport.
	 *
	 * @param WP_REST_Request $request The incoming REST request.
	 * @return true|WP_Error True if authorized, WP_Error otherwise.
	 */
	public static function validate( WP_REST_Request $request ) {
		$auth_header = $request->get_header( 'Authorization' );

		if ( ! $auth_header || ! preg_match( '/^Bearer\s+(.+)$/i', $auth_header ) ) {
			return new WP_Error(
				'mcpwp_missing_token',
				__( 'Bearer token required.', 'mcp-for-wordpress' ),
				[
					'status'  => 401,
					'headers' => [
						'WWW-Authenticate' => DiscoveryController::www_authenticate_header(),
					],
				]
			);
		}

		try {
			$resource_server = self::get_resource_server();

			$factory     = new Psr17Factory();
			$creator     = new ServerRequestCreator( $factory, $factory, $factory, $factory );
			$psr_request = $creator->fromGlobals();

			$validated_request = $resource_server->validateAuthenticatedRequest( $psr_request );

			// Extract user ID from the validated token.
			$user_id = $validated_request->getAttribute( 'oauth_user_id' );

			if ( ! $user_id ) {
				return new WP_Error(
					'mcpwp_invalid_token',
					__( 'Token is not bound to a user.', 'mcp-for-wordpress' ),
					[ 'status' => 401 ]
				);
			}

			// Verify the user still exists and is active.
			$user = get_userdata( (int) $user_id );
			if ( ! $user ) {
				return new WP_Error(
					'mcpwp_user_not_found',
					__( 'Token user no longer exists.', 'mcp-for-wordpress' ),
					[ 'status' => 401 ]
				);
			}

			// Set the current WordPress user — all subsequent permission_callback
			// checks on abilities will run as this user.
			wp_set_current_user( $user->ID );

			return true;

		} catch ( OAuthServerException $e ) {
			return new WP_Error(
				'mcpwp_token_error',
				$e->getMessage(),
				[
					'status'  => 401,
					'headers' => [
						'WWW-Authenticate' => DiscoveryController::www_authenticate_header(),
					],
				]
			);
		}
	}

	/**
	 * Get or create the league/oauth2-server ResourceServer for token validation.
	 */
	private static function get_resource_server(): ResourceServer {
		if ( self::$resource_server !== null ) {
			return self::$resource_server;
		}

		self::$resource_server = new ResourceServer(
			new AccessTokenRepository(),
			KeyManager::get_public_key_path()
		);

		return self::$resource_server;
	}

	/**
	 * Reset singleton (used in tests).
	 */
	public static function reset(): void {
		self::$resource_server = null;
	}
}
