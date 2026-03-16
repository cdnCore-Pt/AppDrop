#!/usr/bin/env bash

# SPDX-FileCopyrightText: 2026 CDNTV
# SPDX-License-Identifier: AGPL-3.0-or-later

#
# prepare-appstore.sh — Generate signing keys, sign, and package the app
#                        for Nextcloud App Store submission.
#
# Usage:
#   ./scripts/prepare-appstore.sh                  # Full flow: keygen + sign + package
#   ./scripts/prepare-appstore.sh --keys-only      # Only generate keys + CSR
#   ./scripts/prepare-appstore.sh --sign-only      # Only sign + package (keys must exist)
#   ./scripts/prepare-appstore.sh --package-only    # Only build .tar.gz (already signed)
#   ./scripts/prepare-appstore.sh --signature       # Print the release signature for API upload
#
# Prerequisites:
#   - openssl (for key generation and release signature)
#   - Docker running with Nextcloud containers up (for occ integrity:sign-app)
#   - The .crt certificate obtained from apps.nextcloud.com (see step 3 below)
#

set -euo pipefail

# ── Config ────────────────────────────────────────────────────────────────────

APP_ID="appdrop"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
PROJECT_ROOT="$(cd "${APP_DIR}/../.." && pwd)"
KEYS_DIR="${APP_DIR}/certs"

PRIVATE_KEY="${KEYS_DIR}/${APP_ID}.key"
CSR_FILE="${KEYS_DIR}/${APP_ID}.csr"
CERT_FILE="${KEYS_DIR}/${APP_ID}.crt"

BUILD_DIR="${APP_DIR}/build"
VERSION=$(grep '<version>' "${APP_DIR}/appinfo/info.xml" | sed 's/.*<version>\(.*\)<\/version>.*/\1/' | tr -d '[:space:]')
TARBALL="${BUILD_DIR}/${APP_ID}-${VERSION}.tar.gz"

# Docker compose command (adjust if needed)
COMPOSE_FILE="${PROJECT_ROOT}/.runtime/docker-compose.yml"
COMPOSE_CMD="docker compose -f ${COMPOSE_FILE} -p nextcloud"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# ── Helpers ───────────────────────────────────────────────────────────────────

info()  { echo -e "${BLUE}[INFO]${NC}  $*"; }
ok()    { echo -e "${GREEN}[OK]${NC}    $*"; }
warn()  { echo -e "${YELLOW}[WARN]${NC}  $*"; }
err()   { echo -e "${RED}[ERROR]${NC} $*" >&2; }
die()   { err "$*"; exit 1; }

check_openssl() {
    command -v openssl >/dev/null 2>&1 || die "openssl is required but not installed."
}

check_docker() {
    if ! ${COMPOSE_CMD} ps --format '{{.Name}}' 2>/dev/null | grep -q 'app'; then
        die "Nextcloud containers are not running. Start them with ./start.sh first."
    fi
}

# ── Step 1: Generate private key + CSR ────────────────────────────────────────

generate_keys() {
    check_openssl
    mkdir -p "${KEYS_DIR}"

    if [[ -f "${PRIVATE_KEY}" ]]; then
        warn "Private key already exists at ${PRIVATE_KEY}"
        read -rp "Overwrite? (y/N) " answer
        [[ "${answer}" =~ ^[Yy]$ ]] || { info "Keeping existing key."; return 0; }
    fi

    info "Generating 4096-bit RSA private key..."
    openssl genrsa -out "${PRIVATE_KEY}" 4096
    chmod 600 "${PRIVATE_KEY}"
    ok "Private key saved to ${PRIVATE_KEY}"

    info "Generating Certificate Signing Request (CSR)..."
    openssl req -new \
        -key "${PRIVATE_KEY}" \
        -out "${CSR_FILE}" \
        -subj "/CN=${APP_ID}"
    ok "CSR saved to ${CSR_FILE}"

    echo ""
    echo "============================================================"
    echo "  NEXT STEP: Submit this CSR to the Nextcloud App Store"
    echo "============================================================"
    echo ""
    echo "  1. Go to: https://apps.nextcloud.com/developer/apps/certificates"
    echo "  2. Paste the following CSR content:"
    echo ""
    echo "------- CSR START -------"
    cat "${CSR_FILE}"
    echo "------- CSR END ---------"
    echo ""
    echo "  3. Save the returned certificate to:"
    echo "     ${CERT_FILE}"
    echo ""
    echo "  4. Then re-run this script to sign and package."
    echo "============================================================"
}

# ── Step 2: Sign the app with occ ─────────────────────────────────────────────

