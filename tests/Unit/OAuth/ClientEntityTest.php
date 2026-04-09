<?php
declare(strict_types=1);

namespace McpForWordPress\Tests\Unit\OAuth;

use PHPUnit\Framework\TestCase;
use McpForWordPress\OAuth\ClientEntity;

final class ClientEntityTest extends TestCase {

	public function test_public_client_setup(): void {
		$client = new ClientEntity();
		$client->setIdentifier( 'test-client-id' );
		$client->setName( 'Test Client' );
		$client->setRedirectUri( [ 'http://localhost/callback' ] );
		$client->setConfidential( false );

		$this->assertSame( 'test-client-id', $client->getIdentifier() );
		$this->assertSame( 'Test Client', $client->getName() );
		$this->assertFalse( $client->isConfidential() );
	}

	public function test_confidential_client_setup(): void {
		$client = new ClientEntity();
		$client->setConfidential( true );

		$this->assertTrue( $client->isConfidential() );
	}

	public function test_redirect_uri_as_string(): void {
		$client = new ClientEntity();
		$client->setRedirectUri( 'http://localhost/callback' );

		$this->assertSame( 'http://localhost/callback', $client->getRedirectUri() );
	}

	public function test_redirect_uri_as_array(): void {
		$client = new ClientEntity();
		$client->setRedirectUri( [ 'http://localhost/cb1', 'http://localhost/cb2' ] );

		$uris = $client->getRedirectUri();
		$this->assertIsArray( $uris );
		$this->assertCount( 2, $uris );
	}
}
