# Tests

Unit tests for AppDrop using PHPUnit 10.

## Requirements

- PHP 8.1+ with extensions: `dom`, `xml`, `xmlwriter`, `mbstring`, `zip`
- Composer dependencies installed

## Setup

```bash
cd custom_apps/appdrop
composer install
```

On Ubuntu/Debian, if `php-xml` is missing:

```bash
sudo apt-get install php8.3-xml
```

## Running tests

```bash
# Via Makefile
make test

# Directly
./vendor/bin/phpunit

# Single test file
./vendor/bin/phpunit tests/Unit/Service/HealthCheckServiceTest.php

# Single test method
./vendor/bin/phpunit --filter testAnalyzeValidZipPassesSecurity
```

## Running via Docker

If you don't have the required PHP extensions installed locally:

```bash
docker run --rm -v "$(pwd):/app" -w /app php:8.3-cli bash -c \
  "apt-get update -qq > /dev/null 2>&1 && \
   apt-get install -y -qq libzip-dev > /dev/null 2>&1 && \
   docker-php-ext-install zip > /dev/null 2>&1 && \
   php vendor/bin/phpunit"
```

## Test structure

```
tests/
├── bootstrap.php                          # Autoloader + OCP namespace registration
└── Unit/
    ├── Controller/
    │   ├── AdminControllerTest.php
    │   ├── AppManagerControllerTest.php
    │   ├── BackupControllerTest.php
    │   ├── HealthCheckControllerTest.php
    │   ├── HistoryControllerTest.php
    │   ├── PermissionControllerTest.php
    │   ├── SettingsControllerTest.php
    │   └── TemplateGeneratorControllerTest.php
    ├── Service/
    │   ├── AppInstallServiceTest.php
    │   ├── AppManagerServiceTest.php
    │   ├── AppPathResolverTest.php
    │   ├── BackupServiceTest.php
    │   ├── HealthCheckServiceTest.php
    │   ├── PermissionServiceTest.php
    │   ├── TemplateGeneratorServiceTest.php
    │   └── UploadHistoryServiceTest.php
    ├── Settings/
    │   ├── AdminSectionTest.php
    │   └── AdminSettingsTest.php
    └── Db/
        └── UploadHistoryTest.php
```

## Writing new tests

Follow the existing patterns:

- One test class per source class
- Use PHPUnit intersection types for mocks: `IConfig&MockObject`
- Use `#[DataProvider('...')]` for parameterized tests
- Mock all OCP interfaces; test service logic in isolation
- For filesystem-dependent tests (Backup, AppManager), create temp directories in `setUp()` and clean up in `tearDown()`
- For zip-dependent tests (HealthCheck, TemplateGenerator), build real zip archives in temp directories
