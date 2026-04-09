<?php
declare(strict_types=1);

namespace McpForWordPress\OAuth;

use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;

/**
 * Persists access tokens in {prefix}mcpwp_access_tokens.
 */
final class AccessTokenRepository implements AccessTokenRepositoryInterface {

	/**
	 * {@inheritdoc}
	 */
	public function getNewToken(
		ClientEntityInterface $clientEntity,
		array $scopes,
		?string $userIdentifier = null
	): AccessTokenEntityInterface {
		$token = new AccessTokenEntity();
		$token->setClient( $clientEntity );
		$token->setUserIdentifier( $userIdentifier );

		foreach ( $scopes as $scope ) {
			$token->addScope( $scope );
		}

		return $token;
	}

	/**
	 * {@inheritdoc}
	 */
	public function persistNewAccessToken( AccessTokenEntityInterface $accessTokenEntity ): void {
		global $wpdb;

		$scopes = array_map(
			static fn( ScopeEntityInterface $scope ): string => $scope->getIdentifier(),
			$accessTokenEntity->getScopes()
		);

		$wpdb->insert(
			$wpdb->prefix . 'mcpwp_access_tokens',
			[
				'token_id'        => $accessTokenEntity->getIdentifier(),
				'client_id'       => $accessTokenEntity->getClient()->getIdentifier(),
				'user_id'         => $accessTokenEntity->getUserIdentifier(),
				'scopes'          => wp_json_encode( $scopes ),
				'expires_at'      => $accessTokenEntity->getExpiryDateTime()->format( 'Y-m-d H:i:s' ),
				'revoked'         => 0,
			],
			[ '%s', '%s', '%s', '%s', '%s', '%d' ]
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function revokeAccessToken( string $tokenId ): void {
		global $wpdb;

		$wpdb->update(
			$wpdb->prefix . 'mcpwp_access_tokens',
			[ 'revoked' => 1 ],
			[ 'token_id' => $tokenId ],
			[ '%d' ],
			[ '%s' ]
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function isAccessTokenRevoked( string $tokenId ): bool {
		global $wpdb;

		$table   = $wpdb->prefix . 'mcpwp_access_tokens';
		$revoked = $wpdb->get_var(
			$wpdb->prepare( "SELECT revoked FROM `$table` WHERE token_id = %s", $tokenId ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		// If not found, treat as revoked.
		return $revoked === null || (int) $revoked === 1;
	}
}
