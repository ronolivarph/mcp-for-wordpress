<?php
declare(strict_types=1);

namespace McpForWordPress\OAuth;

use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\RefreshTokenTrait;

/**
 * Refresh token entity.
 */
final class RefreshTokenEntity implements RefreshTokenEntityInterface {

	use EntityTrait;
	use RefreshTokenTrait;
}
