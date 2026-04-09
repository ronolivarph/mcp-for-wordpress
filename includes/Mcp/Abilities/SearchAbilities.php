<?php
declare(strict_types=1);

namespace McpForWordPress\Mcp\Abilities;

use McpForWordPress\Support\Pagination;

/**
 * MCP tools for WordPress search and discovery.
 *
 * Tools: universal-search, oembed-resolve, fetch-url-meta.
 */
final class SearchAbilities {

	public static function register(): void {
		add_action( 'wp_abilities_api_init', [ self::class, 'on_init' ] );
	}

	public static function on_init(): void {
		wp_register_ability( 'mcp-for-wordpress/search.universal', [
			'label'               => __( 'Universal Search', 'mcp-for-wordpress' ),
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
			'output_schema'       => Pagination::wrap( [
				'type' => 'object',
				'properties' => [
					'id' => [ 'type' => 'integer' ], 'title' => [ 'type' => 'string' ], 'excerpt' => [ 'type' => 'string' ],
					'type' => [ 'type' => 'string' ], 'url' => [ 'type' => 'string' ], 'date' => [ 'type' => 'string' ],
				],
			] ),
			'permission_callback' => static fn(): bool => current_user_can( 'read' ),
			'execute_callback'    => [ self::class, 'execute_universal' ],
			'meta'                => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		] );

		wp_register_ability( 'mcp-for-wordpress/search.oembed-resolve', [
			'label'               => __( 'Resolve oEmbed', 'mcp-for-wordpress' ),
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
			'output_schema'       => [ 'type' => 'object' ],
			'permission_callback' => static fn(): bool => current_user_can( 'read' ),
			'execute_callback'    => [ self::class, 'execute_oembed_resolve' ],
			'meta'                => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		] );

		wp_register_ability( 'mcp-for-wordpress/search.fetch-url-meta', [
			'label'               => __( 'Fetch URL Metadata', 'mcp-for-wordpress' ),
			'description'         => __( 'Fetch title and meta description from a URL.', 'mcp-for-wordpress' ),
			'input_schema'        => [
				'type' => 'object',
				'properties' => [ 'url' => [ 'type' => 'string', 'format' => 'uri' ] ],
				'required' => [ 'url' ],
			],
			'output_schema'       => [
				'type' => 'object',
				'properties' => [
					'title' => [ 'type' => 'string' ], 'description' => [ 'type' => 'string' ], 'image' => [ 'type' => 'string' ],
				],
			],
			'permission_callback' => static fn(): bool => current_user_can( 'read' ),
			'execute_callback'    => [ self::class, 'execute_fetch_url_meta' ],
			'meta'                => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
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
