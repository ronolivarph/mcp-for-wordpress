<?php
declare(strict_types=1);

namespace McpForWordPress\Mcp\Abilities;

use McpForWordPress\Mcp\ToolRegistry;
use McpForWordPress\Support\Errors;
use McpForWordPress\Support\Pagination;
use McpForWordPress\Support\Schemas;

/**
 * MCP tools for WordPress media.
 *
 * Tools: list, get, upload, update-meta, delete, set-alt-text.
 */
final class MediaAbilities {

	public static function register_tools( ToolRegistry $r ): void {
		$r->register( 'mcp-for-wordpress/media.list', [
			'description'         => __( 'List media attachments with pagination.', 'mcp-for-wordpress' ),
			'input_schema'        => [
				'type' => 'object',
				'properties' => [
					'search'    => [ 'type' => 'string' ],
					'mime_type' => [ 'type' => 'string' ],
					'page'      => [ 'type' => 'integer', 'minimum' => 1, 'default' => 1 ],
					'per_page'  => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ],
				],
			],
			'permission_callback' => static fn(): bool => current_user_can( 'upload_files' ),
			'execute_callback'    => [ self::class, 'execute_list' ],
		] );

		$r->register( 'mcp-for-wordpress/media.get', [
			'description'         => __( 'Retrieve a single media attachment by ID.', 'mcp-for-wordpress' ),
			'input_schema'        => [ 'type' => 'object', 'properties' => [ 'id' => [ 'type' => 'integer' ] ], 'required' => [ 'id' ] ],
			'permission_callback' => static fn(): bool => current_user_can( 'upload_files' ),
			'execute_callback'    => [ self::class, 'execute_get' ],
		] );

		$r->register( 'mcp-for-wordpress/media.upload', [
			'description'         => __( 'Upload a media file from a URL or base64 data.', 'mcp-for-wordpress' ),
			'input_schema'        => [
				'type' => 'object',
				'properties' => [
					'url'       => [ 'type' => 'string', 'format' => 'uri', 'description' => 'URL to sideload from.' ],
					'filename'  => [ 'type' => 'string' ],
					'title'     => [ 'type' => 'string' ],
					'alt_text'  => [ 'type' => 'string' ],
					'caption'   => [ 'type' => 'string' ],
				],
				'required' => [ 'url' ],
			],
			'permission_callback' => static fn(): bool => current_user_can( 'upload_files' ),
			'execute_callback'    => [ self::class, 'execute_upload' ],
		] );

		$r->register( 'mcp-for-wordpress/media.update-meta', [
			'description'         => __( 'Update title, caption, or description of a media item.', 'mcp-for-wordpress' ),
			'input_schema'        => [
				'type' => 'object',
				'properties' => [
					'id'          => [ 'type' => 'integer' ],
					'title'       => [ 'type' => 'string' ],
					'caption'     => [ 'type' => 'string' ],
					'description' => [ 'type' => 'string' ],
				],
				'required' => [ 'id' ],
			],
			'permission_callback' => static fn(): bool => current_user_can( 'upload_files' ),
			'execute_callback'    => [ self::class, 'execute_update_meta' ],
		] );

		$r->register( 'mcp-for-wordpress/media.delete', [
			'description'         => __( 'Permanently delete a media attachment.', 'mcp-for-wordpress' ),
			'input_schema'        => [ 'type' => 'object', 'properties' => [ 'id' => [ 'type' => 'integer' ] ], 'required' => [ 'id' ] ],
			'permission_callback' => static fn(): bool => current_user_can( 'delete_posts' ),
			'execute_callback'    => [ self::class, 'execute_delete' ],
		] );

