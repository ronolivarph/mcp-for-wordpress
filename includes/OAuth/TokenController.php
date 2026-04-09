<?php
declare(strict_types=1);

namespace McpForWordPress\OAuth;

use League\OAuth2\Server\Exception\OAuthServerException;
use Nyholm\Psr7\Factory\Psr17Factory;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Handles the /token endpoint.
 *
 * Endpoint: POST /wp-json/mcpwp/v1/oauth/token
 *
 * Supports:
 * - authorization_code (with PKCE code_verifier)
 * - refresh_token
 *
 * NOTE: We build the PSR-7 request from WP_REST_Request params, NOT from
 * php://input globals. WordPress REST API consumes php://input before our
 * callback runs, so ServerRequestCreator::fromGlobals() gets an empty body.
 */
final class TokenController {

	/**
	 * Register the REST route.
	 */
	public static function register(): void {
		register_rest_route(
			'mcpwp/v1',
			'/oauth/token',
			[
				'methods'             => 'POST',
				'callback'            => [ self::class, 'handle' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	/**
	 * Handle a token request.
	 */
	public static function handle( WP_REST_Request $request ): WP_REST_Response {
		$server  = AuthorizationServer::instance();
		$factory = new Psr17Factory();

		// Build a PSR-7 request from the WP_REST_Request's parsed body.
		// league/oauth2-server reads token params from getParsedBody().
		$body_params = $request->get_body_params();

		// If WP didn't parse the body (e.g. JSON content-type), try JSON params.
		if ( empty( $body_params ) ) {
			$body_params = $request->get_json_params();
		}

		// Still empty? Try reading the raw body that WP cached.
		if ( empty( $body_params ) ) {
			$raw_body = $request->get_body();
			if ( ! empty( $raw_body ) ) {
				parse_str( $raw_body, $body_params );
			}
		}

		$uri = rest_url( 'mcpwp/v1/oauth/token' );

		$psr_request = $factory->createServerRequest( 'POST', $uri )
			->withParsedBody( $body_params )
			->withHeader( 'Content-Type', 'application/x-www-form-urlencoded' );

		// Forward the Authorization header if present (for confidential clients).
		$auth_header = $request->get_header( 'Authorization' );
		if ( $auth_header ) {
			$psr_request = $psr_request->withHeader( 'Authorization', $auth_header );
		}

		try {
			$psr_response = $server->respondToAccessTokenRequest(
				$psr_request,
				$factory->createResponse()
			);
		} catch ( OAuthServerException $e ) {
			$psr_response = $e->generateHttpResponse( $factory->createResponse() );
		} catch ( \Exception $e ) {
			$psr_response = ( new OAuthServerException( $e->getMessage(), 0, 'unknown_error', 500 ) )
				->generateHttpResponse( $factory->createResponse() );
		}

		// Convert PSR-7 response to WP_REST_Response.
		$body = (string) $psr_response->getBody();
		$data = json_decode( $body, true );

		$wp_response = new WP_REST_Response(
			$data ?? [ 'raw' => $body ],
			$psr_response->getStatusCode()
		);

		// Forward response headers (cache-control, pragma, etc.)
		foreach ( $psr_response->getHeaders() as $name => $values ) {
			// Skip content-type — WP_REST_Response handles it.
			if ( strtolower( $name ) === 'content-type' ) {
				continue;
			}
			$wp_response->header( $name, implode( ', ', $values ) );
		}

		return $wp_response;
	}
}
