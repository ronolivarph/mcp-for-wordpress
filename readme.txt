=== MCP for WordPress ===
Contributors: ronolivarph
Tags: mcp, ai, claude, model-context-protocol, oauth
Requires at least: 6.4
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Turns your self-hosted WordPress site into an OAuth 2.1-protected remote MCP server for Claude Desktop and other MCP clients.

== Description ==

MCP for WordPress exposes your WordPress site's content and configuration as MCP (Model Context Protocol) tools that AI assistants like Claude Desktop can discover and use — securely authenticated via OAuth 2.1 with PKCE and Dynamic Client Registration.

**Features:**

* ~70+ MCP tools covering posts, pages, media, taxonomies, users, comments, menus, settings, and search.
* Built-in OAuth 2.1 authorization server with PKCE and Dynamic Client Registration (RFC 7591).
* All tools respect WordPress capabilities — users can only do what their role allows.
* Built on the official WordPress MCP Adapter and Abilities API.

== Installation ==

1. Download the latest release zip from GitHub.
2. In your WordPress admin, go to Plugins → Add New → Upload Plugin.
3. Upload the zip file and activate the plugin.
4. In Claude Desktop, add your site URL as a remote MCP server.
5. Complete the OAuth flow when prompted.

== Changelog ==

= 0.1.0 =
* Initial release.
