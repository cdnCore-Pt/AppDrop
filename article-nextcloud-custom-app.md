## Why Build a Custom Nextcloud App?

Nextcloud's App Store has thousands of apps, but sometimes your organization needs functionality that doesn't exist — or needs deep integration with internal systems. Custom apps let you extend Nextcloud with your own controllers, settings panels, background jobs, and API endpoints while leveraging the full power of Nextcloud's AppFramework.

This guide covers the complete lifecycle: from directory structure to App Store publication, including security practices, testing, packaging, and common pitfalls we encountered building a production app.

---

## Prerequisites

- **PHP 8.1+** (Nextcloud 30+ requirement)
- A running Nextcloud instance (local Docker setup recommended)
- Basic knowledge of PHP OOP and MVC patterns
- Composer installed locally

---

## Step 1: Directory Structure

Every Nextcloud app follows a strict structure. The `<id>` in `info.xml` **must** match the directory name exactly.

```
custom_apps/my_custom_app/
├── appinfo/
│   ├── info.xml          # App metadata (REQUIRED)
│   └── routes.php        # URL route definitions
├── lib/
│   ├── AppInfo/
│   │   └── Application.php   # Bootstrap class
│   ├── Controller/
│   │   └── PageController.php
│   ├── Service/
│   │   └── MyService.php
│   └── Settings/
│       └── AdminSettings.php  # Admin settings panel
├── templates/
│   └── main.php          # View templates
├── js/
│   └── script.js
├── css/
│   └── style.css
├── img/
│   └── app.svg           # App icon (SVG required for App Store)
└── composer.json          # PSR-4 autoloader config
```

### Key Rules

1. **App ID format**: lowercase letters, digits, and underscores only — regex `^[a-z0-9_]{3,64}$`
2. **Directory name = App ID**: If your `info.xml` says `<id>my_custom_app</id>`, the directory must be `my_custom_app/`
3. **Namespace convention**: `OCA\MyCustomApp\` — always under the `OCA` vendor prefix
4. **Icon**: Must be SVG format for the App Store. Place at `img/app.svg`

---

## Step 2: The info.xml File

This is the most important file — it defines your app's identity, dependencies, and capabilities.

```xml
<?xml version="1.0"?>
<info xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      xsi:noNamespaceSchemaLocation="https://apps.nextcloud.com/schema/apps/info.xsd">
    <id>my_custom_app</id>
    <name>My Custom App</name>
    <summary>A brief one-line description for store listings</summary>
    <description><![CDATA[
A detailed description of your app. This field supports **Markdown**.

### Features
- Feature one
- Feature two
- Feature three
    ]]></description>
    <version>1.0.0</version>
    <licence>agpl</licence>
    <namespace>MyCustomApp</namespace>
    <category>tools</category>

    <author mail="dev@example.com" homepage="https://example.com">Your Name</author>

    <bugs>https://github.com/yourorg/my_custom_app/issues</bugs>
    <repository type="git">https://github.com/yourorg/my_custom_app</repository>
    <website>https://github.com/yourorg/my_custom_app</website>

    <dependencies>
        <php min-version="8.1"/>
        <nextcloud min-version="30" max-version="32"/>
    </dependencies>

    <!-- Register a navigation entry -->
    <navigations>
        <navigation>
            <name>My Custom App</name>
            <route>my_custom_app.page.index</route>
            <order>90</order>
        </navigation>
    </navigations>

    <!-- Register an admin settings panel -->
    <settings>
        <admin>OCA\MyCustomApp\Settings\AdminSettings</admin>
    </settings>
