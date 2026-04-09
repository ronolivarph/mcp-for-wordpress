<?php
declare(strict_types=1);

namespace McpForWordPress\Tests\Unit\OAuth;

use PHPUnit\Framework\TestCase;
use McpForWordPress\OAuth\DiscoveryController;

final class DiscoveryTest extends TestCase {

	public function test_protected_resource_metadata_has_required_fields(): void {
		$metadata = DiscoveryController::protected_resource_metadata();

		$this->assertArrayHasKey( 'resource', $metadata );
		$this->assertArrayHasKey( 'authorization_servers', $metadata );
		$this->assertIsArray( $metadata['authorization_servers'] );
		$this->assertNotEmpty( $metadata['authorization_servers'] );
		$this->assertArrayHasKey( 'bearer_methods_supported', $metadata );
		$this->assertContains( 'header', $metadata['bearer_methods_supported'] );
	}

	public function test_authorization_server_metadata_has_required_fields(): void {
		$metadata = DiscoveryController::authorization_server_metadata();

		// RFC 8414 required fields.
		$this->assertArrayHasKey( 'issuer', $metadata );
		$this->assertArrayHasKey( 'authorization_endpoint', $metadata );
		$this->assertArrayHasKey( 'token_endpoint', $metadata );
		$this->assertArrayHasKey( 'response_types_supported', $metadata );

		// MCP-required: DCR endpoint.
		$this->assertArrayHasKey( 'registration_endpoint', $metadata );

		// MCP-required: PKCE S256.
		$this->assertArrayHasKey( 'code_challenge_methods_supported', $metadata );
		$this->assertContains( 'S256', $metadata['code_challenge_methods_supported'] );
	}

	public function test_authorization_server_metadata_grant_types(): void {
		$metadata = DiscoveryController::authorization_server_metadata();

		$this->assertContains( 'authorization_code', $metadata['grant_types_supported'] );
		$this->assertContains( 'refresh_token', $metadata['grant_types_supported'] );
		// Client credentials must NOT be listed.
		$this->assertNotContains( 'client_credentials', $metadata['grant_types_supported'] );
	}

	public function test_authorization_server_metadata_token_auth_methods(): void {
		$metadata = DiscoveryController::authorization_server_metadata();

		// Must support public clients (none) and optionally secret_post.
		$this->assertContains( 'none', $metadata['token_endpoint_auth_methods_supported'] );
	}

	public function test_www_authenticate_header_format(): void {
		$header = DiscoveryController::www_authenticate_header();

		$this->assertStringStartsWith( 'Bearer resource_metadata="', $header );
		$this->assertStringContainsString( 'oauth-protected-resource', $header );
		$this->assertStringEndsWith( '"', $header );
	}
}
