#!/usr/bin/env bash
#
# Package contract test.
#
# The App Store tarball must contain exactly the app's runtime files and none of
# the dev-only files. When the app is signed, the signed file set (signature.json)
# must equal the shipped file set — otherwise Nextcloud's integrity check reports
# FILE_MISSING / EXTRA_FILE on install. This test guards both invariants and is
# the regression test for the "sign the dev tree, ship a pruned tree" bug.
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
APP_ID="appdrop"
VERSION=$(grep '<version>' "${APP_DIR}/appinfo/info.xml" | sed 's/.*<version>\(.*\)<\/version>.*/\1/' | tr -d '[:space:]')
TARBALL="${APP_DIR}/build/${APP_ID}-${VERSION}.tar.gz"

fail() { echo "FAIL: $*" >&2; exit 1; }

echo "[test] building package (--package-only)..."
"${APP_DIR}/scripts/prepare-appstore.sh" --package-only >/dev/null

[[ -f "${TARBALL}" ]] || fail "tarball not created at ${TARBALL}"

listing=$(tar tzf "${TARBALL}")

# Top-level directory must be the app id (App Store requirement).
top=$(printf '%s\n' "${listing}" | head -1)
[[ "${top}" == "${APP_ID}/" ]] || fail "top-level dir is '${top}', expected '${APP_ID}/'"

# Runtime files that must always ship.
must_have=(
  "${APP_ID}/appinfo/info.xml"
  "${APP_ID}/appinfo/routes.php"
  "${APP_ID}/lib/AppInfo/Application.php"
  "${APP_ID}/CHANGELOG.md"
)
for f in "${must_have[@]}"; do
  printf '%s\n' "${listing}" | grep -qx "${f}" || fail "missing required file: ${f}"
done

# Dev-only paths that must never ship.
must_not=(
  "${APP_ID}/tests/" "${APP_ID}/scripts/" "${APP_ID}/.git/" "${APP_ID}/.github/"
  "${APP_ID}/certs/" "${APP_ID}/vendor/" "${APP_ID}/node_modules/" "${APP_ID}/screenshots/"
  "${APP_ID}/Makefile" "${APP_ID}/phpunit.xml" "${APP_ID}/psalm.xml"
  "${APP_ID}/composer.json" "${APP_ID}/composer.lock" "${APP_ID}/lefthook.yml"
  "${APP_ID}/krankerl.toml" "${APP_ID}/.nextcloudignore" "${APP_ID}/.gitignore"
  "${APP_ID}/HOW_TO_DEPLOY_TO_APPSTORE.md" "${APP_ID}/CONTRIBUTING.md" "${APP_ID}/SECURITY.md"
)
for p in "${must_not[@]}"; do
  if printf '%s\n' "${listing}" | grep -q "^${p}"; then
    fail "dev-only path leaked into package: ${p}"
  fi
done

# If signed, the signed set MUST equal the shipped set.
if printf '%s\n' "${listing}" | grep -qx "${APP_ID}/appinfo/signature.json"; then
  if command -v python3 >/dev/null 2>&1; then
    echo "[test] verifying signed set == shipped set..."
    tmp=$(mktemp -d)
    tar xzf "${TARBALL}" -C "${tmp}"
    python3 - "${tmp}/${APP_ID}" <<'PY' || { rm -rf "${tmp}"; fail "signed set != shipped set"; }
import json, os, sys
root = sys.argv[1]
hashes = set(json.load(open(os.path.join(root, "appinfo", "signature.json")))["hashes"].keys())
present = set()
for d, _, fs in os.walk(root):
    for f in fs:
        present.add(os.path.relpath(os.path.join(d, f), root))
present.discard("appinfo/signature.json")
miss, extra = sorted(hashes - present), sorted(present - hashes)
if miss or extra:
    for m in miss:
        print("  signed-but-absent:", m)
    for e in extra:
        print("  shipped-but-unsigned:", e)
    sys.exit(1)
print(f"  OK: {len(hashes)} files signed == shipped")
PY
    rm -rf "${tmp}"
  else
    echo "[test] python3 absent — skipping signed-set verification"
  fi
fi

echo "PASS: package contract"
