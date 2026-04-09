<?php
declare(strict_types=1);

namespace McpForWordPress\OAuth;

use League\OAuth2\Server\Exception\OAuthServerException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;

/**
 * Handles the /authorize endpoint via a WordPress rewrite rule (NOT REST API).
 *
 * WordPress REST API deliberately ignores cookie auth (requires X-WP-Nonce header),
 * which breaks the browser-based OAuth flow. Using a rewrite rule means normal
 * WordPress cookie authentication works after wp-login.php redirect.
 *
 * URL: https://example.com/mcpwp-authorize/?client_id=...&redirect_uri=...&...
 */
final class AuthorizeController {

	public const ENDPOINT_SLUG = 'mcpwp-authorize';

	/**
	 * Register rewrite rules and hooks.
	 */
	public static function register(): void {
		add_action( 'init', [ self::class, 'add_rewrite_rules' ] );
		add_filter( 'query_vars', [ self::class, 'add_query_vars' ] );
		add_action( 'template_redirect', [ self::class, 'handle_request' ] );
	}

	/**
	 * Add the rewrite rule for the authorize endpoint.
	 */
	public static function add_rewrite_rules(): void {
		add_rewrite_rule(
			'^' . self::ENDPOINT_SLUG . '/?$',
			'index.php?mcpwp_authorize=1',
			'top'
		);
	}

	/**
	 * Register the query var.
	 *
	 * @param string[] $vars Existing query vars.
	 * @return string[]
	 */
	public static function add_query_vars( array $vars ): array {
		$vars[] = 'mcpwp_authorize';
		return $vars;
	}

	/**
	 * Handle the authorize request on template_redirect.
	 */
	public static function handle_request(): void {
		if ( ! get_query_var( 'mcpwp_authorize' ) ) {
			return;
		}

		$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

		if ( $method === 'GET' ) {
			self::handle_get();
		} elseif ( $method === 'POST' ) {
			self::handle_post();
		}

		exit;
	}

	/**
	 * GET /mcpwp-authorize/ — validate the auth request and show the consent screen.
	 */
	private static function handle_get(): void {
		// Require login — redirect to wp-login.php with return URL.
		if ( ! is_user_logged_in() ) {
			$current_url = home_url( '/' . self::ENDPOINT_SLUG . '/' ) . '?' . $_SERVER['QUERY_STRING'];
			wp_safe_redirect( wp_login_url( $current_url ) );
			exit;
		}

		$server = AuthorizationServer::instance();

		try {
			$psr_request  = self::create_psr7_from_globals();
			$auth_request = $server->validateAuthorizationRequest( $psr_request );

			// Store the auth request in a transient keyed by a nonce.
			$nonce = wp_create_nonce( 'mcpwp_authorize' );
			set_transient( 'mcpwp_auth_request_' . $nonce, serialize( $auth_request ), 600 );

			// Render consent screen.
			self::render_consent_screen( $auth_request, $nonce );
			exit;

		} catch ( OAuthServerException $e ) {
			$psr_response = $e->generateHttpResponse( ( new Psr17Factory() )->createResponse() );
			self::send_psr7_response( $psr_response );
			exit;
		}
	}

