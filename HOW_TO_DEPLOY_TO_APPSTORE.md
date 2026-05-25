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
- Generate a 4096-bit RSA private key at `certs/appdrop.key`
- Generate a CSR at `certs/appdrop.csr`
- Print the CSR content for you to copy

Then get the certificate **via pull request** (there is no web form):
1. Open a PR adding `appdrop/appdrop.csr` to
   https://github.com/nextcloud/app-certificate-requests
   (the path must be `APP_ID/APP_ID.csr`, i.e. `appdrop/appdrop.csr`)
2. Paste the CSR content; optionally link the app source in the PR description
3. Wait for a maintainer to merge (manual review — they verify you own the app id)
4. After merge, download the issued `appdrop/appdrop.crt` from that repo and save it to `certs/appdrop.crt`:
   ```bash
   curl -sL -o certs/appdrop.crt \
     https://raw.githubusercontent.com/nextcloud/app-certificate-requests/master/appdrop/appdrop.crt
   ```

### Sign & package

```bash
# 2. Sign the app and create the .tar.gz
./scripts/prepare-appstore.sh --sign-only
```

This will:
- Stage the shipped tree from `.nextcloudignore` into `build/appdrop`
- Run `occ integrity:sign-app` on the **staged** tree to generate `appinfo/signature.json`
- Verify the signed file set equals the shipped file set
- Build `build/appdrop-<version>.tar.gz`

(The release signature for API upload is printed separately by `--signature`, step 4 below.)

### Register the app (first time only)

Before your **first** release you must register the app id on the store (binds
`appdrop` to your certificate). This is required once, after the certificate is issued:

```bash
# Print the signature over the app id
./scripts/prepare-appstore.sh --register-sig
```

Then register — the web form is easiest (the certificate contains newlines):
- **Web:** https://apps.nextcloud.com/developer/apps/new — paste the contents of
  `certs/appdrop.crt` and the signature printed above.
- **API:** `POST https://apps.nextcloud.com/api/v1/apps` with a JSON body
  `{"certificate": "<certs/appdrop.crt>", "signature": "<register-sig>"}`.

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
    "download": "https://github.com/cdnCore-Pt/AppDrop/releases/download/v1.2.0/appdrop-1.2.0.tar.gz",
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
| `./scripts/prepare-appstore.sh` | Full flow (keygen if needed → stage → sign → package → signature) |
| `./scripts/prepare-appstore.sh --keys-only` | Only generate private key + CSR |
| `./scripts/prepare-appstore.sh --sign-only` | Stage + sign the staged tree + build .tar.gz (key + cert must exist) |
| `./scripts/prepare-appstore.sh --package-only` | Stage + build .tar.gz (app already signed) |
| `./scripts/prepare-appstore.sh --signature` | Print the release signature for API upload |
| `./scripts/prepare-appstore.sh --register-sig` | Print the one-time "Register app" signature |

---

## Manual Path (without the script)

### Step 1: Generate signing key

```bash
mkdir -p certs

# Generate private key (NEVER commit this)
openssl genrsa -out certs/appdrop.key 4096
chmod 600 certs/appdrop.key

# Generate CSR
openssl req -new \
  -key certs/appdrop.key \
  -out certs/appdrop.csr \
  -subj "/CN=appdrop"
```

### Step 2: Get certificate (pull request)

1. Open a PR adding `appdrop/appdrop.csr` (path `APP_ID/APP_ID.csr`) to
   https://github.com/nextcloud/app-certificate-requests
2. Paste the content of `certs/appdrop.csr`; optionally link the app source
3. Wait for a maintainer to merge (manual review of ownership)
4. After merge, save the issued certificate to `certs/appdrop.crt`:
   ```bash
   curl -sL -o certs/appdrop.crt \
     https://raw.githubusercontent.com/nextcloud/app-certificate-requests/master/appdrop/appdrop.crt
   ```

### Step 3: Stage the package tree

Stage exactly what will ship, using the single exclude list (`.nextcloudignore`).
You sign and ship the **same** tree, so the integrity check cannot fail later.

