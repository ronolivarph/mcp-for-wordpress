<?php
declare(strict_types=1);

namespace McpForWordPress\Mcp\Abilities;

use McpForWordPress\Support\Errors;
use McpForWordPress\Support\Pagination;
use McpForWordPress\Support\Schemas;

/**
 * MCP tools for WordPress comments.
 *
 * Tools: list, get, create, update, delete, approve, mark-spam, trash.
 */
final class CommentAbilities {

	public static function register(): void {
		add_action( 'wp_abilities_api_init', [ self::class, 'on_init' ] );
	}

	public static function on_init(): void {
		wp_register_ability( 'mcp-for-wordpress/comments.list', [
			'label'               => __( 'List Comments', 'mcp-for-wordpress' ),
			'description'         => __( 'List comments with filtering and pagination.', 'mcp-for-wordpress' ),
			'input_schema'        => [
				'type' => 'object',
				'properties' => [
					'post_id'  => [ 'type' => 'integer' ],
					'status'   => [ 'type' => 'string', 'enum' => [ 'approve', 'hold', 'spam', 'trash', 'all' ], 'default' => 'all' ],
					'search'   => [ 'type' => 'string' ],
					'page'     => [ 'type' => 'integer', 'minimum' => 1, 'default' => 1 ],
					'per_page' => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ],
				],
			],
			'output_schema'       => Pagination::wrap( Schemas::comment() ),
			'permission_callback' => static fn(): bool => current_user_can( 'moderate_comments' ),
			'execute_callback'    => [ self::class, 'execute_list' ],
			'meta'                => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		] );

		wp_register_ability( 'mcp-for-wordpress/comments.get', [
			'label'               => __( 'Get Comment', 'mcp-for-wordpress' ),
			'description'         => __( 'Retrieve a single comment by ID.', 'mcp-for-wordpress' ),
			'input_schema'        => [ 'type' => 'object', 'properties' => [ 'id' => [ 'type' => 'integer' ] ], 'required' => [ 'id' ] ],
			'output_schema'       => Schemas::comment(),
			'permission_callback' => static fn(): bool => current_user_can( 'moderate_comments' ),
			'execute_callback'    => [ self::class, 'execute_get' ],
			'meta'                => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		] );

		wp_register_ability( 'mcp-for-wordpress/comments.create', [
			'label'               => __( 'Create Comment', 'mcp-for-wordpress' ),
			'description'         => __( 'Add a comment to a post.', 'mcp-for-wordpress' ),
			'input_schema'        => [
				'type' => 'object',
				'properties' => [
					'post_id' => [ 'type' => 'integer' ],
					'content' => [ 'type' => 'string' ],
					'parent'  => [ 'type' => 'integer', 'default' => 0 ],
					'status'  => [ 'type' => 'string', 'enum' => [ 'approve', 'hold' ], 'default' => 'approve' ],
				],
				'required' => [ 'post_id', 'content' ],
			],
			'output_schema'       => Schemas::comment(),
			'permission_callback' => static fn(): bool => current_user_can( 'moderate_comments' ),
			'execute_callback'    => [ self::class, 'execute_create' ],
			'meta'                => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		] );

		wp_register_ability( 'mcp-for-wordpress/comments.update', [
			'label'               => __( 'Update Comment', 'mcp-for-wordpress' ),
			'description'         => __( 'Update a comment.', 'mcp-for-wordpress' ),
			'input_schema'        => [
				'type' => 'object',
				'properties' => [ 'id' => [ 'type' => 'integer' ], 'content' => [ 'type' => 'string' ], 'status' => [ 'type' => 'string' ] ],
				'required' => [ 'id' ],
			],
			'output_schema'       => Schemas::comment(),
			'permission_callback' => static fn(): bool => current_user_can( 'moderate_comments' ),
			'execute_callback'    => [ self::class, 'execute_update' ],
			'meta'                => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		] );

		wp_register_ability( 'mcp-for-wordpress/comments.delete', [
			'label' => __( 'Delete Comment', 'mcp-for-wordpress' ), 'description' => __( 'Permanently delete a comment.', 'mcp-for-wordpress' ),
			'input_schema' => [ 'type' => 'object', 'properties' => [ 'id' => [ 'type' => 'integer' ] ], 'required' => [ 'id' ] ],
			'output_schema' => [ 'type' => 'object', 'properties' => [ 'deleted' => [ 'type' => 'boolean' ] ] ],
			'permission_callback' => static fn(): bool => current_user_can( 'moderate_comments' ),
			'execute_callback' => [ self::class, 'execute_delete' ],
			'meta' => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		] );

		wp_register_ability( 'mcp-for-wordpress/comments.approve', [
			'label' => __( 'Approve Comment', 'mcp-for-wordpress' ), 'description' => __( 'Approve a held comment.', 'mcp-for-wordpress' ),
			'input_schema' => [ 'type' => 'object', 'properties' => [ 'id' => [ 'type' => 'integer' ] ], 'required' => [ 'id' ] ],
			'output_schema' => Schemas::comment(),
			'permission_callback' => static fn(): bool => current_user_can( 'moderate_comments' ),
			'execute_callback' => [ self::class, 'execute_approve' ],
			'meta' => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		] );

