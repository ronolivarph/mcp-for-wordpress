<?php
declare(strict_types=1);

namespace McpForWordPress\OAuth;

use DateInterval;
use League\OAuth2\Server\AuthorizationServer as LeagueAuthorizationServer;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\Grant\RefreshTokenGrant;

/**
 * Factory for creating the league/oauth2-server AuthorizationServer instance.
 *
 * Configures grants, TTLs, and encryption keys. The AS supports only:
 * - Authorization Code + PKCE (required for MCP auth flow)
 * - Refresh Token (90-day lifetime)
 *
 * Client Credentials grant is intentionally disabled.
 */
final class AuthorizationServer {

	private static ?LeagueAuthorizationServer $instance = null;

	/**
	 * Get or create the singleton Authorization Server.
	 */
	public static function instance(): LeagueAuthorizationServer {
		if ( self::$instance !== null ) {
			return self::$instance;
		}

		$client_repo       = new ClientRepository();
		$access_token_repo = new AccessTokenRepository();
		$scope_repo        = new ScopeRepository();
		$auth_code_repo    = new AuthCodeRepository();
		$refresh_token_repo = new RefreshTokenRepository();

		$private_key    = KeyManager::get_private_key_path();
		$encryption_key = KeyManager::get_encryption_key();

		$server = new LeagueAuthorizationServer(
			$client_repo,
			$access_token_repo,
			$scope_repo,
			$private_key,
			$encryption_key
		);

		// Auth Code grant with PKCE — 10-minute code TTL.
		// PKCE is required by default for public clients in league/oauth2-server v9.
		$auth_code_grant = new AuthCodeGrant(
			$auth_code_repo,
			$refresh_token_repo,
			new DateInterval( 'PT10M' )
		);

		$server->enableGrantType(
			$auth_code_grant,
			new DateInterval( 'PT1H' ) // 1-hour access tokens
		);

		// Refresh Token grant — 90-day refresh token TTL.
		$refresh_token_grant = new RefreshTokenGrant( $refresh_token_repo );
		$refresh_token_grant->setRefreshTokenTTL( new DateInterval( 'P90D' ) );

		$server->enableGrantType(
			$refresh_token_grant,
			new DateInterval( 'PT1H' ) // New access tokens also 1 hour
		);

		self::$instance = $server;
		return self::$instance;
	}

	/**
	 * Reset the singleton (used in tests).
	 */
	public static function reset(): void {
		self::$instance = null;
	}
}