```bash
cd custom_apps/appdrop
mkdir -p build/appdrop
rsync -a --exclude-from=.nextcloudignore ./ build/appdrop/
```

### Step 4: Sign the staged tree

Signing happens inside the Nextcloud container where `occ` is available, and must
point at the **staged** tree (`build/appdrop`), not the source. Signing the source
makes `signature.json` list files that aren't shipped → `FILE_MISSING` at install.

```bash
docker compose -f .runtime/docker-compose.yml -p nextcloud cp certs/appdrop.key app:/tmp/appdrop.key
docker compose -f .runtime/docker-compose.yml -p nextcloud cp certs/appdrop.crt app:/tmp/appdrop.crt

docker compose -f .runtime/docker-compose.yml -p nextcloud \
  exec -T -u www-data app php occ integrity:sign-app \
    --privateKey=/tmp/appdrop.key \
    --certificate=/tmp/appdrop.crt \
    --path=/var/www/html/custom_apps/appdrop/build/appdrop

docker compose -f .runtime/docker-compose.yml -p nextcloud exec -T app rm -f /tmp/appdrop.key /tmp/appdrop.crt

# signature.json is on the shared volume; copy it back to appinfo/ so it can be committed
cp build/appdrop/appinfo/signature.json appinfo/signature.json
```

### Step 5: Build the tarball

The App Store requires `.tar.gz` with a single top-level directory matching the app id.

```bash
cd build
tar -czf appdrop-1.2.0.tar.gz appdrop

# Verify the tarball structure (signature.json must be present)
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

### Step 6: Create GitHub release

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
https://github.com/cdnCore-Pt/AppDrop/releases/download/v1.2.0/appdrop-1.2.0.tar.gz
```

### Step 7: Generate release signature

```bash
openssl dgst -sha512 \
  -sign certs/appdrop.key \
  build/appdrop-1.2.0.tar.gz | openssl base64 -A
```

Copy the output — this is your release signature.

### Step 8: Register the app (first time only)

Register the app id once, before your first release (binds `appdrop` to your cert):

```bash
./scripts/prepare-appstore.sh --register-sig
```

Then register at https://apps.nextcloud.com/developer/apps/new (paste the contents
of `certs/appdrop.crt` plus the signature above), or `POST /api/v1/apps` with a JSON
body `{"certificate": "<certs/appdrop.crt>", "signature": "<register-sig>"}`.

### Step 9: Submit the release

> First release only: complete Step 8 first, or the upload is rejected (app not registered).

**Option A: API (recommended)**

Get your API token at https://apps.nextcloud.com/account/token, then:

```bash
curl -X POST https://apps.nextcloud.com/api/v1/apps/releases \
  -H "Authorization: Token YOUR_APPSTORE_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "download": "https://github.com/cdnCore-Pt/AppDrop/releases/download/v1.2.0/appdrop-1.2.0.tar.gz",
    "signature": "PASTE_SIGNATURE_HERE",
    "nightly": false
  }'
```

**Option B: Web interface**

1. Go to https://apps.nextcloud.com/developer/apps/releases/new
2. Enter the download URL of the `.tar.gz`
3. Paste the release signature
4. Submit

### Step 10: Add screenshots (recommended)

Before or after publishing, add screenshots to improve visibility:

1. Take screenshots of the app UI (upload, permissions, generator, etc.)
2. Add them to a `screenshots/` directory in the repository
3. Push to GitHub
4. Add `<screenshot>` elements to `info.xml`:

```xml
<screenshot>https://raw.githubusercontent.com/cdnCore-Pt/AppDrop/main/screenshots/upload.png</screenshot>
<screenshot>https://raw.githubusercontent.com/cdnCore-Pt/AppDrop/main/screenshots/permissions.png</screenshot>
```

---

## Directory structure

After setup, the signing-related files live in:

```
certs/                          ← git-ignored, NEVER commit
├── appdrop.key    ← Private key (keep safe!)
├── appdrop.csr    ← CSR (submitted via app-certificate-requests PR)
└── appdrop.crt    ← Certificate (issued in that PR after merge)

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
