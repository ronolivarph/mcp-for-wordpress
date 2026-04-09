<?php
declare(strict_types=1);

namespace McpForWordPress\Support;

use WP_Error;

/**
 * Maps WP_Error instances into structured arrays suitable for MCP error responses.
 */
final class Errors {

	/**
	 * Convert a WP_Error into an MCP-friendly error array.
	 *
	 * @param WP_Error $error The WordPress error.
	 * @return array{code: string, message: string}
	 */
	public static function from_wp_error( WP_Error $error ): array {
		return [
			'code'    => $error->get_error_code() ?: 'unknown_error',
			'message' => $error->get_error_message() ?: __( 'An unknown error occurred.', 'mcp-for-wordpress' ),
		];
	}

	/**
	 * Create a permission-denied error array.
	 *
	 * @param string $capability The WP capability that was missing.
	 * @return array{code: string, message: string}
	 */
	public static function permission_denied( string $capability = '' ): array {
		$message = __( 'You do not have permission to perform this action.', 'mcp-for-wordpress' );
		if ( $capability !== '' ) {
			/* translators: %s: WordPress capability name */
			$message = sprintf( __( 'You do not have the required capability: %s', 'mcp-for-wordpress' ), $capability );
		}

		return [
			'code'    => 'permission_denied',
			'message' => $message,
		];
	}

	/**
	 * Create a not-found error array.
	 *
	 * @param string $resource_type The type of resource (e.g. "post", "user").
	 * @param int    $id            The ID that was not found.
	 * @return array{code: string, message: string}
	 */
	public static function not_found( string $resource_type, int $id ): array {
		return [
			'code'    => 'not_found',
			/* translators: 1: resource type, 2: resource ID */
			'message' => sprintf( __( '%1$s with ID %2$d not found.', 'mcp-for-wordpress' ), ucfirst( $resource_type ), $id ),
		];
	}
}
