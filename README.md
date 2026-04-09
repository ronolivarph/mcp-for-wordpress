# MCP for WordPress

A WordPress plugin that turns your self-hosted WordPress site into an OAuth 2.1-protected remote MCP server for Claude Desktop and other MCP clients.

## What It Does

Once installed, Claude Desktop (or any MCP client) can connect to your WordPress site and use **74 tools** to read and manage your content — all authenticated via OAuth 2.1 and gated by WordPress user roles.

## Features

- **74 MCP tools** across 9 categories: Posts, Pages, Media, Taxonomies, Users, Comments, Menus, Settings, Search
- **OAuth 2.1 authorization server** with PKCE and Dynamic Client Registration (RFC 7591)
- **Discovery endpoints**: `/.well-known/oauth-protected-resource` (RFC 9728) and `/.well-known/oauth-authorization-server` (RFC 8414)
- **WordPress capability enforcement** — users can only do what their role allows
- **Consent screen** — users approve access before any tools are available
- **90-day refresh tokens** — Claude Desktop stays connected without frequent re-auth
- Built on the official [WordPress MCP Adapter](https://github.com/WordPress/mcp-adapter) and Abilities API

## Requirements

- WordPress 6.4+
- PHP 8.1+
- HTTPS (required for OAuth in production; localhost works for development)

## Installation

### From Release ZIP

1. Download `mcp-for-wordpress-0.1.0.zip` from the [GitHub Releases](https://github.com/ronolivarph/mcp-for-wordpress/releases) page
2. In WordPress admin: **Plugins → Add New → Upload Plugin** → select the ZIP
3. Click **Activate**

### From Source

```bash
git clone https://github.com/gohire/mcp-for-wordpress.git
cd mcp-for-wordpress
composer install --no-dev --optimize-autoloader
```

Then symlink or copy the directory into `wp-content/plugins/` and activate.

## Connecting Claude Desktop

1. Open **Claude Desktop → Settings → MCP Servers**
2. Click **Add Server → Remote MCP Server**
3. Enter your site URL: `https://your-site.com/wp-json/mcpwp/mcp`
4. Claude Desktop will open a browser for OAuth login
5. Log in with your WordPress credentials and click **Authorize**
6. Done — Claude can now use your WordPress tools

## Tool Reference

| Category | Tools | Required Capability |
|---|---|---|
| **Posts** (8) | list, get, create, update, delete, list-revisions, restore-revision, autosave | `edit_posts` |
| **Pages** (8) | list, get, create, update, delete, list-revisions, restore-revision, autosave | `edit_pages` |
| **Media** (6) | list, get, upload, update-meta, delete, set-alt-text | `upload_files` |
| **Taxonomies** (12) | CRUD for categories + tags, list-taxonomies, get-terms-for-post | `manage_categories` |
| **Users** (8) | list, get, get-current, create, update, delete, list-app-passwords, change-role | `list_users` / `edit_users` |
| **Comments** (8) | list, get, create, update, delete, approve, mark-spam, trash | `moderate_comments` |
| **Menus** (10) | list, get, create, update, delete, list-items, add-item, update-item, remove-item, assign-location | `edit_theme_options` |
| **Settings** (10) | get-settings, update-setting, list-post-types, list-post-statuses, list-plugins, list-themes, get-active-theme, list-widgets, list-sidebars, get-site-info | `manage_options` |
| **Search** (3) | universal-search, oembed-resolve, fetch-url-meta | `read` |
| **Ping** (1) | ping | `read` |

## Development

```bash
# Install dependencies
docker compose run --rm composer install

# Run unit tests
docker compose run --rm test

# Run linter
docker compose run --rm lint

# Local WordPress environment (requires Node.js for wp-env)
npx wp-env start
npx wp-env run cli wp plugin activate mcp-for-wordpress
```

## Architecture

```
MCP Client (Claude Desktop)
    │
    │ OAuth 2.1 + PKCE
    ▼
┌─────────────────────────────────────┐
│  MCP for WordPress (WP Plugin)      │
│                                     │
│  ┌─────────────┐  ┌──────────────┐  │
│  │ OAuth 2.1   │  │ MCP Server   │  │
│  │ Auth Server │  │ (mcp-adapter)│  │
│  │             │  │              │  │
│  │ • DCR       │  │ • 74 tools   │  │
│  │ • /authorize│  │ • WP cap     │  │
│  │ • /token    │  │   checks     │  │
│  │ • Discovery │  │              │  │
│  └─────────────┘  └──────────────┘  │
│           │               │         │
│           └───────┬───────┘         │
│                   │                 │
│          WordPress Core APIs        │
│    (wp_insert_post, get_terms, ...) │
└─────────────────────────────────────┘
```

## OAuth Flow

1. Client requests MCP endpoint → **401** with `WWW-Authenticate: Bearer resource_metadata="..."`
2. Client fetches `/.well-known/oauth-protected-resource` → protected resource metadata (RFC 9728)
3. Client fetches `/.well-known/oauth-authorization-server` → authorization server metadata (RFC 8414)
4. Client registers via DCR: `POST /wp-json/mcpwp/v1/oauth/register` (RFC 7591)
5. Client redirects user to `/wp-json/mcpwp/v1/oauth/authorize` with PKCE challenge
6. User logs in and approves on consent screen
7. Client exchanges code at `/wp-json/mcpwp/v1/oauth/token`
8. Client sends MCP requests with `Authorization: Bearer <token>`

## License

GPL-2.0-or-later