		$r->register( 'mcp-for-wordpress/media.set-alt-text', [
			'description'         => __( 'Set the alt text for a media attachment.', 'mcp-for-wordpress' ),
			'input_schema'        => [
				'type' => 'object',
				'properties' => [ 'id' => [ 'type' => 'integer' ], 'alt_text' => [ 'type' => 'string' ] ],
				'required' => [ 'id', 'alt_text' ],
			],
			'permission_callback' => static fn(): bool => current_user_can( 'upload_files' ),
			'execute_callback'    => [ self::class, 'execute_set_alt_text' ],
		] );
	}

	public static function execute_list( array $input ): array {
		$args = [
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => $input['per_page'] ?? 20,
			'paged'          => $input['page'] ?? 1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		];
		if ( ! empty( $input['search'] ) )    { $args['s'] = sanitize_text_field( $input['search'] ); }
		if ( ! empty( $input['mime_type'] ) ) { $args['post_mime_type'] = sanitize_mime_type( $input['mime_type'] ); }

		$query = new \WP_Query( $args );
		return Pagination::response(
			array_map( [ self::class, 'format_media' ], $query->posts ),
			(int) $query->found_posts,
			$args['paged'],
			$args['posts_per_page']
		);
	}

	public static function execute_get( array $input ): array {
		$post = get_post( absint( $input['id'] ) );
		if ( ! $post || $post->post_type !== 'attachment' ) { return Errors::not_found( 'media', $input['id'] ); }
		return self::format_media( $post );
	}

	public static function execute_upload( array $input ): array {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$url = esc_url_raw( $input['url'] );

		$tmp = download_url( $url );
		if ( is_wp_error( $tmp ) ) { return Errors::from_wp_error( $tmp ); }

		$filename = ! empty( $input['filename'] ) ? sanitize_file_name( $input['filename'] ) : basename( wp_parse_url( $url, PHP_URL_PATH ) );

		$file_array = [ 'name' => $filename, 'tmp_name' => $tmp ];
		$attachment_id = media_handle_sideload( $file_array, 0, $input['title'] ?? '' );

		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return Errors::from_wp_error( $attachment_id );
		}

		if ( ! empty( $input['alt_text'] ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $input['alt_text'] ) );
		}
		if ( ! empty( $input['caption'] ) ) {
			wp_update_post( [ 'ID' => $attachment_id, 'post_excerpt' => sanitize_textarea_field( $input['caption'] ) ] );
		}

		return self::format_media( get_post( $attachment_id ) );
	}

	public static function execute_update_meta( array $input ): array {
		$post = get_post( absint( $input['id'] ) );
		if ( ! $post || $post->post_type !== 'attachment' ) { return Errors::not_found( 'media', $input['id'] ); }

		$data = [ 'ID' => $post->ID ];
		if ( isset( $input['title'] ) )       { $data['post_title']   = sanitize_text_field( $input['title'] ); }
		if ( isset( $input['caption'] ) )     { $data['post_excerpt'] = sanitize_textarea_field( $input['caption'] ); }
		if ( isset( $input['description'] ) ) { $data['post_content'] = sanitize_textarea_field( $input['description'] ); }

		wp_update_post( $data );
		return self::format_media( get_post( $post->ID ) );
	}

	public static function execute_delete( array $input ): array {
		$post = get_post( absint( $input['id'] ) );
		if ( ! $post || $post->post_type !== 'attachment' ) { return Errors::not_found( 'media', $input['id'] ); }
		$result = wp_delete_attachment( $post->ID, true );
		return [ 'deleted' => $result !== false && $result !== null ];
	}

	public static function execute_set_alt_text( array $input ): array {
		$post = get_post( absint( $input['id'] ) );
		if ( ! $post || $post->post_type !== 'attachment' ) { return Errors::not_found( 'media', $input['id'] ); }
		update_post_meta( $post->ID, '_wp_attachment_image_alt', sanitize_text_field( $input['alt_text'] ) );
		return self::format_media( get_post( $post->ID ) );
	}

	public static function format_media( \WP_Post $post ): array {
		$meta = wp_get_attachment_metadata( $post->ID );
		return [
			'id'         => $post->ID,
			'title'      => $post->post_title,
			'alt_text'   => get_post_meta( $post->ID, '_wp_attachment_image_alt', true ) ?: '',
			'mime_type'  => $post->post_mime_type,
			'source_url' => wp_get_attachment_url( $post->ID ),
			'date'       => $post->post_date_gmt,
			'width'      => $meta['width'] ?? 0,
			'height'     => $meta['height'] ?? 0,
			'file_size'  => $meta['filesize'] ?? 0,
		];
	}
}
