<?php
declare(strict_types=1);

namespace McpForWordPress\OAuth;

use WP_REST_Request;
use WP_REST_Response;

/**
 * Handles Dynamic Client Registration (RFC 7591).
 *
 * Endpoint: POST /wp-json/mcpwp/v1/oauth/register
 *
 * Claude Desktop sends a DCR request with client_name and redirect_uris.
 * We generate a client_id (and optionally a client_secret for confidential clients),
 * store the registration, and return the RFC 7591 response.
 */
final class DcrController {

	/**
	 * Register the REST route.
	 */
	public static function register(): void {
		register_rest_route(
			'mcpwp/v1',
			'/oauth/register',
			[
				'methods'             => 'POST',
				'callback'            => [ self::class, 'handle' ],
				'permission_callback' => '__return_true', // DCR is open by design.
			]
		);
	}

	/**
	 * Handle a DCR request.
	 */
	public static function handle( WP_REST_Request $request ): WP_REST_Response {
		$body = $request->get_json_params();

		$client_name   = sanitize_text_field( $body['client_name'] ?? 'Unknown Client' );
		$redirect_uris = $body['redirect_uris'] ?? [];
		$grant_types   = $body['grant_types'] ?? [ 'authorization_code' ];
		$token_auth    = $body['token_endpoint_auth_method'] ?? 'none';

		// Validate redirect URIs.
		if ( ! is_array( $redirect_uris ) || count( $redirect_uris ) === 0 ) {
			return new WP_REST_Response(
				[
					'error'             => 'invalid_client_metadata',
					'error_description' => 'At least one redirect_uri is required.',
				],
				400
			);
		}

		$sanitized_uris = [];
		foreach ( $redirect_uris as $uri ) {
			$uri = esc_url_raw( $uri );
			if ( empty( $uri ) ) {
				return new WP_REST_Response(
					[
						'error'             => 'invalid_redirect_uri',
						'error_description' => 'One or more redirect URIs are invalid.',
					],
					400
				);
			}
			$sanitized_uris[] = $uri;
		}

		// Generate client credentials.
		$client_id     = wp_generate_uuid4();
		$is_confidential = ( $token_auth !== 'none' );
		$client_secret = '';
		$secret_hash   = '';

		if ( $is_confidential ) {
			$client_secret = bin2hex( random_bytes( 32 ) );
			$secret_hash   = password_hash( $client_secret, PASSWORD_DEFAULT );
		}

		$repo   = new ClientRepository();
		$result = $repo->create_client(
			$client_id,
			$client_name,
			$sanitized_uris,
			$is_confidential,
			$secret_hash,
			implode( ',', array_map( 'sanitize_text_field', $grant_types ) ),
			sanitize_text_field( $token_auth )
		);

		if ( ! $result ) {
			return new WP_REST_Response(
				[
					'error'             => 'server_error',
					'error_description' => 'Failed to register client.',
				],
				500
			);
		}

		$response_data = [
			'client_id'                  => $client_id,
			'client_name'                => $client_name,
			'redirect_uris'              => $sanitized_uris,
			'grant_types'                => $grant_types,
			'token_endpoint_auth_method' => $token_auth,
			'client_id_issued_at'        => time(),
		];

		if ( $is_confidential && $client_secret !== '' ) {
			$response_data['client_secret']            = $client_secret;
			$response_data['client_secret_expires_at'] = 0; // Never expires.
		}

		return new WP_REST_Response( $response_data, 201 );
	}
}
