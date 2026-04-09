<?php
declare(strict_types=1);

namespace McpForWordPress\OAuth;

use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Entities\Traits\AuthCodeTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\TokenEntityTrait;

/**
 * Authorization code entity.
 */
final class AuthCodeEntity implements AuthCodeEntityInterface {

	use AuthCodeTrait;
	use EntityTrait;
	use TokenEntityTrait;
}