</info>
```

### Required Fields for App Store

| Field | Purpose | Notes |
|-------|---------|-------|
| `<id>` | Unique identifier | Must match directory name |
| `<name>` | Display name | Shown in Nextcloud UI |
| `<summary>` | One-liner | Shown in App Store listings |
| `<description>` | Full description | Supports Markdown, use CDATA |
| `<version>` | Semver version | Bumped on each release |
| `<licence>` | License identifier | `agpl` required for App Store |
| `<namespace>` | PHP namespace | Must match your PSR-4 config |
| `<author>` | Author name | Can have multiple `<author>` elements |
| `<bugs>` | Bug tracker URL | Required for App Store |
| `<dependencies>` | PHP + NC versions | Controls compatibility display |

### Common Mistake: Namespace Mismatch

The `<namespace>` value must match your PHP namespace **exactly**. If `info.xml` says `<namespace>MyCustomApp</namespace>`, then all your PHP classes must be under `OCA\MyCustomApp\`.

---

## Step 3: Bootstrap Class (Application.php)

The bootstrap class is your app's entry point. Nextcloud 30+ uses the `IBootstrap` interface.

```php
<?php

declare(strict_types=1);

namespace OCA\MyCustomApp\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class Application extends App implements IBootstrap
{
    public const APP_ID = 'my_custom_app';

    public function __construct()
    {
        parent::__construct(self::APP_ID);
    }

    public function register(IRegistrationContext $context): void
    {
        // Register services, event listeners, middleware here
        // This runs early — no app APIs available yet
    }

    public function boot(IBootContext $context): void
    {
        // Runs after all apps are registered
        // Safe to use other app's APIs here
    }
}
```

### Key Points

- **`register()`** runs during app loading — use it for DI registration, event listeners, and middleware
- **`boot()`** runs after all apps are registered — safe to use cross-app APIs
- **Don't** do heavy work in either method — they run on every request
- Settings panels registered in `info.xml` don't need manual registration here

---

## Step 4: PSR-4 Autoloading (composer.json)

```json
{
    "name": "yourorg/my-custom-app",
    "description": "My Custom Nextcloud App",
    "license": "AGPL-3.0-or-later",
    "require": {
        "php": ">=8.1"
    },
    "require-dev": {
        "nextcloud/ocp": "dev-master",
        "nextcloud/coding-standard": "^1.3",
        "psalm/phar": "^5.0",
        "phpunit/phpunit": "^10.0"
    },
    "autoload": {
        "psr-4": {
            "OCA\\MyCustomApp\\": "lib/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "OCA\\MyCustomApp\\Tests\\": "tests/"
        }
    }
}
```

Run `composer install` after creating this file. The `nextcloud/ocp` package provides IDE autocompletion and static analysis stubs for all `OCP\` interfaces.

---

## Step 5: Routes and Controllers

### Defining Routes (appinfo/routes.php)

```php
<?php

return [
    'routes' => [
        ['name' => 'page#index',    'url' => '/',          'verb' => 'GET'],
        ['name' => 'page#doSomething', 'url' => '/action', 'verb' => 'POST'],
        ['name' => 'api#getData',   'url' => '/api/data',  'verb' => 'GET'],
    ],
];
```

Route naming convention: `controller_name#method_name`. Nextcloud converts `page#index` to `PageController::index()`.

### Building a Controller

```php
<?php

declare(strict_types=1);

namespace OCA\MyCustomApp\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class PageController extends Controller
{
    public function __construct(
        string $appName,
        IRequest $request,
        private readonly MyService $myService,
    ) {
        parent::__construct($appName, $request);
    }

    #[NoCSRFRequired]
    public function index(): TemplateResponse
    {
        return new TemplateResponse('my_custom_app', 'main');
    }

    public function doSomething(): JSONResponse
    {
        // CSRF protection enabled by default on POST routes
        $result = $this->myService->process();
        return new JSONResponse(['success' => true, 'data' => $result]);
    }
}
```

### CSRF Protection Rules

- **GET routes**: Add `#[NoCSRFRequired]` attribute (safe to skip CSRF on read-only pages)
- **POST/PUT/DELETE routes**: CSRF protection enabled by default — **never** disable it
- Frontend must include the request token: either via form field `requesttoken` or header `requesttoken: OC.requestToken`

---

## Step 6: Templates and Output Escaping

Nextcloud templates are plain PHP files with global helper functions.