sign_app() {
    [[ -f "${PRIVATE_KEY}" ]] || die "Private key not found at ${PRIVATE_KEY}. Run with --keys-only first."
    [[ -f "${CERT_FILE}" ]]   || die "Certificate not found at ${CERT_FILE}. Download it from apps.nextcloud.com after submitting the CSR."

    check_docker

    info "Copying key and certificate into container..."
    ${COMPOSE_CMD} cp "${PRIVATE_KEY}" app:/tmp/${APP_ID}.key
    ${COMPOSE_CMD} cp "${CERT_FILE}"   app:/tmp/${APP_ID}.crt

    info "Signing app with occ integrity:sign-app..."
    ${COMPOSE_CMD} exec -T -u www-data app php occ integrity:sign-app \
        --privateKey=/tmp/${APP_ID}.key \
        --certificate=/tmp/${APP_ID}.crt \
        --path=/var/www/html/custom_apps/${APP_ID}

    info "Cleaning up temporary files in container..."
    ${COMPOSE_CMD} exec -T app rm -f /tmp/${APP_ID}.key /tmp/${APP_ID}.crt

    info "Copying signature.json back from container..."
    ${COMPOSE_CMD} cp app:/var/www/html/custom_apps/${APP_ID}/appinfo/signature.json \
        "${APP_DIR}/appinfo/signature.json"

    ok "App signed successfully. signature.json updated."
}

# ── Step 3: Build .tar.gz package ─────────────────────────────────────────────

build_package() {
    info "Building release package v${VERSION}..."

    rm -rf "${BUILD_DIR}"
    mkdir -p "${BUILD_DIR}/${APP_ID}"

    rsync -a \
        --exclude='build' \
        --exclude='certs' \
        --exclude='scripts' \
        --exclude='.git' \
        --exclude='.github' \
        --exclude='tests' \
        --exclude='node_modules' \
        --exclude='vendor' \
        --exclude='psalm.xml' \
        --exclude='phpunit.xml' \
        --exclude='.php-cs-fixer.dist.php' \
        --exclude='.php-cs-fixer.cache' \
        --exclude='composer.lock' \
        --exclude='composer.json' \
        --exclude='Makefile' \
        --exclude='krankerl.toml' \
        --exclude='.nextcloudignore' \
        --exclude='.gitignore' \
        --exclude='HOW_TO_DEPLOY_TO_APPSTORE.md' \
        --exclude='article-nextcloud-custom-app.md' \
        --exclude='CONTRIBUTING.md' \
        --exclude='SECURITY.md' \
        --exclude='.phpunit.result.cache' \
        --exclude='screenshots' \
        "${APP_DIR}/" "${BUILD_DIR}/${APP_ID}/"

    cd "${BUILD_DIR}"
    tar -czf "${APP_ID}-${VERSION}.tar.gz" "${APP_ID}"
    rm -rf "${BUILD_DIR}/${APP_ID}"

    ok "Package created: ${TARBALL}"
    echo "    Size: $(du -h "${TARBALL}" | cut -f1)"
}

# ── Step 4: Generate release signature (for API upload) ───────────────────────

print_signature() {
    [[ -f "${PRIVATE_KEY}" ]] || die "Private key not found at ${PRIVATE_KEY}."
    [[ -f "${TARBALL}" ]]     || die "Tarball not found at ${TARBALL}. Run --package-only first."

    check_openssl

    info "Generating release signature for API upload..."
    echo ""

    SIGNATURE=$(openssl dgst -sha512 -sign "${PRIVATE_KEY}" "${TARBALL}" | openssl base64 -A)

    echo "============================================================"
    echo "  Release signature (use with App Store API)"
    echo "============================================================"
    echo ""
    echo "${SIGNATURE}"
    echo ""
    echo "============================================================"
    echo ""
    echo "  Upload command:"
    echo ""
    echo "  curl -X POST https://apps.nextcloud.com/api/v1/apps/releases \\"
    echo "    -H 'Authorization: Token YOUR_APPSTORE_TOKEN' \\"
    echo "    -H 'Content-Type: application/json' \\"
    echo "    -d '{\"download\": \"TARBALL_DOWNLOAD_URL\", \"signature\": \"${SIGNATURE}\", \"nightly\": false}'"
    echo ""
}

# ── Main ──────────────────────────────────────────────────────────────────────

main() {
    echo ""
    echo "=========================================="
    echo "  ${APP_ID} — App Store Preparation"
    echo "  Version: ${VERSION}"
    echo "=========================================="
    echo ""

    case "${1:-}" in
        --keys-only)
            generate_keys
            ;;
        --sign-only)
            sign_app
            build_package
            ;;
        --package-only)
            build_package
            ;;
        --signature)
            print_signature
            ;;
        *)
            # Full flow
            if [[ ! -f "${PRIVATE_KEY}" ]]; then
                generate_keys
                echo ""
                warn "Certificate (.crt) is needed before signing."
                warn "After saving the certificate to ${CERT_FILE}, re-run:"
                warn "  ./scripts/prepare-appstore.sh --sign-only"
                exit 0
            fi

            if [[ ! -f "${CERT_FILE}" ]]; then
                warn "Certificate not found at ${CERT_FILE}"
                warn "Submit the CSR to https://apps.nextcloud.com/developer/apps/certificates"
                warn "Save the returned certificate to ${CERT_FILE}"
                warn "Then re-run: ./scripts/prepare-appstore.sh --sign-only"
                exit 0
            fi

            sign_app
            build_package
            print_signature

            echo ""
            ok "All done! Your app is ready for App Store submission."
            echo ""
            ;;
    esac
}

main "$@"
