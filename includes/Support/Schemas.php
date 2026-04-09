<?php
declare(strict_types=1);

namespace McpForWordPress\Support;

/**
 * Reusable JSON Schema fragments for MCP ability inputs/outputs.
 */
final class Schemas {

	/**
	 * Schema for a WordPress post object.
	 *
	 * @return array<string, mixed>
	 */
	public static function post(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'id'             => [ 'type' => 'integer' ],
				'title'          => [ 'type' => 'string' ],
				'content'        => [ 'type' => 'string' ],
				'excerpt'        => [ 'type' => 'string' ],
				'status'         => [ 'type' => 'string' ],
				'slug'           => [ 'type' => 'string' ],
				'author'         => [ 'type' => 'integer' ],
				'date'           => [ 'type' => 'string', 'format' => 'date-time' ],
				'modified'       => [ 'type' => 'string', 'format' => 'date-time' ],
				'categories'     => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
				'tags'           => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
				'featured_media' => [ 'type' => 'integer' ],
				'format'         => [ 'type' => 'string' ],
				'link'           => [ 'type' => 'string', 'format' => 'uri' ],
			],
		];
	}

	/**
	 * Schema for a WordPress taxonomy term.
	 *
	 * @return array<string, mixed>
	 */
	public static function term(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'id'          => [ 'type' => 'integer' ],
				'name'        => [ 'type' => 'string' ],
				'slug'        => [ 'type' => 'string' ],
				'description' => [ 'type' => 'string' ],
				'parent'      => [ 'type' => 'integer' ],
				'count'       => [ 'type' => 'integer' ],
				'taxonomy'    => [ 'type' => 'string' ],
			],
		];
	}

	/**
	 * Schema for a WordPress user.
	 *
	 * @return array<string, mixed>
	 */
	public static function user(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'id'           => [ 'type' => 'integer' ],
				'username'     => [ 'type' => 'string' ],
				'name'         => [ 'type' => 'string' ],
				'email'        => [ 'type' => 'string', 'format' => 'email' ],
				'roles'        => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
				'registered'   => [ 'type' => 'string', 'format' => 'date-time' ],
				'avatar_url'   => [ 'type' => 'string', 'format' => 'uri' ],
			],
		];
	}

	/**
	 * Schema for a WordPress comment.
	 *
	 * @return array<string, mixed>
	 */
	public static function comment(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'id'           => [ 'type' => 'integer' ],
				'post'         => [ 'type' => 'integer' ],
				'author_name'  => [ 'type' => 'string' ],
				'author_email' => [ 'type' => 'string', 'format' => 'email' ],
				'content'      => [ 'type' => 'string' ],
				'date'         => [ 'type' => 'string', 'format' => 'date-time' ],
				'status'       => [ 'type' => 'string' ],
				'parent'       => [ 'type' => 'integer' ],
			],
		];
	}

	/**
	 * Schema for a WordPress media attachment.
	 *
	 * @return array<string, mixed>
	 */
	public static function media(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'id'         => [ 'type' => 'integer' ],
				'title'      => [ 'type' => 'string' ],
				'alt_text'   => [ 'type' => 'string' ],
				'mime_type'  => [ 'type' => 'string' ],
				'source_url' => [ 'type' => 'string', 'format' => 'uri' ],
				'date'       => [ 'type' => 'string', 'format' => 'date-time' ],
				'width'      => [ 'type' => 'integer' ],
				'height'     => [ 'type' => 'integer' ],
				'file_size'  => [ 'type' => 'integer' ],
			],
		];
	}

	/**
	 * Schema for a WordPress nav menu item.
	 *
	 * @return array<string, mixed>
	 */
	public static function menu_item(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'id'        => [ 'type' => 'integer' ],
				'title'     => [ 'type' => 'string' ],
				'url'       => [ 'type' => 'string', 'format' => 'uri' ],
				'menu_id'   => [ 'type' => 'integer' ],
				'parent'    => [ 'type' => 'integer' ],
				'position'  => [ 'type' => 'integer' ],
				'type'      => [ 'type' => 'string' ],
				'object'    => [ 'type' => 'string' ],
				'object_id' => [ 'type' => 'integer' ],
			],
		];
	}
}