```php
<?php
/** @var \OCP\IL10N $l */
/** @var array $_ */

\OCP\Util::addStyle('my_custom_app', 'style');
\OCP\Util::addScript('my_custom_app', 'script');
?>

<div id="app-content">
    <div class="section">
        <h2><?php p($l->t('My Custom App')); ?></h2>
        <p><?php p($l->t('Welcome to the app.')); ?></p>

        <!-- SAFE: p() does htmlspecialchars() -->
        <span><?php p($_['userName']); ?></span>

        <!-- SAFE: for trusted HTML from Nextcloud helpers -->
        <a href="<?php print_unescaped(\OC::$server->getURLGenerator()->linkToRoute('my_custom_app.page.index')); ?>">
            <?php p($l->t('Go to app')); ?>
        </a>
    </div>
</div>
```

### Critical XSS Prevention Rules

| Function | Use Case | Safe? |
|----------|----------|-------|
| `p()` | All user-facing text output | Yes — escapes HTML |
| `print_unescaped()` | Trusted URLs from Nextcloud helpers | Yes — but verify source |
| `echo` | **NEVER use in templates** | No — XSS vulnerability |

**The App Store review will reject apps that use `echo` in templates.** Always use `p()` for text and `print_unescaped()` only for URLs generated by Nextcloud's own `linkToRoute()` or `linkTo()` helpers.

---

## Step 7: Styling with Nextcloud CSS Variables

Use Nextcloud's CSS custom properties for consistent theming and automatic dark mode support.

```css
.my-app-container {
    max-width: 640px;
    margin: 0 auto;
    padding: 20px;
    font-family: var(--font-face);
    color: var(--color-main-text);
}

.my-app-card {
    background: var(--color-main-background);
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-large);
    padding: 16px;
}

.my-app-btn {
    background: var(--color-primary-element);
    color: var(--color-primary-element-text);
    border: none;
    border-radius: var(--border-radius);
    padding: 10px 20px;
    cursor: pointer;
}

.my-app-btn:hover {
    background: var(--color-primary-element-hover);
}

.my-app-alert--success {
    background: var(--color-success);
    color: white;
    padding: 10px 16px;
    border-radius: var(--border-radius);
}

.my-app-alert--error {
    background: var(--color-error);
    color: white;
    padding: 10px 16px;
    border-radius: var(--border-radius);
}
```

### Key CSS Variables

| Variable | Purpose |
|----------|---------|
| `--color-main-text` | Primary text color |
| `--color-main-background` | Page background |
| `--color-primary-element` | Primary action color (buttons) |
| `--color-primary-element-text` | Text on primary buttons |
| `--color-border` | Border color |
| `--color-error` | Error state color |
| `--color-success` | Success state color |
| `--border-radius` | Standard border radius |
| `--font-face` | System font stack |

Using CSS variables means your app automatically looks correct in dark mode, high contrast mode, and custom themes — with zero extra effort.

---

## Step 8: JavaScript Without Build Tools

For simple apps, vanilla JavaScript works perfectly. Use IIFEs to avoid polluting the global scope.

```javascript
(function () {
    'use strict';

    const form = document.getElementById('my-form');
    if (!form) return;

    form.addEventListener('submit', async function (e) {
        e.preventDefault();

        const formData = new FormData(form);

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: {
                    'requesttoken': OC.requestToken,
                },
            });

            const data = await response.json();

            if (data.success) {
                showMessage('success', data.message);
            } else {
                showMessage('error', data.message || 'An error occurred.');
            }
        } catch (err) {
            showMessage('error', 'Network error. Please try again.');
        }
    });

    function showMessage(type, text) {
        const el = document.getElementById('message-area');
        el.textContent = text;
        el.className = 'my-app-alert my-app-alert--' + type;
        el.style.display = 'block';
    }
})();
```

### Key Points

- **`OC.requestToken`** is globally available in Nextcloud's JS environment — use it for CSRF protection on fetch calls
- **`credentials: 'same-origin'`** ensures session cookies are sent with the request
- Always handle errors gracefully — show user-friendly messages
- Load scripts via `\OCP\Util::addScript('my_custom_app', 'script')` in templates

---

## Step 9: Security Best Practices

Security is the #1 reason apps get rejected from the App Store. Here's a comprehensive checklist.

### Use Only Public APIs (OCP namespace)

