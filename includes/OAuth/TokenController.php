<?php
declare(strict_types=1);

namespace McpForWordPress\OAuth;

use League\OAuth2\Server\Exception\OAuthServerException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use WP_REST_Request;

/**
 * Handles the /token endpoint.
 *
 * Endpoint: POST /wp-json/mcpwp/v1/oauth/token
 *
 * Supports:
 * - authorization_code (with PKCE code_verifier)
 * - refresh_token
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
	public static function handle( WP_REST_Request $request ): void {
		$server = AuthorizationServer::instance();

		$factory     = new Psr17Factory();
		$creator     = new ServerRequestCreator( $factory, $factory, $factory, $factory );
		$psr_request = $creator->fromGlobals();

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

		// Send the PSR-7 response.
		http_response_code( $psr_response->getStatusCode() );

		foreach ( $psr_response->getHeaders() as $name => $values ) {
			foreach ( $values as $value ) {
				header( "$name: $value", false );
			}
		}

		echo $psr_response->getBody();
		exit;
	}
}
