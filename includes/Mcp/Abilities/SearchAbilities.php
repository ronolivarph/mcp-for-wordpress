<?php
declare(strict_types=1);

namespace McpForWordPress\Mcp\Abilities;

use McpForWordPress\Mcp\ToolRegistry;
use McpForWordPress\Support\Pagination;

/**
 * MCP tools for WordPress search and discovery.
 *
 * Tools: universal-search, oembed-resolve, fetch-url-meta.
 */
final class SearchAbilities {

	public static function register_tools( ToolRegistry $r ): void {
		$r->register( 'wp_search_universal', [
			'description'         => __( 'Search across posts, pages, and custom post types.', 'mcp-for-wordpress' ),
			'input_schema'        => [
				'type' => 'object',
				'properties' => [
					'query'     => [ 'type' => 'string' ],
					'post_type' => [ 'type' => 'string', 'default' => 'any' ],
					'page'      => [ 'type' => 'integer', 'minimum' => 1, 'default' => 1 ],
					'per_page'  => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ],
				],
				'required' => [ 'query' ],
			],
			'permission_callback' => static fn(): bool => current_user_can( 'read' ),
			'execute_callback'    => [ self::class, 'execute_universal' ],
		] );

		$r->register( 'wp_search_oembed-resolve', [
			'description'         => __( 'Resolve an oEmbed URL and return embed HTML.', 'mcp-for-wordpress' ),
			'input_schema'        => [
				'type' => 'object',
				'properties' => [
					'url'       => [ 'type' => 'string', 'format' => 'uri' ],
					'maxwidth'  => [ 'type' => 'integer' ],
					'maxheight' => [ 'type' => 'integer' ],
				],
				'required' => [ 'url' ],
			],
			'permission_callback' => static fn(): bool => current_user_can( 'read' ),
			'execute_callback'    => [ self::class, 'execute_oembed_resolve' ],
		] );

		$r->register( 'wp_search_fetch-url-meta', [
			'description'         => __( 'Fetch title and meta description from a URL.', 'mcp-for-wordpress' ),
			'input_schema'        => [
				'type' => 'object',
				'properties' => [ 'url' => [ 'type' => 'string', 'format' => 'uri' ] ],
				'required' => [ 'url' ],
			],
			'permission_callback' => static fn(): bool => current_user_can( 'read' ),
			'execute_callback'    => [ self::class, 'execute_fetch_url_meta' ],
		] );
	}

	public static function execute_universal( array $input ): array {
		$per_page = $input['per_page'] ?? 20;
		$page     = $input['page'] ?? 1;

		$query = new \WP_Query( [
			's'              => sanitize_text_field( $input['query'] ),
			'post_type'      => $input['post_type'] ?? 'any',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $page,
		] );

		$items = array_map( static fn( \WP_Post $p ) => [
			'id'      => $p->ID,
			'title'   => $p->post_title,
			'excerpt' => wp_trim_words( $p->post_content, 30 ),
			'type'    => $p->post_type,
			'url'     => get_permalink( $p->ID ),
			'date'    => $p->post_date_gmt,
		], $query->posts );

		return Pagination::response( $items, (int) $query->found_posts, $page, $per_page );
	}

	public static function execute_oembed_resolve( array $input ): array {
		$url    = esc_url_raw( $input['url'] );
		$args   = [];
		if ( isset( $input['maxwidth'] ) )  { $args['width']  = absint( $input['maxwidth'] ); }
		if ( isset( $input['maxheight'] ) ) { $args['height'] = absint( $input['maxheight'] ); }

		$html = wp_oembed_get( $url, $args );

		if ( ! $html ) {
			return [ 'resolved' => false, 'html' => '', 'url' => $url ];
		}

		return [ 'resolved' => true, 'html' => $html, 'url' => $url ];
	}

	public static function execute_fetch_url_meta( array $input ): array {
		$url      = esc_url_raw( $input['url'] );
		$response = wp_safe_remote_get( $url, [ 'timeout' => 10 ] );

		if ( is_wp_error( $response ) ) {
			return [ 'title' => '', 'description' => '', 'image' => '' ];
		}

		$body  = wp_remote_retrieve_body( $response );
		$title = '';
		$desc  = '';
		$image = '';

		if ( preg_match( '/<title[^>]*>(.*?)<\/title>/si', $body, $m ) ) {
			$title = html_entity_decode( trim( $m[1] ), ENT_QUOTES, 'UTF-8' );
		}
		if ( preg_match( '/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']*)/si', $body, $m ) ) {
			$desc = html_entity_decode( trim( $m[1] ), ENT_QUOTES, 'UTF-8' );
		}
		if ( preg_match( '/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']*)/si', $body, $m ) ) {
			$image = esc_url_raw( $m[1] );
		}

		return [ 'title' => $title, 'description' => $desc, 'image' => $image ];
	}
}
