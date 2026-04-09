<?php
declare(strict_types=1);

namespace McpForWordPress\OAuth;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;

/**
 * Manages OAuth scopes.
 *
 * For v1, we use a single scope "mcp" that grants access to all registered MCP tools.
 * Individual access control is handled by WordPress capabilities on each ability's
 * permission_callback, not by OAuth scopes.
 */
final class ScopeRepository implements ScopeRepositoryInterface {

	/**
	 * {@inheritdoc}
	 */
	public function getScopeEntityByIdentifier( string $identifier ): ?ScopeEntityInterface {
		if ( $identifier === 'mcp' ) {
			$scope = new ScopeEntity();
			$scope->setIdentifier( 'mcp' );
			return $scope;
		}

		return null;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param ScopeEntityInterface[] $scopes
	 * @return ScopeEntityInterface[]
	 */
	public function finalizeScopes(
		array $scopes,
		string $grantType,
		ClientEntityInterface $clientEntity,
		?string $userIdentifier = null,
		?string $authCodeId = null
	): array {
		// Always ensure the "mcp" scope is present.
		$has_mcp = false;
		foreach ( $scopes as $scope ) {
			if ( $scope->getIdentifier() === 'mcp' ) {
				$has_mcp = true;
				break;
			}
		}

		if ( ! $has_mcp ) {
			$mcp_scope = new ScopeEntity();
			$mcp_scope->setIdentifier( 'mcp' );
			$scopes[] = $mcp_scope;
		}

		return $scopes;
	}
}
