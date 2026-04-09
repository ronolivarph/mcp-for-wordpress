<?php
/**
 * PHPUnit bootstrap file.
 *
 * For unit tests (no WP): just load the Composer autoloader.
 * For integration tests: load wp-phpunit's bootstrap which loads WordPress.
 */

declare(strict_types=1);

// Composer autoloader.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Polyfill WordPress i18n functions for unit tests (no WP loaded).
if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'home_url' ) ) {
	function home_url( string $path = '' ): string {
		return 'https://example.com' . $path;
	}
}
if ( ! function_exists( 'rest_url' ) ) {
	function rest_url( string $path = '' ): string {
		return 'https://example.com/wp-json/' . ltrim( $path, '/' );
	}
}

// If running integration tests via wp-env, the WP test bootstrap path
// is set via the WP_TESTS_DIR env variable.
$wp_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( $wp_tests_dir ) {
	// Integration mode: load the WP test suite.
	$_tests_dir = rtrim( $wp_tests_dir, '/\\' );

	if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
		echo "Could not find $_tests_dir/includes/functions.php\n"; // phpcs:ignore
		exit( 1 );
	}

	// Load WP test functions.
	require_once $_tests_dir . '/includes/functions.php';

	// Activate the plugin during test setup.
	tests_add_filter(
		'muplugins_loaded',
		static function (): void {
			require dirname( __DIR__ ) . '/mcp-for-wordpress.php';
		}
	);

	// Start up the WP testing environment.
	require $_tests_dir . '/includes/bootstrap.php';
}
