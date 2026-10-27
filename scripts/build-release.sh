#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_SLUG="lead-assistant"
BUILD_DIR="${ROOT_DIR}/build"
STAGE_DIR="${BUILD_DIR}/${PLUGIN_SLUG}"

VERSION="$(sed -nE 's/^ \* Version:[[:space:]]*([^[:space:]]+).*/\1/p' "${ROOT_DIR}/lead-assistant.php" | head -n1)"
if [[ -z "${VERSION}" ]]; then
  echo "Could not determine plugin version." >&2
  exit 1
fi

ZIP_PATH="${BUILD_DIR}/${PLUGIN_SLUG}-${VERSION}.zip"

rm -rf "${BUILD_DIR}"
mkdir -p "${STAGE_DIR}"

# Release allowlist: only runtime/plugin-distribution files enter the ZIP.
cp "${ROOT_DIR}/lead-assistant.php" "${STAGE_DIR}/"
cp "${ROOT_DIR}/readme.txt" "${STAGE_DIR}/"
cp -R "${ROOT_DIR}/includes" "${STAGE_DIR}/includes"
cp -R "${ROOT_DIR}/admin" "${STAGE_DIR}/admin"
cp -R "${ROOT_DIR}/public" "${STAGE_DIR}/public"

# No development artefacts may be packaged.
if find "${STAGE_DIR}" -type f \( -name '.DS_Store' -o -name '*.log' -o -name '*.md' -o -name '*.yml' -o -name '*.yaml' \) | grep -q .; then
  echo "Development artefact found in release staging directory." >&2
  exit 1
fi

(
  cd "${BUILD_DIR}"
  zip -qr "$(basename "${ZIP_PATH}")" "${PLUGIN_SLUG}"
)

printf '%s\n' "${ZIP_PATH}"
