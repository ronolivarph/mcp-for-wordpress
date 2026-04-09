<?php
declare(strict_types=1);

namespace McpForWordPress\OAuth;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\Traits\ClientTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;

/**
 * Represents an OAuth client registered via DCR or admin UI.
 */
final class ClientEntity implements ClientEntityInterface {

	use EntityTrait;
	use ClientTrait;

	/**
	 * Set the client name.
	 */
	public function setName( string $name ): void {
		$this->name = $name;
	}

	/**
	 * Set the redirect URI(s).
	 *
	 * @param string|string[] $uri Single URI or array of URIs.
	 */
	public function setRedirectUri( string|array $uri ): void {
		$this->redirectUri = $uri;
	}

	/**
	 * Set whether the client is confidential.
	 */
	public function setConfidential( bool $confidential ): void {
		$this->isConfidential = $confidential;
	}
}
