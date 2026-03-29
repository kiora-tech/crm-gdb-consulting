#!/bin/bash
#
# Migrate files from MinIO to Garage S3
#
# MinIO data is on disk at /srv/swarm-data/prod/crm-gdb/minio/
# Garage S3 endpoint is at 192.168.1.74:3900
#
# This script uses mc (MinIO client) to upload files from the local
# filesystem to the Garage S3 bucket with proper prefixes.
#
# Structure:
#   MinIO bucket gdbcrmdocuments/* → Garage crm-gbd-consulting/documents/*
#   MinIO bucket gdbcrmtemplates/* → Garage crm-gbd-consulting/templates/*
#

set -euo pipefail

# Garage S3 config — set these via environment variables or a .env file
S3_ENDPOINT="${S3_ENDPOINT:?S3_ENDPOINT is required}"
S3_BUCKET="${S3_BUCKET:?S3_BUCKET is required}"
S3_ACCESS_KEY="${S3_ACCESS_KEY:?S3_ACCESS_KEY is required}"
S3_SECRET_KEY="${S3_SECRET_KEY:?S3_SECRET_KEY is required}"

# MinIO data on disk
MINIO_DATA="/srv/swarm-data/prod/crm-gdb/minio"

echo "=== Migration MinIO → Garage S3 ==="
echo ""

# Check mc is available
if ! command -v mc &> /dev/null; then
    echo "Installing mc (MinIO client)..."
    curl -sL https://dl.min.io/client/mc/release/linux-arm64/mc -o /tmp/mc
    chmod +x /tmp/mc
    MC="/tmp/mc"
else
    MC="mc"
fi

# Configure Garage alias
echo "[1/5] Configuring Garage S3 alias..."
$MC alias set garage "$S3_ENDPOINT" "$S3_ACCESS_KEY" "$S3_SECRET_KEY" --api S3v4 2>&1

# Verify connectivity
echo ""
echo "[2/5] Verifying Garage connectivity..."
$MC ls garage/ 2>&1 || { echo "ERROR: Cannot connect to Garage"; exit 1; }

# Count source files
echo ""
echo "[3/5] Counting source files..."
DOC_COUNT=$(find "$MINIO_DATA/gdbcrmdocuments" -type f ! -path '*/.minio.sys/*' | wc -l)
TPL_COUNT=$(find "$MINIO_DATA/gdbcrmtemplates" -type f ! -path '*/.minio.sys/*' | wc -l)
echo "  Documents: $DOC_COUNT files"
echo "  Templates: $TPL_COUNT files"

# Upload documents
echo ""
echo "[4/5] Uploading documents to garage/${S3_BUCKET}/documents/ ..."
$MC mirror --exclude "**/.minio.sys/**" "$MINIO_DATA/gdbcrmdocuments/" "garage/${S3_BUCKET}/documents/" 2>&1

# Upload templates
echo ""
echo "[5/5] Uploading templates to garage/${S3_BUCKET}/templates/ ..."
$MC mirror --exclude "**/.minio.sys/**" "$MINIO_DATA/gdbcrmtemplates/" "garage/${S3_BUCKET}/templates/" 2>&1

# Verify
echo ""
echo "=== Verification ==="
GARAGE_DOC_COUNT=$($MC ls --recursive "garage/${S3_BUCKET}/documents/" 2>/dev/null | wc -l)
GARAGE_TPL_COUNT=$($MC ls --recursive "garage/${S3_BUCKET}/templates/" 2>/dev/null | wc -l)
echo "  Source documents: $DOC_COUNT → Garage documents: $GARAGE_DOC_COUNT"
echo "  Source templates: $TPL_COUNT → Garage templates: $GARAGE_TPL_COUNT"

if [ "$DOC_COUNT" -eq "$GARAGE_DOC_COUNT" ] && [ "$TPL_COUNT" -eq "$GARAGE_TPL_COUNT" ]; then
    echo ""
    echo "=== Migration OK — all files transferred ==="
else
    echo ""
    echo "=== WARNING: file count mismatch! Check manually ==="
    exit 1
fi
