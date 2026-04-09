<?php
declare(strict_types=1);

namespace McpForWordPress\OAuth;

/**
 * Creates and manages the custom database tables for OAuth storage.
 *
 * Tables:
 * - {prefix}mcpwp_clients       — DCR-registered OAuth clients
 * - {prefix}mcpwp_access_tokens — Issued access tokens
 * - {prefix}mcpwp_auth_codes    — Authorization codes
 * - {prefix}mcpwp_refresh_tokens — Refresh tokens
 */
final class Schema {

	/**
	 * Create all OAuth tables. Called on plugin activation.
	 */
	public static function create_tables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$clients_table = $wpdb->prefix . 'mcpwp_clients';
		$tokens_table  = $wpdb->prefix . 'mcpwp_access_tokens';
		$codes_table   = $wpdb->prefix . 'mcpwp_auth_codes';
		$refresh_table = $wpdb->prefix . 'mcpwp_refresh_tokens';

		$sql = "CREATE TABLE $clients_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			client_id varchar(255) NOT NULL,
			client_name varchar(255) NOT NULL DEFAULT '',
			redirect_uris text NOT NULL,
			is_confidential tinyint(1) NOT NULL DEFAULT 0,
			client_secret_hash varchar(255) NOT NULL DEFAULT '',
			grant_types varchar(255) NOT NULL DEFAULT 'authorization_code',
			token_endpoint_auth_method varchar(50) NOT NULL DEFAULT 'none',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			revoked tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			UNIQUE KEY client_id (client_id),
			KEY revoked (revoked)
		) $charset_collate;

		CREATE TABLE $tokens_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			token_id varchar(100) NOT NULL,
			client_id varchar(255) NOT NULL,
			user_id bigint(20) unsigned DEFAULT NULL,
			scopes text DEFAULT NULL,
			expires_at datetime NOT NULL,
			revoked tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			UNIQUE KEY token_id (token_id),
			KEY client_id (client_id),
			KEY user_id (user_id),
			KEY revoked (revoked)
		) $charset_collate;

		CREATE TABLE $codes_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			code_id varchar(100) NOT NULL,
			client_id varchar(255) NOT NULL,
			user_id bigint(20) unsigned DEFAULT NULL,
			expires_at datetime NOT NULL,
			revoked tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			UNIQUE KEY code_id (code_id),
			KEY client_id (client_id)
		) $charset_collate;

		CREATE TABLE $refresh_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			token_id varchar(100) NOT NULL,
			access_token_id varchar(100) NOT NULL,
			expires_at datetime NOT NULL,
			revoked tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			UNIQUE KEY token_id (token_id),
			KEY access_token_id (access_token_id),
			KEY revoked (revoked)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'mcpwp_db_version', '1.0.0' );
	}

	/**
	 * Drop all OAuth tables. Called on plugin uninstall (if applicable).
	 */
	public static function drop_tables(): void {
		global $wpdb;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}mcpwp_clients" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}mcpwp_access_tokens" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}mcpwp_auth_codes" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}mcpwp_refresh_tokens" );
		// phpcs:enable

		delete_option( 'mcpwp_db_version' );
	}
}
