#!/bin/sh
# Generate the SeaweedFS S3 identity file from environment variables, then
# start a single-node `weed mini` with the S3 API on $S3_PORT (default 9000).
# Secrets stay out of the image and out of git.
set -eu

CONFIG_DIR="${S3_CONFIG_DIR:-/etc/seaweedfs}"
CONFIG_FILE="${CONFIG_DIR}/s3.json"
DATA_DIR="${S3_DATA_DIR:-/data}"
BUCKET="${S3_BUCKET:-incident-photos}"
S3_PORT="${S3_PORT:-9000}"

if [ -z "${S3_ACCESS_KEY:-}" ] || [ -z "${S3_SECRET_KEY:-}" ]; then
  echo "fogos.s3: S3_ACCESS_KEY and S3_SECRET_KEY must be set" >&2
  exit 1
fi

json_escape() {
  printf '%s' "$1" | sed 's/\\/\\\\/g; s/"/\\"/g'
}

ACCESS_KEY=$(json_escape "$S3_ACCESS_KEY")
SECRET_KEY=$(json_escape "$S3_SECRET_KEY")
BUCKET_ESC=$(json_escape "$BUCKET")

ADMIN_BLOCK=""
if [ -n "${S3_ADMIN_ACCESS_KEY:-}" ] && [ -n "${S3_ADMIN_SECRET_KEY:-}" ]; then
  ADMIN_KEY=$(json_escape "$S3_ADMIN_ACCESS_KEY")
  ADMIN_SECRET=$(json_escape "$S3_ADMIN_SECRET_KEY")
  ADMIN_BLOCK=$(printf '%s\n' \
    "    ,{" \
    "      \"name\": \"fogos-admin\"," \
    "      \"credentials\": [{\"accessKey\": \"${ADMIN_KEY}\", \"secretKey\": \"${ADMIN_SECRET}\"}]," \
    "      \"actions\": [\"Admin\", \"Read\", \"Write\", \"List\", \"Tagging\"]" \
    "    }")
fi

mkdir -p "$CONFIG_DIR" "$DATA_DIR"

cat > "$CONFIG_FILE" <<EOF_CFG
{
  "identities": [
    {
      "name": "anonymous",
      "actions": ["Read:${BUCKET_ESC}"]
    },
    {
      "name": "fogos-app",
      "credentials": [
        {
          "accessKey": "${ACCESS_KEY}",
          "secretKey": "${SECRET_KEY}"
        }
      ],
      "actions": [
        "Read:${BUCKET_ESC}",
        "Write:${BUCKET_ESC}",
        "List:${BUCKET_ESC}",
        "Tagging:${BUCKET_ESC}",
        "WriteAcp:${BUCKET_ESC}",
        "ReadAcp:${BUCKET_ESC}"
      ]
    }
${ADMIN_BLOCK}
  ]
}
EOF_CFG
chmod 600 "$CONFIG_FILE"
# The official image runs weed as user `seaweed` (uid 1000). If we wrote the
# config as root, that user cannot read a 0600 file.
if [ "$(id -u)" = "0" ] && id seaweed >/dev/null 2>&1; then
  chown seaweed:seaweed "$CONFIG_FILE" || true
fi

# `weed mini` is the supported single-node all-in-one. Extra surfaces that
# Fogos does not need are turned off: Admin UI, WebDAV, Iceberg, and Lance.
# The filer HTTP port still listens on the container network but does not
# expose directory listings.
# Extra CLI flags can be passed through (used by local smoke tests to move
# default ports, and available in compose via `command:` if ever needed).
IP_ARGS=""
if [ -n "${S3_IP:-}" ]; then
  IP_ARGS="-ip=${S3_IP}"
fi

# word-splitting IP_ARGS is intentional (empty or a single -ip= flag)
# shellcheck disable=SC2086
set -- mini \
  -dir="$DATA_DIR" \
  -s3.port="$S3_PORT" \
  -s3.config="$CONFIG_FILE" \
  -bucket="$BUCKET" \
  -admin.ui=false \
  -webdav=false \
  -s3.port.iceberg=0 \
  -s3.port.lance=0 \
  -filer.exposeDirectoryData=false \
  $IP_ARGS \
  "$@"

# Prefer the image's official entrypoint so /data is chowned to uid 1000
# and weed is not left running as root. Fall back to `weed` on PATH for
# local smoke tests that do not have that image entrypoint.
if [ -x /entrypoint.sh ]; then
  exec /entrypoint.sh "$@"
fi
if command -v weed >/dev/null 2>&1; then
  exec weed "$@"
fi

echo "fogos.s3: weed binary not found on PATH" >&2
exit 1