```php
// GOOD — public API, stable across versions
use OCP\IConfig;
use OCP\IUserSession;
use OCP\IGroupManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;

// BAD — private API, will break and get rejected
use OC\Files\Filesystem;    // NEVER
use OC_User;                // NEVER
use OC\Security\CSP;        // NEVER
```

**Rule**: Only import from `OCP\` (public) and `OCA\` (your own app or other apps). Never use `OC\` or `OC_` — these are private internals that can change without notice.

### Admin Access Enforcement

If your app is admin-only, check in every controller method:

```php
private function isAdmin(): bool
{
    $user = $this->userSession->getUser();
    return $user !== null
        && $this->groupManager->isInGroup($user->getUID(), 'admin');
}

public function index(): TemplateResponse
{
    if (!$this->isAdmin()) {
        return new TemplateResponse('guest', '', ['error' => 'Access denied.']);
    }
    return new TemplateResponse('my_custom_app', 'main');
}
```

### File Upload Security

If your app handles file uploads, implement defense-in-depth:

```php
// 1. MIME type validation
$finfo = new \finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($tmpPath);
if (!in_array($mimeType, ['application/zip', 'application/x-zip-compressed'])) {
    throw new \RuntimeException('Invalid file type.');
}

// 2. File size limit
if (filesize($tmpPath) > 20 * 1024 * 1024) {
    throw new \RuntimeException('File too large. Maximum 20 MB.');
}

// 3. Zip Slip protection (for zip extraction)
$zip = new \ZipArchive();
$zip->open($zipPath);
for ($i = 0; $i < $zip->numFiles; $i++) {
    $name = $zip->getNameIndex($i);
    if (str_starts_with($name, '/') || str_contains($name, '..')) {
        throw new \RuntimeException('Zip contains unsafe paths.');
    }
}

// 4. Set restrictive permissions after extraction
// Directories: 0750 (rwxr-x---)
// Files: 0640 (rw-r-----)
```

### Shell Command Safety

If you must call shell commands (e.g., `occ`):

```php
// GOOD — use escapeshellarg() for every argument
$cmd = sprintf(
    '%s %s %s',
    escapeshellarg($phpBinary),
    escapeshellarg($occPath),
    'app:enable ' . escapeshellarg($appId)
);

// BAD — direct string interpolation
$cmd = "$phpBinary $occPath app:enable $appId"; // COMMAND INJECTION!
```

---

## Step 10: Alpine UID Gotcha

The Nextcloud FPM Alpine image uses `www-data` with **UID/GID 82** (Alpine convention), not the Debian 33. This matters when:

- Running `occ` commands: always use `-u www-data`
- Setting file ownership after copying files into the container
- Writing `chown` commands

```bash
# Correct — works on Alpine
docker compose exec -T -u www-data app php occ app:enable my_custom_app

# Setting ownership inside Alpine container
docker compose exec -T app chown -R www-data:www-data /var/www/html/custom_apps/my_custom_app
# www-data = UID 82 on Alpine, not 33
```

---

## Step 11: SPDX License Headers

The Nextcloud coding standard requires SPDX headers on **every source file**. The App Store checks for this.

### PHP Files

```php
<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Your Organization
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\MyCustomApp\Controller;
// ... rest of code
```

### JavaScript Files

```javascript
/**
 * SPDX-FileCopyrightText: 2026 Your Organization
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

(function () {
    // ... code
})();
```

### CSS Files

```css
/**
 * SPDX-FileCopyrightText: 2026 Your Organization
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

.my-app-container {
    /* ... */
}
```

---

## Step 12: Required Files for App Store

Beyond code, the App Store requires these files in your release:

### CHANGELOG.md

Follow the [Keep a Changelog](https://keepachangelog.com/) format:

```markdown
# Changelog

All notable changes to this project will be documented in this file.

## [1.0.0] - 2026-03-04

### Added
- Initial release
- Feature A with full description
- Feature B with full description
```

### LICENSE

Full text of AGPL-3.0-or-later. Download from [gnu.org](https://www.gnu.org/licenses/agpl-3.0.txt).

### README.md

```markdown
# My Custom App

Brief description of what the app does.

## Features
- Feature list

## Requirements
- Nextcloud 30+
- PHP 8.1+

