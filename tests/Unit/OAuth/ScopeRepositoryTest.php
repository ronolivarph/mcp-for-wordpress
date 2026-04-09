<?php
declare(strict_types=1);

namespace McpForWordPress\Tests\Unit\OAuth;

use PHPUnit\Framework\TestCase;
use McpForWordPress\OAuth\ScopeRepository;
use McpForWordPress\OAuth\ScopeEntity;

final class ScopeRepositoryTest extends TestCase {

	public function test_get_mcp_scope(): void {
		$repo  = new ScopeRepository();
		$scope = $repo->getScopeEntityByIdentifier( 'mcp' );

		$this->assertNotNull( $scope );
		$this->assertSame( 'mcp', $scope->getIdentifier() );
	}

	public function test_get_unknown_scope_returns_null(): void {
		$repo  = new ScopeRepository();
		$scope = $repo->getScopeEntityByIdentifier( 'nonexistent' );

		$this->assertNull( $scope );
	}

	public function test_finalize_scopes_adds_mcp_if_missing(): void {
		$repo   = new ScopeRepository();
		$client = $this->createMock( \League\OAuth2\Server\Entities\ClientEntityInterface::class );

		$result = $repo->finalizeScopes( [], 'authorization_code', $client, '1' );

		$this->assertCount( 1, $result );
		$this->assertSame( 'mcp', $result[0]->getIdentifier() );
	}

	public function test_finalize_scopes_does_not_duplicate_mcp(): void {
		$repo   = new ScopeRepository();
		$client = $this->createMock( \League\OAuth2\Server\Entities\ClientEntityInterface::class );

		$existing_scope = new ScopeEntity();
		$existing_scope->setIdentifier( 'mcp' );

		$result = $repo->finalizeScopes( [ $existing_scope ], 'authorization_code', $client, '1' );

		$this->assertCount( 1, $result );
	}
}
