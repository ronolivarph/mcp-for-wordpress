<?php
declare(strict_types=1);

namespace McpForWordPress\OAuth;

use WP_REST_Request;
use WP_REST_Response;

/**
 * Serves the OAuth discovery documents required by the MCP auth spec:
 *
 * 1. /.well-known/oauth-protected-resource    (RFC 9728)
 * 2. /.well-known/oauth-authorization-server   (RFC 8414)
 *
 * Both are also served via REST routes for environments where .well-known
 * rewrites are not available.
 */
final class DiscoveryController {

	/**
	 * Register .well-known rewrite rules and REST fallback routes.
	 */
	public static function register(): void {
		// REST fallback routes (always work).
		add_action( 'rest_api_init', [ self::class, 'register_rest_routes' ] );

		// .well-known rewrite rules for canonical URLs.
		add_action( 'init', [ self::class, 'add_rewrite_rules' ] );
		add_filter( 'query_vars', [ self::class, 'add_query_vars' ] );
		add_action( 'template_redirect', [ self::class, 'handle_well_known' ] );
	}

	/**
	 * Register REST API fallback routes.
	 */
	public static function register_rest_routes(): void {
		register_rest_route(
			'mcpwp/v1',
			'/oauth/protected-resource',
			[
				'methods'             => 'GET',
				'callback'            => [ self::class, 'handle_protected_resource' ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			'mcpwp/v1',
			'/oauth/authorization-server',
			[
				'methods'             => 'GET',
				'callback'            => [ self::class, 'handle_authorization_server' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	/**
	 * Add rewrite rules for .well-known paths.
	 */
	public static function add_rewrite_rules(): void {
		add_rewrite_rule(
			'^\.well-known/oauth-protected-resource$',
			'index.php?mcpwp_well_known=protected-resource',
			'top'
		);
		add_rewrite_rule(
			'^\.well-known/oauth-authorization-server$',
			'index.php?mcpwp_well_known=authorization-server',
			'top'
		);
	}

	/**
	 * Register query vars.
	 *
	 * @param string[] $vars Existing query vars.
	 * @return string[]
	 */
	public static function add_query_vars( array $vars ): array {
		$vars[] = 'mcpwp_well_known';
		return $vars;
	}

	/**
	 * Intercept .well-known requests on template_redirect.
	 */
	public static function handle_well_known(): void {
		$well_known = get_query_var( 'mcpwp_well_known' );

		if ( $well_known === 'protected-resource' ) {
			wp_send_json( self::protected_resource_metadata() );
		} elseif ( $well_known === 'authorization-server' ) {
			wp_send_json( self::authorization_server_metadata() );
		}
	}

	/**
	 * REST handler for protected resource metadata (RFC 9728).
	 */
	public static function handle_protected_resource( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( self::protected_resource_metadata() );
	}

	/**
	 * REST handler for authorization server metadata (RFC 8414).
	 */
	public static function handle_authorization_server( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( self::authorization_server_metadata() );
	}

	/**
	 * Build the Protected Resource Metadata document (RFC 9728).
	 *
	 * @return array<string, mixed>
	 */
	public static function protected_resource_metadata(): array {
		$site_url = home_url();

		return [
			'resource'              => $site_url,
			'authorization_servers' => [ $site_url ],
			'bearer_methods_supported' => [ 'header' ],
			'scopes_supported'      => [ 'mcp' ],
			'resource_documentation' => rest_url( 'mcpwp/v1' ),
		];
	}

	/**
	 * Build the Authorization Server Metadata document (RFC 8414).
	 *
	 * @return array<string, mixed>
	 */
	public static function authorization_server_metadata(): array {
		$site_url = home_url();

		return [
			'issuer'                                => $site_url,
			'authorization_endpoint'                => rest_url( 'mcpwp/v1/oauth/authorize' ),
			'token_endpoint'                        => rest_url( 'mcpwp/v1/oauth/token' ),
			'registration_endpoint'                 => rest_url( 'mcpwp/v1/oauth/register' ),
			'scopes_supported'                      => [ 'mcp' ],
			'response_types_supported'              => [ 'code' ],
			'response_modes_supported'              => [ 'query' ],
			'grant_types_supported'                 => [ 'authorization_code', 'refresh_token' ],
			'token_endpoint_auth_methods_supported' => [ 'none', 'client_secret_post' ],
			'code_challenge_methods_supported'      => [ 'S256' ],
			'service_documentation'                 => rest_url( 'mcpwp/v1' ),
		];
	}

	/**
	 * Get the WWW-Authenticate header value for 401 responses (RFC 9728 §5.1).
	 *
	 * @return string The header value pointing to the protected resource metadata.
	 */
	public static function www_authenticate_header(): string {
		$metadata_url = home_url( '/.well-known/oauth-protected-resource' );

		return sprintf( 'Bearer resource_metadata="%s"', $metadata_url );
	}
}
