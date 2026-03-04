# How to Deploy to the Nextcloud App Store

Step-by-step guide for publishing `appdrop` to the [Nextcloud App Store](https://apps.nextcloud.com).

---

## Prerequisites

- An account at [apps.nextcloud.com](https://apps.nextcloud.com) (register with GitHub or Nextcloud account)
- `openssl` installed locally
- Docker with the Nextcloud containers running (`./start.sh`)
- A public GitHub repository to host release tarballs

---

## Quick Path (using the script)

The `scripts/prepare-appstore.sh` script automates key generation, signing, and packaging.

### First-time setup

```bash
cd custom_apps/appdrop

# 1. Generate private key + CSR
./scripts/prepare-appstore.sh --keys-only
```

The script will:
- Generate a 2048-bit RSA private key at `certs/appdrop.key`
- Generate a CSR at `certs/appdrop.csr`
- Print the CSR content for you to copy

Then:
1. Go to https://apps.nextcloud.com/developer/apps/certificates
2. Paste the CSR content
3. Copy the returned certificate
4. Save it to `certs/appdrop.crt`

### Sign & package

```bash
# 2. Sign the app and create the .tar.gz
./scripts/prepare-appstore.sh --sign-only
```

This will:
- Copy key + cert into the container
- Run `occ integrity:sign-app` to generate `appinfo/signature.json`
- Build `build/appdrop-<version>.tar.gz`
- Print the release signature for API upload

### Upload to App Store

```bash
# 3. Create a GitHub release
git tag v1.2.0
git push origin v1.2.0
gh release create v1.2.0 \
  --title "v1.2.0" \
  --notes "See CHANGELOG.md for details" \
  build/appdrop-1.2.0.tar.gz

# 4. Get the release signature
./scripts/prepare-appstore.sh --signature

# 5. Submit to App Store
curl -X POST https://apps.nextcloud.com/api/v1/apps/releases \
  -H "Authorization: Token YOUR_APPSTORE_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "download": "https://github.com/cdntv/appdrop/releases/download/v1.2.0/appdrop-1.2.0.tar.gz",
    "signature": "PASTE_SIGNATURE_HERE",
    "nightly": false
  }'
```

Get your API token at: https://apps.nextcloud.com/account/token

### Future releases

For each new version:

```bash
# 1. Bump version in appinfo/info.xml
# 2. Update CHANGELOG.md
# 3. Sign + package
./scripts/prepare-appstore.sh --sign-only
# 4. Create GitHub release + submit to API (steps 3-5 above)
```

### Script reference

| Command | Description |
|---|---|
| `./scripts/prepare-appstore.sh` | Full flow (keygen if needed → sign → package → signature) |
| `./scripts/prepare-appstore.sh --keys-only` | Only generate private key + CSR |
| `./scripts/prepare-appstore.sh --sign-only` | Sign app + build .tar.gz (key + cert must exist) |
| `./scripts/prepare-appstore.sh --package-only` | Only build .tar.gz (app already signed) |
| `./scripts/prepare-appstore.sh --signature` | Print the release signature for API upload |

---

## Manual Path (without the script)

### Step 1: Generate signing key

```bash
mkdir -p certs

# Generate private key (NEVER commit this)
openssl genrsa -out certs/appdrop.key 2048
chmod 600 certs/appdrop.key

# Generate CSR
openssl req -new \
  -key certs/appdrop.key \
  -out certs/appdrop.csr \
  -subj "/CN=appdrop"
```

### Step 2: Get certificate from Nextcloud

1. Go to https://apps.nextcloud.com/developer/apps/certificates
2. Paste the content of `certs/appdrop.csr`
3. Save the returned certificate to `certs/appdrop.crt`

### Step 3: Sign the app

The signing must happen inside the Nextcloud container where `occ` is available.

```bash
# Copy key and cert into the container
docker compose -f .runtime/docker-compose.yml -p nextcloud \
  cp certs/appdrop.key app:/tmp/appdrop.key
docker compose -f .runtime/docker-compose.yml -p nextcloud \
  cp certs/appdrop.crt app:/tmp/appdrop.crt

# Sign the app
docker compose -f .runtime/docker-compose.yml -p nextcloud \
  exec -T -u www-data app php occ integrity:sign-app \
    --privateKey=/tmp/appdrop.key \
    --certificate=/tmp/appdrop.crt \
    --path=/var/www/html/custom_apps/appdrop

# Clean up keys from container
docker compose -f .runtime/docker-compose.yml -p nextcloud \
  exec -T app rm -f /tmp/appdrop.key /tmp/appdrop.crt

# Copy signature.json back to local
docker compose -f .runtime/docker-compose.yml -p nextcloud \
  cp app:/var/www/html/custom_apps/appdrop/appinfo/signature.json \
  custom_apps/appdrop/appinfo/signature.json
```

### Step 4: Build the tarball

The App Store requires `.tar.gz` format. The tarball must contain a single top-level directory matching the app ID.

```bash
cd custom_apps

mkdir -p appdrop/build/appdrop

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
  appdrop/ appdrop/build/appdrop/

cd appdrop/build
tar -czf appdrop-1.2.0.tar.gz appdrop
rm -rf appdrop/

# Verify the tarball structure
tar tzf appdrop-1.2.0.tar.gz | head -15
```

Expected output:
```
appdrop/
appdrop/appinfo/
appdrop/appinfo/info.xml
appdrop/appinfo/routes.php
appdrop/appinfo/signature.json
...
```

### Step 5: Create GitHub release

```bash
git tag v1.2.0
git push origin v1.2.0

gh release create v1.2.0 \
  --title "v1.2.0" \
  --notes "See CHANGELOG.md for details" \
  custom_apps/appdrop/build/appdrop-1.2.0.tar.gz
```

The download URL will be:
```
https://github.com/cdntv/appdrop/releases/download/v1.2.0/appdrop-1.2.0.tar.gz
```

### Step 6: Generate release signature

```bash
openssl dgst -sha512 \
  -sign certs/appdrop.key \
  build/appdrop-1.2.0.tar.gz | openssl base64 -A
```

Copy the output — this is your release signature.

### Step 7: Submit to App Store

**Option A: API (recommended)**

Get your API token at https://apps.nextcloud.com/account/token, then:

```bash
curl -X POST https://apps.nextcloud.com/api/v1/apps/releases \
  -H "Authorization: Token YOUR_APPSTORE_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "download": "https://github.com/cdntv/appdrop/releases/download/v1.2.0/appdrop-1.2.0.tar.gz",
    "signature": "PASTE_SIGNATURE_HERE",
    "nightly": false
  }'
```

**Option B: Web interface**

1. Go to https://apps.nextcloud.com/developer/apps/releases/new
2. Enter the download URL of the `.tar.gz`
3. Paste the release signature
4. Submit

### Step 8: Add screenshots (recommended)

Before or after publishing, add screenshots to improve visibility:

1. Take screenshots of the app UI (upload, permissions, generator, etc.)
2. Add them to a `screenshots/` directory in the repository
3. Push to GitHub
4. Add `<screenshot>` elements to `info.xml`:

```xml
<screenshot>https://raw.githubusercontent.com/cdntv/appdrop/main/screenshots/upload.png</screenshot>
<screenshot>https://raw.githubusercontent.com/cdntv/appdrop/main/screenshots/permissions.png</screenshot>
```

---

## Directory structure

After setup, the signing-related files live in:

```
certs/                          ← git-ignored, NEVER commit
├── appdrop.key    ← Private key (keep safe!)
├── appdrop.csr    ← CSR (submitted to Nextcloud)
└── appdrop.crt    ← Certificate (from Nextcloud)

build/                          ← git-ignored
└── appdrop-1.2.0.tar.gz   ← Release tarball

scripts/
└── prepare-appstore.sh         ← Automation script
```

## Important notes

- **Never commit the private key** (`certs/` is git-ignored)
- The `appinfo/signature.json` **must** be included in the tarball — it IS committed
- The App Store validates `info.xml` fields — ensure `<version>`, `<licence>`, `<dependencies>` are correct
- Each new version requires re-signing and a new release signature
- The tarball must have the app ID as the top-level directory name
- Keep your private key backed up securely — if lost, you need to generate a new one and re-register the certificate
