<?php
declare(strict_types=1);

namespace McpForWordPress\Tests\Unit\OAuth;

use PHPUnit\Framework\TestCase;
use McpForWordPress\OAuth\UserEntity;

final class UserEntityTest extends TestCase {

	public function test_identifier_is_string_of_user_id(): void {
		$user = new UserEntity( 42 );

		$this->assertSame( '42', $user->getIdentifier() );
	}

	public function test_identifier_for_admin_user(): void {
		$user = new UserEntity( 1 );

		$this->assertSame( '1', $user->getIdentifier() );
	}
}
