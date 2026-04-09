<?php
declare(strict_types=1);

namespace McpForWordPress\OAuth;

use League\OAuth2\Server\Entities\UserEntityInterface;

/**
 * Represents the authenticated WordPress user in the OAuth flow.
 */
final class UserEntity implements UserEntityInterface {

	private string $identifier;

	public function __construct( int $user_id ) {
		$this->identifier = (string) $user_id;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getIdentifier(): string {
		return $this->identifier;
	}
}
