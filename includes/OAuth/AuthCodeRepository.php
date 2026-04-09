<?php
declare(strict_types=1);

namespace McpForWordPress\OAuth;

use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;

/**
 * Persists authorization codes in {prefix}mcpwp_auth_codes.
 */
final class AuthCodeRepository implements AuthCodeRepositoryInterface {

	/**
	 * {@inheritdoc}
	 */
	public function getNewAuthCode(): AuthCodeEntityInterface {
		return new AuthCodeEntity();
	}

	/**
	 * {@inheritdoc}
	 */
	public function persistNewAuthCode( AuthCodeEntityInterface $authCodeEntity ): void {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'mcpwp_auth_codes',
			[
				'code_id'    => $authCodeEntity->getIdentifier(),
				'client_id'  => $authCodeEntity->getClient()->getIdentifier(),
				'user_id'    => $authCodeEntity->getUserIdentifier(),
				'expires_at' => $authCodeEntity->getExpiryDateTime()->format( 'Y-m-d H:i:s' ),
				'revoked'    => 0,
			],
			[ '%s', '%s', '%s', '%s', '%d' ]
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function revokeAuthCode( string $codeId ): void {
		global $wpdb;

		$wpdb->update(
			$wpdb->prefix . 'mcpwp_auth_codes',
			[ 'revoked' => 1 ],
			[ 'code_id' => $codeId ],
			[ '%d' ],
			[ '%s' ]
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function isAuthCodeRevoked( string $codeId ): bool {
		global $wpdb;

		$table   = $wpdb->prefix . 'mcpwp_auth_codes';
		$revoked = $wpdb->get_var(
			$wpdb->prepare( "SELECT revoked FROM `$table` WHERE code_id = %s", $codeId ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		return $revoked === null || (int) $revoked === 1;
	}
}
