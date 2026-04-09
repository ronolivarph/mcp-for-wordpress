<?php
declare(strict_types=1);

namespace McpForWordPress\OAuth;

use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\ScopeTrait;

/**
 * Scope entity.
 */
final class ScopeEntity implements ScopeEntityInterface {

	use EntityTrait;
	use ScopeTrait;

	/**
	 * Set the scope identifier.
	 */
	public function setIdentifier( string $identifier ): void {
		$this->identifier = $identifier;
	}

	/**
	 * Serialize the scope for JSON responses.
	 *
	 * @return string
	 */
	#[\ReturnTypeWillChange]
	public function jsonSerialize(): string {
		return $this->getIdentifier();
	}
}
