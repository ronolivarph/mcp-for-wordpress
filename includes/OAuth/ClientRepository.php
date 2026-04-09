<?php
declare(strict_types=1);

namespace McpForWordPress\OAuth;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;

/**
 * Stores OAuth clients in a custom DB table ({prefix}mcpwp_clients).
 * Clients are created via Dynamic Client Registration (RFC 7591).
 */
final class ClientRepository implements ClientRepositoryInterface {

	/**
	 * {@inheritdoc}
	 */
	public function getClientEntity( string $clientIdentifier ): ?ClientEntityInterface {
		global $wpdb;

		$table = $wpdb->prefix . 'mcpwp_clients';
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM `$table` WHERE client_id = %s AND revoked = 0", $clientIdentifier ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		if ( ! $row ) {
			return null;
		}

		$client = new ClientEntity();
		$client->setIdentifier( $row->client_id );
		$client->setName( $row->client_name );
		$client->setRedirectUri( explode( "\n", $row->redirect_uris ) );

		// Only mark as confidential if it was registered as such AND has a secret.
		// DCR clients without a secret must be treated as public, otherwise
		// league/oauth2-server rejects the token request before our validateClient runs.
		$is_confidential = ( (bool) $row->is_confidential ) && ! empty( $row->client_secret_hash );
		$client->setConfidential( $is_confidential );

		return $client;
	}

	/**
	 * {@inheritdoc}
	 */
	public function validateClient( string $clientIdentifier, ?string $clientSecret, ?string $grantType ): bool {
		$client = $this->getClientEntity( $clientIdentifier );

		if ( $client === null ) {
			return false;
		}

		// Public clients (MCP/Claude Desktop) don't have secrets.
		if ( ! $client->isConfidential() ) {
			return true;
		}

		// If marked confidential but no secret was stored, treat as public.
		// This handles clients registered via DCR without an explicit secret.
		global $wpdb;
		$table = $wpdb->prefix . 'mcpwp_clients';
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT client_secret_hash FROM `$table` WHERE client_id = %s", $clientIdentifier ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		if ( ! $row || empty( $row->client_secret_hash ) ) {
			return true;
		}

		// Confidential clients with a stored secret must provide a valid one.
		if ( $clientSecret === null ) {
			return false;
		}

		return password_verify( $clientSecret, $row->client_secret_hash );
	}

	/**
	 * Insert a new client from a DCR request.
	 *
	 * @param string   $client_id      Generated client ID.
	 * @param string   $client_name    Human-readable name.
	 * @param string[] $redirect_uris  Array of redirect URIs.
	 * @param bool     $is_confidential Whether the client has a secret.
	 * @param string   $secret_hash    Hashed client secret (empty for public clients).
	 * @param string   $grant_types    Comma-separated grant types.
	 * @param string   $token_endpoint_auth_method Auth method.
	 * @return bool Whether insert succeeded.
	 */
	public function create_client(
		string $client_id,
		string $client_name,
		array $redirect_uris,
		bool $is_confidential,
		string $secret_hash,
		string $grant_types,
		string $token_endpoint_auth_method
	): bool {
		global $wpdb;

		$table  = $wpdb->prefix . 'mcpwp_clients';
		$result = $wpdb->insert(
			$table,
			[
				'client_id'                  => $client_id,
				'client_name'                => $client_name,
				'redirect_uris'              => implode( "\n", $redirect_uris ),
				'is_confidential'            => $is_confidential ? 1 : 0,
				'client_secret_hash'         => $secret_hash,
				'grant_types'                => $grant_types,
				'token_endpoint_auth_method' => $token_endpoint_auth_method,
				'created_at'                 => current_time( 'mysql', true ),
				'revoked'                    => 0,
			],
			[ '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d' ]
		);

		return $result !== false;
	}

	/**
	 * Revoke a client and all its tokens.
	 *
	 * @param string $client_id The client identifier.
	 */
	public function revoke_client( string $client_id ): void {
		global $wpdb;

		$clients_table = $wpdb->prefix . 'mcpwp_clients';
		$tokens_table  = $wpdb->prefix . 'mcpwp_access_tokens';

		$wpdb->update( $clients_table, [ 'revoked' => 1 ], [ 'client_id' => $client_id ], [ '%d' ], [ '%s' ] );
		$wpdb->update( $tokens_table, [ 'revoked' => 1 ], [ 'client_id' => $client_id ], [ '%d' ], [ '%s' ] );
	}
}
