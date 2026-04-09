<?php
declare(strict_types=1);

namespace McpForWordPress\Tests\Unit;

use PHPUnit\Framework\TestCase;
use McpForWordPress\Support\Errors;

/**
 * Unit tests for the Errors helper.
 *
 * Note: These tests don't require WordPress — we mock WP_Error.
 */
final class ErrorsTest extends TestCase {

	public function test_permission_denied_without_capability(): void {
		$error = Errors::permission_denied();

		$this->assertSame( 'permission_denied', $error['code'] );
		$this->assertNotEmpty( $error['message'] );
	}

	public function test_permission_denied_with_capability(): void {
		$error = Errors::permission_denied( 'edit_posts' );

		$this->assertSame( 'permission_denied', $error['code'] );
		$this->assertStringContainsString( 'edit_posts', $error['message'] );
	}

	public function test_not_found(): void {
		$error = Errors::not_found( 'post', 42 );

		$this->assertSame( 'not_found', $error['code'] );
		$this->assertStringContainsString( 'Post', $error['message'] );
		$this->assertStringContainsString( '42', $error['message'] );
	}
}