## Installation
### From the App Store
Search for "My Custom App" in Settings → Apps.

### Manual Installation
1. Download the latest release
2. Extract to `custom_apps/my_custom_app`
3. Enable via `occ app:enable my_custom_app`

## License
AGPL-3.0-or-later
```

---

## Step 13: Quality Tools Setup

### Psalm (Static Analysis)

Create `psalm.xml`:

```xml
<?xml version="1.0"?>
<psalm errorLevel="4"
       resolveFromConfigFile="true"
       xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
       xmlns="https://getpsalm.org/schema/config"
       xsi:schemaLocation="https://getpsalm.org/schema/config vendor/vimeo/psalm/config.xsd">
    <projectFiles>
        <directory name="lib" />
        <ignoreFiles>
            <directory name="vendor" />
        </ignoreFiles>
    </projectFiles>
</psalm>
```

### PHP-CS-Fixer (Coding Standard)

Create `.php-cs-fixer.dist.php`:

```php
<?php

declare(strict_types=1);

require_once './vendor/autoload.php';

use Nextcloud\CodingStandard\Config;

$config = new Config();
$config->getFinder()
    ->ignoreVCSIgnored(true)
    ->notPath('vendor')
    ->in(__DIR__);
return $config;
```

### PHPUnit

Create `phpunit.xml`:

```xml
<?xml version="1.0"?>
<phpunit bootstrap="vendor/autoload.php" colors="true" failOnWarning="true">
    <testsuites>
        <testsuite name="unit">
            <directory>./tests/Unit</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

---

## Step 14: Packaging for Release

### .nextcloudignore

Exclude development files from the release archive:

```
.git
.github
tests
psalm.xml
phpunit.xml
.php-cs-fixer.dist.php
.php-cs-fixer.cache
composer.lock
CHANGELOG.md
README.md
Makefile
krankerl.toml
.nextcloudignore
.gitignore
```

### krankerl.toml

