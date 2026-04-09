<?php
declare(strict_types=1);

namespace McpForWordPress\Tests\Unit;

use PHPUnit\Framework\TestCase;
use McpForWordPress\Support\Schemas;

final class SchemasTest extends TestCase {

	/**
	 * @dataProvider schema_provider
	 */
	public function test_schema_is_valid_object_type( string $method ): void {
		$schema = Schemas::$method();

		$this->assertSame( 'object', $schema['type'] );
		$this->assertIsArray( $schema['properties'] );
		$this->assertNotEmpty( $schema['properties'] );
	}

	/**
	 * @dataProvider schema_provider
	 */
	public function test_schema_has_id_field( string $method ): void {
		$schema = Schemas::$method();

		$this->assertArrayHasKey( 'id', $schema['properties'] );
		$this->assertSame( 'integer', $schema['properties']['id']['type'] );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function schema_provider(): array {
		return [
			'post'      => [ 'post' ],
			'term'      => [ 'term' ],
			'user'      => [ 'user' ],
			'comment'   => [ 'comment' ],
			'media'     => [ 'media' ],
			'menu_item' => [ 'menu_item' ],
		];
	}
}
