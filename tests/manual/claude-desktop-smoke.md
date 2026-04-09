# Claude Desktop Smoke Test

End-to-end verification that Claude Desktop can connect to MCP for WordPress and execute tools.

## Prerequisites

- WordPress 6.4+ running with HTTPS (or localhost for dev)
- MCP for WordPress plugin activated
- An admin-level WordPress user account
- Claude Desktop (latest version)

## Setup: wp-env (local dev)

```bash
cd /path/to/mcp-for-wordpress

# Start the local WP environment
npx wp-env start

# Activate the plugin
npx wp-env run cli wp plugin activate mcp-for-wordpress

# Create the OAuth tables + signing keys
npx wp-env run cli wp eval 'McpForWordPress\Plugin::activate();'

# Flush rewrite rules for .well-known routes
npx wp-env run cli wp rewrite flush

# Verify the discovery endpoint works
curl -s http://localhost:8888/.well-known/oauth-protected-resource | jq .
# Expected: { "resource": "http://localhost:8888", "authorization_servers": [...], ... }

curl -s http://localhost:8888/.well-known/oauth-authorization-server | jq .
# Expected: { "issuer": "http://localhost:8888", "authorization_endpoint": "...", ... }

# Verify the MCP endpoint returns 401 without a token
curl -s -o /dev/null -w "%{http_code}" http://localhost:8888/wp-json/mcpwp/mcp
# Expected: 401
```

## Step 1: Add the MCP Server in Claude Desktop

1. Open Claude Desktop → Settings → MCP Servers
2. Click "Add Server" → "Remote MCP Server"
3. Enter the URL: `http://localhost:8888/wp-json/mcpwp/mcp`
4. Click "Connect"

## Step 2: OAuth Flow

Claude Desktop should automatically:

1. Fetch `/.well-known/oauth-protected-resource` (RFC 9728)
2. Extract the `authorization_servers` URL
3. Fetch `/.well-known/oauth-authorization-server` (RFC 8414)
4. Send a Dynamic Client Registration request to `/wp-json/mcpwp/v1/oauth/register`
5. Open a browser window to `/wp-json/mcpwp/v1/oauth/authorize?...`

**In the browser:**
1. Log in with your WordPress admin credentials (if not already logged in)
2. You should see the consent screen: "**Unknown Client** wants to access your WordPress site"
3. Review the scope: "Use MCP tools to read and manage your WordPress content..."
4. Click **Authorize**
5. The browser redirects back to Claude Desktop with an authorization code
6. Claude Desktop exchanges the code + PKCE verifier for an access token

**Verify:** Claude Desktop shows the server as "Connected" with a green status indicator.

## Step 3: Tool Verification

Ask Claude Desktop to execute each of the following. Verify the response makes sense.

### 3.1 Ping (health check)
> "Use the ping tool on my WordPress site"

Expected: `{ "message": "pong" }`

### 3.2 Posts
> "List all posts on my WordPress site"

Expected: Paginated list of posts (at least "Hello world!" on a fresh install).

> "Create a draft post titled 'MCP Smoke Test' with content 'This post was created by Claude via MCP.'"

Expected: A post object with `status: "draft"` returned.

> "Get the post you just created"

Expected: The same post, fetched by ID.

### 3.3 Pages
> "List all pages on my WordPress site"

Expected: Paginated list (at least "Sample Page" on a fresh install).

### 3.4 Media
> "Upload this image to my WordPress site: https://picsum.photos/200"

Expected: A media object with `mime_type`, `source_url`, dimensions.

### 3.5 Users
> "Who am I on this WordPress site?"

Expected: Claude calls `users.get-current` and returns your admin user profile.

### 3.6 Comments
> "List all comments on my WordPress site"

Expected: At least the default "Hi, this is a comment" on a fresh install.

### 3.7 Settings
> "What WordPress version is running on this site?"

Expected: Claude calls `settings.get-site-info` and reports the WP version.

### 3.8 Search
> "Search for 'hello' across all content on my site"

Expected: "Hello world!" post appears in results.

### 3.9 Taxonomies
> "List all categories on my site"

Expected: At least "Uncategorized" on a fresh install.

### 3.10 Menus
> "List all navigation menus on my site"

Expected: Empty array on a fresh install (no menus created yet), or a list if menus exist.

## Step 4: Permission Isolation

1. Create a WordPress user with the **Subscriber** role
2. In a new Claude Desktop session, connect to the same MCP server
3. Log in as the Subscriber user during the OAuth consent screen
4. Try the following:

> "Create a new post titled 'Should Fail'"

Expected: Permission denied error (Subscribers cannot `edit_posts`).

> "Who am I on this WordPress site?"

Expected: `users.get-current` succeeds (Subscribers have the `read` capability).

> "List all users"

Expected: Permission denied (Subscribers cannot `list_users`).

## Step 5: Token Revocation

1. In the WordPress admin, go to the plugin settings (or directly delete rows from `wp_mcpwp_clients` in the DB)
2. Revoke the OAuth client that Claude Desktop registered
3. In Claude Desktop, try any tool call

Expected: 401 error. Claude Desktop should prompt to re-authenticate.

## Step 6: Refresh Token

1. Wait for the access token to expire (1 hour), or manually revoke just the access token in the DB
2. Try a tool call

Expected: Claude Desktop silently uses its refresh token to obtain a new access token. The tool call succeeds without prompting for re-login.

## Results Checklist

| # | Test | Pass? |
|---|------|-------|
| 1 | OAuth discovery endpoints respond correctly | |
| 2 | MCP endpoint returns 401 without token | |
| 3 | Claude Desktop completes OAuth flow | |
| 4 | Consent screen renders with correct client info | |
| 5 | Ping tool returns pong | |
| 6 | Posts CRUD works (list, create, get) | |
| 7 | Pages list works | |
| 8 | Media upload works | |
| 9 | Users get-current works | |
| 10 | Comments list works | |
| 11 | Settings get-site-info works | |
| 12 | Search returns results | |
| 13 | Taxonomies list works | |
| 14 | Menus list works | |
| 15 | Subscriber role is denied mutation tools | |
| 16 | Subscriber role can use read-only tools | |
| 17 | Client revocation invalidates tokens | |
| 18 | Refresh token flow works silently | |
