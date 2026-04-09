#!/usr/bin/env bash
#
# OAuth 2.1 Conformance Test for MCP for WordPress
#
# Walks through the full OAuth flow programmatically:
# 1. Discovery (RFC 9728 + RFC 8414)
# 2. Dynamic Client Registration (RFC 7591)
# 3. Authorization Code + PKCE
# 4. Token Exchange
# 5. Authenticated MCP request
#
# Usage:
#   ./tests/manual/oauth-conformance.sh http://localhost:8888
#
# Requirements: curl, jq, openssl, base64, a running WordPress with the plugin active.
# Note: Step 3 requires manual browser login — the script pauses for you to complete it.

set -euo pipefail

BASE_URL="${1:?Usage: $0 <base-url>}"
echo "=== OAuth 2.1 Conformance Test ==="
echo "Base URL: $BASE_URL"
echo ""

# ─── 1. Protected Resource Metadata (RFC 9728) ───
echo "─── Step 1: Protected Resource Metadata ───"
PRM=$(curl -sf "$BASE_URL/.well-known/oauth-protected-resource")
echo "$PRM" | jq .

RESOURCE=$(echo "$PRM" | jq -r '.resource')
AS_URL=$(echo "$PRM" | jq -r '.authorization_servers[0]')
echo "Resource: $RESOURCE"
echo "Authorization Server: $AS_URL"
echo ""

# ─── 2. Authorization Server Metadata (RFC 8414) ───
echo "─── Step 2: Authorization Server Metadata ───"
ASM=$(curl -sf "$AS_URL/.well-known/oauth-authorization-server")
echo "$ASM" | jq .

AUTH_ENDPOINT=$(echo "$ASM" | jq -r '.authorization_endpoint')
TOKEN_ENDPOINT=$(echo "$ASM" | jq -r '.token_endpoint')
REG_ENDPOINT=$(echo "$ASM" | jq -r '.registration_endpoint')
echo "Authorization Endpoint: $AUTH_ENDPOINT"
echo "Token Endpoint: $TOKEN_ENDPOINT"
echo "Registration Endpoint: $REG_ENDPOINT"

# Verify PKCE S256 is supported
S256_SUPPORTED=$(echo "$ASM" | jq -r '.code_challenge_methods_supported | index("S256")')
if [ "$S256_SUPPORTED" = "null" ]; then
  echo "FAIL: S256 not in code_challenge_methods_supported"
  exit 1
fi
echo "✓ S256 PKCE supported"
echo ""

# ─── 3. Dynamic Client Registration (RFC 7591) ───
echo "─── Step 3: Dynamic Client Registration ───"
REDIRECT_URI="http://localhost:9999/callback"
DCR_RESPONSE=$(curl -sf -X POST "$REG_ENDPOINT" \
  -H "Content-Type: application/json" \
  -d "{
    \"client_name\": \"OAuth Conformance Test\",
    \"redirect_uris\": [\"$REDIRECT_URI\"],
    \"grant_types\": [\"authorization_code\", \"refresh_token\"],
    \"token_endpoint_auth_method\": \"none\"
  }")
echo "$DCR_RESPONSE" | jq .

CLIENT_ID=$(echo "$DCR_RESPONSE" | jq -r '.client_id')
if [ "$CLIENT_ID" = "null" ] || [ -z "$CLIENT_ID" ]; then
  echo "FAIL: DCR did not return a client_id"
  exit 1
fi
echo "✓ Client registered: $CLIENT_ID"
echo ""

# ─── 4. PKCE Challenge ───
echo "─── Step 4: Generate PKCE Parameters ───"
CODE_VERIFIER=$(openssl rand -base64 32 | tr -d '=/+' | head -c 43)
CODE_CHALLENGE=$(printf '%s' "$CODE_VERIFIER" | openssl dgst -sha256 -binary | base64 | tr '+/' '-_' | tr -d '=')
STATE=$(openssl rand -hex 16)

echo "Code Verifier: $CODE_VERIFIER"
echo "Code Challenge: $CODE_CHALLENGE"
echo "State: $STATE"
echo ""