	/**
	 * POST /mcpwp-authorize/ — user approves or denies the request.
	 */
	private static function handle_post(): void {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'You must be logged in.', 'mcp-for-wordpress' ), 403 );
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, 'mcpwp_authorize' ) ) {
			wp_die( esc_html__( 'Invalid nonce.', 'mcp-for-wordpress' ), 403 );
		}

		$auth_request_data = get_transient( 'mcpwp_auth_request_' . $nonce );
		if ( ! $auth_request_data ) {
			wp_die( esc_html__( 'Authorization request expired. Please try again.', 'mcp-for-wordpress' ), 400 );
		}

		$auth_request = unserialize( $auth_request_data ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
		delete_transient( 'mcpwp_auth_request_' . $nonce );

		$approved = ( sanitize_text_field( wp_unslash( $_POST['approve'] ?? '0' ) ) === '1' );

		$auth_request->setUser( new UserEntity( get_current_user_id() ) );
		$auth_request->setAuthorizationApproved( $approved );

		$server = AuthorizationServer::instance();

		try {
			$psr_response = $server->completeAuthorizationRequest(
				$auth_request,
				( new Psr17Factory() )->createResponse()
			);

			self::send_psr7_response( $psr_response );
			exit;

		} catch ( OAuthServerException $e ) {
			$psr_response = $e->generateHttpResponse( ( new Psr17Factory() )->createResponse() );
			self::send_psr7_response( $psr_response );
			exit;
		}
	}

	/**
	 * Get the full authorize endpoint URL.
	 *
	 * @return string
	 */
	public static function get_endpoint_url(): string {
		return home_url( '/' . self::ENDPOINT_SLUG . '/' );
	}

	/**
	 * Render the OAuth consent screen.
	 *
	 * @param \League\OAuth2\Server\RequestTypes\AuthorizationRequestInterface $auth_request The validated auth request.
	 * @param string $nonce The WP nonce for the form submission.
	 */
	private static function render_consent_screen( $auth_request, string $nonce ): void {
		$client     = $auth_request->getClient();
		$scopes     = $auth_request->getScopes();
		$action_url = home_url( '/' . self::ENDPOINT_SLUG . '/' );
		$user       = wp_get_current_user();

		header( 'Content-Type: text/html; charset=utf-8' );

		echo '<!DOCTYPE html><html><head>';
		echo '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
		echo '<title>' . esc_html__( 'Authorize Application', 'mcp-for-wordpress' ) . '</title>';
		echo '<style>';
		echo 'body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;max-width:480px;margin:60px auto;padding:0 20px;background:#f0f0f1;color:#1d2327}';
		echo '.card{background:#fff;border-radius:8px;padding:32px;box-shadow:0 1px 3px rgba(0,0,0,.1)}';
		echo 'h1{font-size:20px;margin:0 0 8px}';
		echo '.client-name{color:#2271b1;font-weight:600}';
		echo '.user-info{margin:16px 0;padding:12px;background:#f6f7f7;border-radius:4px;font-size:14px}';
		echo '.scopes{margin:16px 0;padding:0;list-style:none}';
		echo '.scopes li{padding:8px 0;border-bottom:1px solid #f0f0f1;font-size:14px}';
		echo '.scopes li::before{content:"✓ ";color:#00a32a}';
		echo '.actions{display:flex;gap:12px;margin-top:24px}';
		echo 'button{padding:10px 24px;border:none;border-radius:4px;font-size:14px;cursor:pointer;flex:1}';
		echo '.approve{background:#2271b1;color:#fff}.approve:hover{background:#135e96}';
		echo '.deny{background:#dcdcde;color:#1d2327}.deny:hover{background:#c3c4c7}';
		echo '</style></head><body>';

		echo '<div class="card">';
		echo '<h1>' . esc_html__( 'Authorize Application', 'mcp-for-wordpress' ) . '</h1>';
		echo '<p><span class="client-name">' . esc_html( $client->getName() ) . '</span> ';
		echo esc_html__( 'wants to access your WordPress site.', 'mcp-for-wordpress' ) . '</p>';

		echo '<div class="user-info">' . esc_html(
			sprintf(
				/* translators: %s: user display name */
				__( 'Logged in as: %s', 'mcp-for-wordpress' ),
				$user->display_name
			)
		) . '</div>';

		echo '<p><strong>' . esc_html__( 'This will allow the application to:', 'mcp-for-wordpress' ) . '</strong></p>';
		echo '<ul class="scopes">';
		foreach ( $scopes as $scope ) {
			echo '<li>' . esc_html( self::scope_description( $scope->getIdentifier() ) ) . '</li>';
		}
		echo '</ul>';

		echo '<form method="post" action="' . esc_url( $action_url ) . '">';
		echo '<input type="hidden" name="_wpnonce" value="' . esc_attr( $nonce ) . '">';
		echo '<div class="actions">';
		echo '<button type="submit" name="approve" value="0" class="deny">' . esc_html__( 'Deny', 'mcp-for-wordpress' ) . '</button>';
		echo '<button type="submit" name="approve" value="1" class="approve">' . esc_html__( 'Authorize', 'mcp-for-wordpress' ) . '</button>';
		echo '</div></form>';

		echo '</div></body></html>';
	}

	/**
	 * Get a human-readable description for a scope.
	 */
	private static function scope_description( string $scope_id ): string {
		$descriptions = [
			'mcp' => __( 'Use MCP tools to read and manage your WordPress content (posts, pages, media, users, settings) according to your role permissions.', 'mcp-for-wordpress' ),
		];

		return $descriptions[ $scope_id ] ?? $scope_id;
	}

	/**
	 * Create a PSR-7 ServerRequest from PHP globals.
	 */
	private static function create_psr7_from_globals(): \Psr\Http\Message\ServerRequestInterface {
		$factory = new Psr17Factory();
		$creator = new ServerRequestCreator( $factory, $factory, $factory, $factory );
		return $creator->fromGlobals();
	}

	/**
	 * Send a PSR-7 response back to the client.
	 */
	private static function send_psr7_response( \Psr\Http\Message\ResponseInterface $response ): void {
		http_response_code( $response->getStatusCode() );

		foreach ( $response->getHeaders() as $name => $values ) {
			foreach ( $values as $value ) {
				header( "$name: $value", false );
			}
		}

		echo $response->getBody();
	}
}
