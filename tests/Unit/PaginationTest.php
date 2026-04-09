<?php
declare(strict_types=1);

namespace McpForWordPress\Tests\Unit;

use PHPUnit\Framework\TestCase;
use McpForWordPress\Support\Pagination;

final class PaginationTest extends TestCase {

	public function test_wrap_produces_valid_schema(): void {
		$item_schema = [ 'type' => 'object', 'properties' => [ 'id' => [ 'type' => 'integer' ] ] ];
		$wrapped     = Pagination::wrap( $item_schema );

		$this->assertSame( 'object', $wrapped['type'] );
		$this->assertArrayHasKey( 'items', $wrapped['properties'] );
		$this->assertArrayHasKey( 'total', $wrapped['properties'] );
		$this->assertArrayHasKey( 'page', $wrapped['properties'] );
		$this->assertArrayHasKey( 'per_page', $wrapped['properties'] );
		$this->assertArrayHasKey( 'total_pages', $wrapped['properties'] );
		$this->assertSame( $item_schema, $wrapped['properties']['items']['items'] );
	}

	public function test_response_calculates_total_pages(): void {
		$result = Pagination::response( [ 'a', 'b', 'c' ], 25, 2, 10 );

		$this->assertSame( [ 'a', 'b', 'c' ], $result['items'] );
		$this->assertSame( 25, $result['total'] );
		$this->assertSame( 2, $result['page'] );
		$this->assertSame( 10, $result['per_page'] );
		$this->assertSame( 3, $result['total_pages'] );
	}

	public function test_response_handles_zero_per_page(): void {
		$result = Pagination::response( [], 0, 1, 0 );

		$this->assertSame( 0, $result['total_pages'] );
	}

	public function test_response_single_page(): void {
		$result = Pagination::response( [ 'x' ], 1, 1, 20 );

		$this->assertSame( 1, $result['total_pages'] );
	}
}
