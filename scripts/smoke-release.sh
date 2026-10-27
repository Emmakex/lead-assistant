#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_SLUG="lead-assistant"
BUILD_DIR="${ROOT_DIR}/build"

VERSION_HEADER="$(sed -nE 's/^ \* Version:[[:space:]]*([^[:space:]]+).*/\1/p' "${ROOT_DIR}/lead-assistant.php" | head -n1)"
VERSION_CONST="$(sed -nE "s/^define\( 'LEAD_ASSISTANT_VERSION', '([^']+)' \);/\1/p" "${ROOT_DIR}/lead-assistant.php" | head -n1)"
STABLE_TAG="$(sed -nE 's/^Stable tag:[[:space:]]*([^[:space:]]+).*/\1/p' "${ROOT_DIR}/readme.txt" | head -n1)"
ZIP_PATH="${BUILD_DIR}/${PLUGIN_SLUG}-${VERSION_HEADER}.zip"

if [[ -z "${VERSION_HEADER}" || -z "${VERSION_CONST}" || -z "${STABLE_TAG}" ]]; then
  echo "Could not read release version metadata." >&2
  exit 1
fi

if [[ "${VERSION_HEADER}" != "${VERSION_CONST}" || "${VERSION_HEADER}" != "${STABLE_TAG}" ]]; then
  echo "Version mismatch: header=${VERSION_HEADER} constant=${VERSION_CONST} stable=${STABLE_TAG}" >&2
  exit 1
fi

if [[ ! -f "${ZIP_PATH}" ]]; then
  echo "Release ZIP not found: ${ZIP_PATH}" >&2
  exit 1
fi

LISTING="$(unzip -Z1 "${ZIP_PATH}")"

required=(
  "${PLUGIN_SLUG}/lead-assistant.php"
  "${PLUGIN_SLUG}/readme.txt"
  "${PLUGIN_SLUG}/includes/"
  "${PLUGIN_SLUG}/admin/"
  "${PLUGIN_SLUG}/public/"
)

for path in "${required[@]}"; do
  if ! grep -Fxq "${path}" <<<"${LISTING}"; then
    echo "Required release path missing: ${path}" >&2
    exit 1
  fi
done

# The ZIP must have one installable top-level directory and no development material.
if awk -F/ 'NF && $1 != "lead-assistant" { exit 1 }' <<<"${LISTING}"; then
  :
else
  echo "ZIP contains files outside the lead-assistant root directory." >&2
  exit 1
fi

if grep -Eq '(^|/)(\.git|\.github|docs|scripts|build)(/|$)|(^|/)README\.md$|\.DS_Store$|\.log$' <<<"${LISTING}"; then
  echo "Development-only content found in release ZIP." >&2
  exit 1
fi

TMP_DIR="$(mktemp -d)"
trap 'rm -rf "${TMP_DIR}"' EXIT
unzip -q "${ZIP_PATH}" -d "${TMP_DIR}"

while IFS= read -r -d '' file; do
  php -l "${file}" >/dev/null
done < <(find "${TMP_DIR}/${PLUGIN_SLUG}" -type f -name '*.php' -print0)

while IFS= read -r -d '' file; do
  node --check "${file}" >/dev/null
done < <(find "${TMP_DIR}/${PLUGIN_SLUG}/public" -type f -name '*.js' -print0)

if grep -RInE '(sk-[A-Za-z0-9_-]{20,}|OPENAI_API_KEY[[:space:]]*=)' "${TMP_DIR}/${PLUGIN_SLUG}" >/dev/null 2>&1; then
  echo "Potential committed credential found inside release ZIP." >&2
  exit 1
fi

echo "Release smoke passed: ${ZIP_PATH}"