The [krankerl](https://github.com/nickvergessen/krankerl) tool automates packaging:

```toml
[package]
before_cmds = []
exclude = []
```

### Makefile

```makefile
app_name=my_custom_app
build_dir=./build

.PHONY: all clean test lint psalm package sign

all: lint psalm test

clean:
	rm -rf $(build_dir)

test:
	./vendor/bin/phpunit

lint:
	./vendor/bin/php-cs-fixer fix --dry-run --diff

psalm:
	./vendor/bin/psalm --no-cache

package: clean
	mkdir -p $(build_dir)/$(app_name)
	rsync -a --exclude-from=.nextcloudignore . $(build_dir)/$(app_name)/
	cd $(build_dir) && tar -czf $(app_name).tar.gz $(app_name)
	@echo "Package created: $(build_dir)/$(app_name).tar.gz"

sign:
	occ integrity:sign-app --path=$(PWD) --privateKey=$(HOME)/.nextcloud/certs/$(app_name).key --certificate=$(HOME)/.nextcloud/certs/$(app_name).crt
```

---

## Step 15: Code Signing and Publication

### 1. Generate a Certificate Signing Request

```bash
openssl req -nodes -newkey rsa:4096 \
    -keyout my_custom_app.key \
    -out my_custom_app.csr \
    -subj "/CN=my_custom_app"
```

### 2. Submit to Nextcloud

Open a PR at [nextcloud/app-certificate-requests](https://github.com/nextcloud/app-certificate-requests) with your `.csr` file. The Nextcloud team will review and return a signed certificate.

### 3. Sign Your App

```bash
occ integrity:sign-app \
    --path=/path/to/my_custom_app \
    --privateKey=/path/to/my_custom_app.key \
    --certificate=/path/to/my_custom_app.crt
```

### 4. Create Release Archive

```bash
# The archive MUST have a single top-level folder matching the app ID
tar -czf my_custom_app-1.0.0.tar.gz my_custom_app/

# Sign the archive
openssl dgst -sha512 -sign my_custom_app.key my_custom_app-1.0.0.tar.gz | openssl base64
```

### 5. Publish

1. Upload the `.tar.gz` to GitHub Releases
2. Register your app at [apps.nextcloud.com](https://apps.nextcloud.com)
3. Submit the release URL and signature

---

## Common Pitfalls and Solutions

### 1. "App not found" After Extraction

**Cause**: The app was extracted but the in-memory app cache doesn't include it. New apps extracted at runtime aren't visible to `IAppManager::enableApp()` until the next request.

**Solution**: Fall back to a subprocess call to `php occ app:enable` which forces a fresh filesystem scan:

```php
try {
    $this->appManager->enableApp($appId);
} catch (\Exception $e) {
    // Fallback: occ subprocess forces fresh cache
    exec(sprintf('php %s app:enable %s 2>&1',
        escapeshellarg($occPath),
        escapeshellarg($appId)
    ), $output, $exitCode);
}
```

### 2. JSON Responses Crash with NavigationManager

**Cause**: If your app registers navigation entries in `info.xml` and a new app's routes are malformed, the `NavigationManager` crashes when rendering `TemplateResponse`.

**Solution**: Return `JSONResponse` instead of `TemplateResponse` for POST endpoints that process data. The JSON serializer bypasses navigation rendering entirely.

### 3. Backup Before Update

Always back up the existing app directory before overwriting:

```php
$backupPath = $appPath . '_backup_' . date('Ymd_His');
rename($appPath, $backupPath);
```

This allows rollback if the new version is broken.

### 4. `OC::$server` Usage in Templates

While `OC::$server->getURLGenerator()` is commonly used in templates, it's technically accessing the private `OC` namespace. For App Store submission, prefer injecting the URL generator into the controller and passing URLs as template parameters:

```php
// Controller
public function index(): TemplateResponse
{
    return new TemplateResponse('my_app', 'main', [
        'actionUrl' => $this->urlGenerator->linkToRoute('my_app.page.doSomething'),
    ]);
}

// Template
<form action="<?php print_unescaped($_['actionUrl']); ?>" method="POST">
```

---

## App Store Submission Checklist

Before submitting, verify every item:

- [ ] `info.xml` has all required fields (id, name, summary, description, version, licence, namespace, author, bugs, dependencies)
- [ ] App ID matches directory name
- [ ] `<namespace>` matches PHP namespace
- [ ] SPDX headers on every source file (PHP, JS, CSS)
- [ ] `CHANGELOG.md` in Keep a Changelog format
- [ ] `LICENSE` file with full AGPL-3.0-or-later text
- [ ] No `echo` in templates — only `p()` and `print_unescaped()`
- [ ] No private API usage (`OC\`, `OC_`)
- [ ] CSRF protection on all POST/PUT/DELETE routes
- [ ] SVG icon at `img/app.svg`
- [ ] Code signing certificate obtained and app signed
- [ ] Release archive has single top-level folder
- [ ] `.nextcloudignore` excludes dev files from archive
- [ ] All `occ` commands use `escapeshellarg()` for arguments
- [ ] File uploads validate MIME type, size, and content (Zip Slip protection for archives)
- [ ] Admin-only features enforce group membership check

---

## Quick Reference: Nextcloud AppFramework APIs

| Interface | Purpose | Common Methods |
|-----------|---------|----------------|
| `OCP\IConfig` | System and app config | `getSystemValue()`, `getAppValue()`, `setAppValue()` |
| `OCP\IUserSession` | Current user session | `getUser()`, `isLoggedIn()` |
| `OCP\IGroupManager` | Group membership | `isInGroup()`, `getUserGroups()` |
| `OCP\IAppManager` | App management | `enableApp()`, `disableApp()`, `isEnabledForUser()` |
| `OCP\IURLGenerator` | URL generation | `linkToRoute()`, `linkTo()`, `imagePath()` |
| `OCP\IL10N` | Translations | `t()`, `n()` |
| `OCP\AppFramework\Db\QBMapper` | Database ORM | `insert()`, `update()`, `delete()`, `findEntity()` |
| `OCP\IDBConnection` | Direct DB access | `getQueryBuilder()` |
| `Psr\Log\LoggerInterface` | Logging | `info()`, `warning()`, `error()` |

All of these are injectable via constructor dependency injection — Nextcloud's DI container auto-wires them based on type hints.