		wp_register_ability( 'mcp-for-wordpress/comments.mark-spam', [
			'label' => __( 'Mark Comment as Spam', 'mcp-for-wordpress' ), 'description' => __( 'Mark a comment as spam.', 'mcp-for-wordpress' ),
			'input_schema' => [ 'type' => 'object', 'properties' => [ 'id' => [ 'type' => 'integer' ] ], 'required' => [ 'id' ] ],
			'output_schema' => Schemas::comment(),
			'permission_callback' => static fn(): bool => current_user_can( 'moderate_comments' ),
			'execute_callback' => [ self::class, 'execute_mark_spam' ],
			'meta' => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		] );

		wp_register_ability( 'mcp-for-wordpress/comments.trash', [
			'label' => __( 'Trash Comment', 'mcp-for-wordpress' ), 'description' => __( 'Move a comment to trash.', 'mcp-for-wordpress' ),
			'input_schema' => [ 'type' => 'object', 'properties' => [ 'id' => [ 'type' => 'integer' ] ], 'required' => [ 'id' ] ],
			'output_schema' => Schemas::comment(),
			'permission_callback' => static fn(): bool => current_user_can( 'moderate_comments' ),
			'execute_callback' => [ self::class, 'execute_trash' ],
			'meta' => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		] );
	}

	public static function execute_list( array $input ): array {
		$per_page = $input['per_page'] ?? 20;
		$page     = $input['page'] ?? 1;
		$args     = [ 'number' => $per_page, 'offset' => ( $page - 1 ) * $per_page, 'status' => $input['status'] ?? 'all' ];
		if ( ! empty( $input['post_id'] ) ) { $args['post_id'] = absint( $input['post_id'] ); }
		if ( ! empty( $input['search'] ) )  { $args['search']  = sanitize_text_field( $input['search'] ); }

		$comments = get_comments( $args );
		$count_args = $args;
		$count_args['count'] = true;
		unset( $count_args['number'], $count_args['offset'] );
		$total = (int) get_comments( $count_args );

		return Pagination::response( array_map( [ self::class, 'format_comment' ], $comments ), $total, $page, $per_page );
	}

	public static function execute_get( array $input ): array {
		$comment = get_comment( absint( $input['id'] ) );
		if ( ! $comment ) { return Errors::not_found( 'comment', $input['id'] ); }
		return self::format_comment( $comment );
	}

	public static function execute_create( array $input ): array {
		$user = wp_get_current_user();
		$data = [
			'comment_post_ID'  => absint( $input['post_id'] ),
			'comment_content'  => wp_kses_post( $input['content'] ),
			'comment_parent'   => absint( $input['parent'] ?? 0 ),
			'comment_approved' => ( $input['status'] ?? 'approve' ) === 'approve' ? 1 : 0,
			'user_id'          => $user->ID,
			'comment_author'   => $user->display_name,
			'comment_author_email' => $user->user_email,
		];
		$comment_id = wp_insert_comment( $data );
		if ( ! $comment_id ) { return [ 'code' => 'insert_failed', 'message' => __( 'Failed to create comment.', 'mcp-for-wordpress' ) ]; }
		return self::format_comment( get_comment( $comment_id ) );
	}

	public static function execute_update( array $input ): array {
		$comment = get_comment( absint( $input['id'] ) );
		if ( ! $comment ) { return Errors::not_found( 'comment', $input['id'] ); }
		$data = [ 'comment_ID' => $comment->comment_ID ];
		if ( isset( $input['content'] ) ) { $data['comment_content'] = wp_kses_post( $input['content'] ); }
		if ( isset( $input['status'] ) )  { $data['comment_approved'] = $input['status'] === 'approve' ? 1 : 0; }
		wp_update_comment( $data );
		return self::format_comment( get_comment( $comment->comment_ID ) );
	}

	public static function execute_delete( array $input ): array {
		return [ 'deleted' => (bool) wp_delete_comment( absint( $input['id'] ), true ) ];
	}

	public static function execute_approve( array $input ): array {
		wp_set_comment_status( absint( $input['id'] ), 'approve' );
		return self::format_comment( get_comment( $input['id'] ) );
	}

	public static function execute_mark_spam( array $input ): array {
		wp_spam_comment( absint( $input['id'] ) );
		return self::format_comment( get_comment( $input['id'] ) );
	}

	public static function execute_trash( array $input ): array {
		wp_trash_comment( absint( $input['id'] ) );
		return self::format_comment( get_comment( $input['id'] ) );
	}

	public static function format_comment( \WP_Comment $c ): array {
		return [
			'id'           => (int) $c->comment_ID,
			'post'         => (int) $c->comment_post_ID,
			'author_name'  => $c->comment_author,
			'author_email' => $c->comment_author_email,
			'content'      => $c->comment_content,
			'date'         => $c->comment_date_gmt,
			'status'       => wp_get_comment_status( $c ),
			'parent'       => (int) $c->comment_parent,
		];
	}
}