# ─── 5. Authorization Request ───
echo "─── Step 5: Authorization ───"
AUTH_URL="${AUTH_ENDPOINT}?response_type=code&client_id=${CLIENT_ID}&redirect_uri=$(python3 -c "import urllib.parse; print(urllib.parse.quote('$REDIRECT_URI'))" 2>/dev/null || echo "$REDIRECT_URI")&code_challenge=${CODE_CHALLENGE}&code_challenge_method=S256&state=${STATE}&scope=mcp&resource=$(python3 -c "import urllib.parse; print(urllib.parse.quote('$RESOURCE'))" 2>/dev/null || echo "$RESOURCE")"

echo "Open this URL in your browser and complete the login + consent:"
echo ""
echo "  $AUTH_URL"
echo ""
echo "After approving, you'll be redirected to $REDIRECT_URI?code=XXXXX&state=$STATE"
echo "(The redirect will fail since nothing is listening — copy the 'code' parameter from the URL bar)"
echo ""
read -rp "Paste the authorization code here: " AUTH_CODE

if [ -z "$AUTH_CODE" ]; then
  echo "FAIL: No authorization code provided"
  exit 1
fi
echo "✓ Authorization code received"
echo ""

# ─── 6. Token Exchange ───
echo "─── Step 6: Token Exchange ───"
TOKEN_RESPONSE=$(curl -sf -X POST "$TOKEN_ENDPOINT" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "grant_type=authorization_code&code=${AUTH_CODE}&redirect_uri=${REDIRECT_URI}&client_id=${CLIENT_ID}&code_verifier=${CODE_VERIFIER}&resource=${RESOURCE}")
echo "$TOKEN_RESPONSE" | jq .

ACCESS_TOKEN=$(echo "$TOKEN_RESPONSE" | jq -r '.access_token')
REFRESH_TOKEN=$(echo "$TOKEN_RESPONSE" | jq -r '.refresh_token')

if [ "$ACCESS_TOKEN" = "null" ] || [ -z "$ACCESS_TOKEN" ]; then
  echo "FAIL: No access_token in response"
  exit 1
fi
echo "✓ Access token received (${#ACCESS_TOKEN} chars)"
echo "✓ Refresh token received (${#REFRESH_TOKEN} chars)"
echo ""

# ─── 7. Authenticated MCP Request ───
echo "─── Step 7: Authenticated MCP Request ───"
MCP_ENDPOINT="$BASE_URL/wp-json/mcpwp/mcp"

# Send an MCP initialize request
MCP_RESPONSE=$(curl -sf -X POST "$MCP_ENDPOINT" \
  -H "Authorization: Bearer $ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json, text/event-stream" \
  -d '{
    "jsonrpc": "2.0",
    "id": 1,
    "method": "initialize",
    "params": {
      "protocolVersion": "2025-06-18",
      "capabilities": {},
      "clientInfo": { "name": "oauth-conformance-test", "version": "1.0.0" }
    }
  }')
echo "$MCP_RESPONSE" | jq . 2>/dev/null || echo "$MCP_RESPONSE"
echo "✓ MCP request completed"
echo ""

# ─── 8. Verify 401 without token ───
echo "─── Step 8: Verify 401 without token ───"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$MCP_ENDPOINT" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{}}')
if [ "$HTTP_CODE" = "401" ]; then
  echo "✓ Returns 401 without Bearer token"
else
  echo "FAIL: Expected 401, got $HTTP_CODE"
fi
echo ""

# ─── 9. Refresh Token ───
echo "─── Step 9: Refresh Token ───"
if [ "$REFRESH_TOKEN" != "null" ] && [ -n "$REFRESH_TOKEN" ]; then
  REFRESH_RESPONSE=$(curl -sf -X POST "$TOKEN_ENDPOINT" \
    -H "Content-Type: application/x-www-form-urlencoded" \
    -d "grant_type=refresh_token&refresh_token=${REFRESH_TOKEN}&client_id=${CLIENT_ID}&resource=${RESOURCE}")
  NEW_ACCESS=$(echo "$REFRESH_RESPONSE" | jq -r '.access_token')
  if [ "$NEW_ACCESS" != "null" ] && [ -n "$NEW_ACCESS" ]; then
    echo "✓ Refresh token exchanged for new access token"
  else
    echo "FAIL: Refresh token exchange failed"
    echo "$REFRESH_RESPONSE" | jq .
  fi
else
  echo "SKIP: No refresh token to test"
fi
echo ""

echo "=== All conformance checks complete ==="
