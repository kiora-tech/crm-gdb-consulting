#!/bin/sh
set -e

# Decode base64 JWT keys to PEM files if provided as base64:// env vars
JWT_DIR="/var/www/website/config/jwt"
mkdir -p "$JWT_DIR"

if echo "$JWT_SECRET_KEY" | grep -q "^base64://"; then
    echo "$JWT_SECRET_KEY" | sed 's|^base64://||' | base64 -d > "$JWT_DIR/private.pem"
    export JWT_SECRET_KEY="$JWT_DIR/private.pem"
    echo "[entrypoint] Decoded JWT private key to $JWT_DIR/private.pem"
fi

if echo "$JWT_PUBLIC_KEY" | grep -q "^base64://"; then
    echo "$JWT_PUBLIC_KEY" | sed 's|^base64://||' | base64 -d > "$JWT_DIR/public.pem"
    export JWT_PUBLIC_KEY="$JWT_DIR/public.pem"
    echo "[entrypoint] Decoded JWT public key to $JWT_DIR/public.pem"
fi

exec "$@"
