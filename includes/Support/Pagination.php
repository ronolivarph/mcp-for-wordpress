<?php
declare(strict_types=1);

namespace McpForWordPress\Support;

/**
 * Pagination helpers for MCP ability responses.
 */
final class Pagination {

	/**
	 * Wrap an item schema into a paginated response schema.
	 *
	 * @param array<string, mixed> $item_schema The schema for a single item.
	 * @return array<string, mixed>
	 */
	public static function wrap( array $item_schema ): array {
		return [
			'type'       => 'object',
			'properties' => [
				'items'      => [
					'type'  => 'array',
					'items' => $item_schema,
				],
				'total'      => [ 'type' => 'integer' ],
				'page'       => [ 'type' => 'integer' ],
				'per_page'   => [ 'type' => 'integer' ],
				'total_pages' => [ 'type' => 'integer' ],
			],
		];
	}

	/**
	 * Build a paginated response array from a WP_Query-style result.
	 *
	 * @param array<mixed>  $items    The items for the current page.
	 * @param int           $total    Total number of items across all pages.
	 * @param int           $page     Current page number (1-based).
	 * @param int           $per_page Items per page.
	 * @return array{items: array<mixed>, total: int, page: int, per_page: int, total_pages: int}
	 */
	public static function response( array $items, int $total, int $page, int $per_page ): array {
		return [
			'items'       => $items,
			'total'       => $total,
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => $per_page > 0 ? (int) ceil( $total / $per_page ) : 0,
		];
	}
}
