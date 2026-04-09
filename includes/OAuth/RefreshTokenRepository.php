<?php
declare(strict_types=1);

namespace McpForWordPress\OAuth;

use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;

/**
 * Persists refresh tokens in {prefix}mcpwp_refresh_tokens.
 */
final class RefreshTokenRepository implements RefreshTokenRepositoryInterface {

	/**
	 * {@inheritdoc}
	 */
	public function getNewRefreshToken(): ?RefreshTokenEntityInterface {
		return new RefreshTokenEntity();
	}

	/**
	 * {@inheritdoc}
	 */
	public function persistNewRefreshToken( RefreshTokenEntityInterface $refreshTokenEntity ): void {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'mcpwp_refresh_tokens',
			[
				'token_id'         => $refreshTokenEntity->getIdentifier(),
				'access_token_id'  => $refreshTokenEntity->getAccessToken()->getIdentifier(),
				'expires_at'       => $refreshTokenEntity->getExpiryDateTime()->format( 'Y-m-d H:i:s' ),
				'revoked'          => 0,
			],
			[ '%s', '%s', '%s', '%d' ]
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function revokeRefreshToken( string $tokenId ): void {
		global $wpdb;

		$wpdb->update(
			$wpdb->prefix . 'mcpwp_refresh_tokens',
			[ 'revoked' => 1 ],
			[ 'token_id' => $tokenId ],
			[ '%d' ],
			[ '%s' ]
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function isRefreshTokenRevoked( string $tokenId ): bool {
		global $wpdb;

		$table   = $wpdb->prefix . 'mcpwp_refresh_tokens';
		$revoked = $wpdb->get_var(
			$wpdb->prepare( "SELECT revoked FROM `$table` WHERE token_id = %s", $tokenId ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		return $revoked === null || (int) $revoked === 1;
	}
}
