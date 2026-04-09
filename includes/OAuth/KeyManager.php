<?php
declare(strict_types=1);

namespace McpForWordPress\OAuth;

use Defuse\Crypto\Key;

/**
 * Manages RSA signing keys and the Defuse encryption key for OAuth tokens.
 *
 * Keys are stored in wp-content/mcp-keys/ which is protected by an .htaccess deny-all.
 * The RSA private key signs JWTs; the Defuse key encrypts auth codes and refresh tokens.
 */
final class KeyManager {

	private const KEY_DIR_NAME = 'mcp-keys';

	/**
	 * Get the absolute path to the key storage directory.
	 */
	public static function get_key_dir(): string {
		return WP_CONTENT_DIR . '/' . self::KEY_DIR_NAME;
	}

	/**
	 * Get the path to the RSA private key file.
	 */
	public static function get_private_key_path(): string {
		return self::get_key_dir() . '/private.key';
	}

	/**
	 * Get the path to the RSA public key file.
	 */
	public static function get_public_key_path(): string {
		return self::get_key_dir() . '/public.key';
	}

	/**
	 * Get the Defuse encryption key string for encrypting auth codes / refresh tokens.
	 *
	 * @return string The ASCII-safe encryption key.
	 */
	public static function get_encryption_key(): string {
		$key_file = self::get_key_dir() . '/encryption.key';

		if ( ! file_exists( $key_file ) ) {
			self::generate_keys();
		}

		$key = file_get_contents( $key_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( $key === false ) {
			wp_die( esc_html__( 'MCP for WordPress: Could not read encryption key.', 'mcp-for-wordpress' ) );
		}

		return trim( $key );
	}

	/**
	 * Generate all keys if they don't already exist.
	 * Called during plugin activation and on-demand.
	 */
	public static function generate_keys(): void {
		$key_dir = self::get_key_dir();

		if ( ! is_dir( $key_dir ) ) {
			wp_mkdir_p( $key_dir );
		}

		// Protect the directory with .htaccess.
		$htaccess = $key_dir . '/.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			file_put_contents( $htaccess, "Deny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}

		// Also add an index.php to prevent directory listing.
		$index = $key_dir . '/index.php';
		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}

		// Generate RSA key pair.
		if ( ! file_exists( self::get_private_key_path() ) ) {
			$config = [
				'private_key_bits' => 2048,
				'private_key_type' => OPENSSL_KEYTYPE_RSA,
			];

			$key_resource = openssl_pkey_new( $config );
			if ( $key_resource === false ) {
				wp_die( esc_html__( 'MCP for WordPress: Failed to generate RSA keys. Ensure OpenSSL is available.', 'mcp-for-wordpress' ) );
			}

			openssl_pkey_export( $key_resource, $private_key_pem );
			$public_key_details = openssl_pkey_get_details( $key_resource );

			file_put_contents( self::get_private_key_path(), $private_key_pem ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( self::get_public_key_path(), $public_key_details['key'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

			chmod( self::get_private_key_path(), 0600 );
			chmod( self::get_public_key_path(), 0644 );
		}

		// Generate Defuse encryption key.
		$encryption_key_file = $key_dir . '/encryption.key';
		if ( ! file_exists( $encryption_key_file ) ) {
			$key = Key::createNewRandomKey();
			file_put_contents( $encryption_key_file, $key->saveToAsciiSafeString() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			chmod( $encryption_key_file, 0600 );
		}
	}

	/**
	 * Get the public key as a PEM string (for JWKS endpoint).
	 *
	 * @return string PEM-encoded RSA public key.
	 */
	public static function get_public_key_pem(): string {
		$path = self::get_public_key_path();
		if ( ! file_exists( $path ) ) {
			self::generate_keys();
		}

		$key = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return $key !== false ? $key : '';
	}
}
