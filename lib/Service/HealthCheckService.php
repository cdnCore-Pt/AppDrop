<?php

/**
 * SPDX-FileCopyrightText: 2026 CDNTV
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\AppDrop\Service;

/**
 * Pre-install health check: validates a zip before installation.
 * Returns a structured report with individual check results.
 *
 * Each check has: label (display name), status (pass|fail|warn), detail (explanation).
 */
class HealthCheckService
{
    private const APPID_PATTERN = '/^[a-z0-9_]{3,64}$/';
    private const NAMESPACE_PATTERN = '/^[A-Z][a-zA-Z0-9]*$/';

    /**
     * Analyze a zip file and return a health report.
     *
     * @param string $zipPath Path to the temporary zip file
     * @return array{checks: array[], errors: string[], warnings: string[], appId: string, version: string, name: string, icon: ?string}
     */
    public function analyze(string $zipPath): array
    {
        $checks = [];
        $errors = [];
        $warnings = [];
        $appId = '';
        $version = '';
        $name = '';

        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            $checks[] = ['label' => 'Zip Archive', 'status' => 'fail', 'detail' => 'Cannot open the zip archive.'];
            return $this->buildResult($checks, ['Cannot open the zip archive.'], [], '', '', '', null);
        }
        $checks[] = ['label' => 'Zip Archive', 'status' => 'pass', 'detail' => 'Archive opened successfully.'];

        // ── Scan all entries ─────────────────────────────────────────────────
        $topLevelPrefix = '';
        $entries = [];
        $relativeEntries = [];
        $infoXmlContent = null;
        $iconIndex = null;
        $iconExt = '';
        $securityOk = true;
        $applicationPhpContent = null;
        $routesPhpContent = null;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);
            if ($entryName === false) {
                continue;
            }
            $normalised = str_replace('\\', '/', $entryName);
            $entries[] = $normalised;

            if ($i === 0 && str_ends_with($normalised, '/') && substr_count($normalised, '/') === 1) {
                $topLevelPrefix = $normalised;
            }

            // Security checks
            if (str_starts_with($normalised, '/')) {
                $errors[] = "Security: absolute path in entry '{$entryName}'.";
                $securityOk = false;
            }
            foreach (explode('/', $normalised) as $part) {
                if ($part === '..') {
                    $errors[] = "Security: directory traversal in entry '{$entryName}'.";
                    $securityOk = false;
                    break;
                }
            }

            $relative = $normalised;
            if ($topLevelPrefix !== '' && str_starts_with($normalised, $topLevelPrefix)) {
                $relative = substr($normalised, strlen($topLevelPrefix));
            }
            $relativeEntries[] = $relative;

            if ($relative === 'appinfo/info.xml') {
                $infoXmlContent = $zip->getFromIndex($i);
            }
            if ($relative === 'lib/AppInfo/Application.php') {
                $applicationPhpContent = $zip->getFromIndex($i);
            }
            if ($relative === 'appinfo/routes.php') {
                $routesPhpContent = $zip->getFromIndex($i);
            }
            if ($iconIndex === null && preg_match('#^img/app\.(svg|png|jpg|jpeg|gif)$#i', $relative, $iconMatch)) {
                $iconIndex = $i;
                $iconExt = strtolower($iconMatch[1]);
            }
        }

        // ── Security ─────────────────────────────────────────────────────────
        $checks[] = $securityOk
            ? ['label' => 'Security (Zip Slip)', 'status' => 'pass', 'detail' => 'No malicious paths detected.']
            : ['label' => 'Security (Zip Slip)', 'status' => 'fail', 'detail' => 'Dangerous paths found in archive.'];

        // ── Icon ─────────────────────────────────────────────────────────────
        $iconData = null;
        if ($iconIndex !== null) {
            $iconRaw = $zip->getFromIndex($iconIndex);
            if ($iconRaw !== false && $iconRaw !== '') {
                $mimeMap = ['svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif'];
                $iconData = 'data:' . ($mimeMap[$iconExt] ?? 'image/png') . ';base64,' . base64_encode($iconRaw);
            }
        }
        if ($iconData !== null) {
            $checks[] = ['label' => 'App Icon', 'status' => 'pass', 'detail' => 'Icon found (img/app.' . $iconExt . ').'];
        } else {
            $checks[] = ['label' => 'App Icon', 'status' => 'warn', 'detail' => 'No icon found. A placeholder will be generated.'];
            $warnings[] = 'No app icon found.';
        }

        $zip->close();

        // ── info.xml exists ──────────────────────────────────────────────────
        if ($infoXmlContent === false || $infoXmlContent === null) {
            $checks[] = ['label' => 'appinfo/info.xml', 'status' => 'fail', 'detail' => 'Missing. Not a valid Nextcloud app package.'];
            $errors[] = "Missing 'appinfo/info.xml'.";
            return $this->buildResult($checks, $errors, $warnings, '', '', '', $iconData);
        }
        $checks[] = ['label' => 'appinfo/info.xml', 'status' => 'pass', 'detail' => 'File found in package.'];

        // ── info.xml is valid XML ────────────────────────────────────────────
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($infoXmlContent);
        if ($xml === false) {
            $checks[] = ['label' => 'XML Parsing', 'status' => 'fail', 'detail' => 'info.xml is not valid XML.'];
            $errors[] = 'info.xml is not valid XML.';
            return $this->buildResult($checks, $errors, $warnings, '', '', '', $iconData);
        }
        $checks[] = ['label' => 'XML Parsing', 'status' => 'pass', 'detail' => 'info.xml parsed successfully.'];

        // ── App ID ───────────────────────────────────────────────────────────
        $appId = trim((string) ($xml->id ?? ''));
        if ($appId === '') {
            $checks[] = ['label' => 'App ID (<id>)', 'status' => 'fail', 'detail' => 'Missing <id> element in info.xml.'];
            $errors[] = 'info.xml is missing the <id> element.';
        } elseif (preg_match(self::APPID_PATTERN, $appId) !== 1) {
            $checks[] = ['label' => 'App ID (<id>)', 'status' => 'fail', 'detail' => "Invalid: '{$appId}'. Must be lowercase letters, digits, underscores (3-64 chars)."];
            $errors[] = "Invalid app ID '{$appId}'.";
        } else {
            $checks[] = ['label' => 'App ID (<id>)', 'status' => 'pass', 'detail' => $appId];
        }

        // ── App ID matches directory ─────────────────────────────────────────
        if ($appId !== '' && $topLevelPrefix !== '') {
            $dirName = rtrim($topLevelPrefix, '/');
            if ($dirName === $appId) {
                $checks[] = ['label' => 'Directory matches ID', 'status' => 'pass', 'detail' => "Directory '{$dirName}' matches app ID."];
            } else {
                $checks[] = ['label' => 'Directory matches ID', 'status' => 'warn', 'detail' => "Directory '{$dirName}' differs from app ID '{$appId}'. AppDrop will handle this, but it may cause confusion."];
                $warnings[] = "Directory name '{$dirName}' differs from app ID '{$appId}'.";
            }
        }

        // ── App Name ─────────────────────────────────────────────────────────
        $name = trim((string) ($xml->name ?? ''));
        if ($name === '') {
            $checks[] = ['label' => 'App Name (<name>)', 'status' => 'warn', 'detail' => 'Missing. The app ID will be used as display name.'];
            $warnings[] = 'No <name> in info.xml.';
            $name = $appId;
        } else {
            $checks[] = ['label' => 'App Name (<name>)', 'status' => 'pass', 'detail' => $name];
        }

        // ── Version ──────────────────────────────────────────────────────────
        $version = trim((string) ($xml->version ?? ''));
        if ($version === '') {
            $checks[] = ['label' => 'Version (<version>)', 'status' => 'warn', 'detail' => 'Missing. Defaults to 0.0.0.'];
            $warnings[] = 'No <version> in info.xml.';
            $version = '0.0.0';
        } else {
            $checks[] = ['label' => 'Version (<version>)', 'status' => 'pass', 'detail' => $version];
        }

        // ── Namespace ────────────────────────────────────────────────────────
        $namespace = trim((string) ($xml->namespace ?? ''));
        if ($namespace === '') {
            $checks[] = ['label' => 'Namespace (<namespace>)', 'status' => 'warn', 'detail' => 'Missing. Controllers may not autoload correctly.'];
            $warnings[] = 'No <namespace> in info.xml.';
        } elseif (preg_match(self::NAMESPACE_PATTERN, $namespace) !== 1) {
            $checks[] = ['label' => 'Namespace (<namespace>)', 'status' => 'warn', 'detail' => "'{$namespace}' is not PascalCase. Expected format: MyApp."];
            $warnings[] = "Namespace '{$namespace}' is not PascalCase.";
        } else {
            $checks[] = ['label' => 'Namespace (<namespace>)', 'status' => 'pass', 'detail' => $namespace];
        }

        // ── Licence ──────────────────────────────────────────────────────────
        $licence = trim((string) ($xml->licence ?? (string) ($xml->license ?? '')));
        if ($licence === '') {
            $checks[] = ['label' => 'Licence (<licence>)', 'status' => 'warn', 'detail' => 'No licence declared.'];
            $warnings[] = 'No <licence> in info.xml.';
        } else {
            $checks[] = ['label' => 'Licence (<licence>)', 'status' => 'pass', 'detail' => $licence];
        }

        // ── Namespace consistency with Application.php ───────────────────────
        if ($namespace !== '' && $applicationPhpContent !== null && $applicationPhpContent !== false) {
            $expectedNs = 'namespace OCA\\' . $namespace . '\\AppInfo';
            if (str_contains($applicationPhpContent, $expectedNs)) {
                $checks[] = ['label' => 'Namespace in Application.php', 'status' => 'pass', 'detail' => "Matches OCA\\{$namespace}\\AppInfo."];
            } else {
                // Try to extract actual namespace
                if (preg_match('/namespace\s+(OCA\\\\[A-Za-z0-9_\\\\]+)/', $applicationPhpContent, $nsMatch)) {
                    $checks[] = ['label' => 'Namespace in Application.php', 'status' => 'fail', 'detail' => "Expected OCA\\{$namespace}\\AppInfo, found {$nsMatch[1]}."];
                    $errors[] = "Namespace mismatch in Application.php.";
                } else {
                    $checks[] = ['label' => 'Namespace in Application.php', 'status' => 'warn', 'detail' => 'Could not detect namespace declaration.'];
                    $warnings[] = 'Could not verify namespace in Application.php.';
                }
            }
        }

        // ── Required/recommended files ───────────────────────────────────────
        $fileChecks = [
            'appinfo/routes.php' => ['required' => false, 'label' => 'appinfo/routes.php'],
            'lib/AppInfo/Application.php' => ['required' => false, 'label' => 'lib/AppInfo/Application.php'],
        ];

        foreach ($fileChecks as $file => $meta) {
            $found = in_array($file, $relativeEntries, true);
            if ($found) {
                $checks[] = ['label' => $meta['label'], 'status' => 'pass', 'detail' => 'File found.'];
            } else {
                $status = $meta['required'] ? 'fail' : 'warn';
                $checks[] = ['label' => $meta['label'], 'status' => $status, 'detail' => 'Not found in package.'];
                if ($meta['required']) {
                    $errors[] = "Required file '{$file}' missing.";
                } else {
                    $warnings[] = "Recommended file '{$file}' not found.";
                }
            }
        }

        // ── Nextcloud version dependency ─────────────────────────────────────
        $ncDeps = $xml->dependencies->nextcloud ?? null;
        if ($ncDeps === null) {
            $checks[] = ['label' => 'Nextcloud Compatibility', 'status' => 'warn', 'detail' => 'No Nextcloud version dependency declared.'];
            $warnings[] = 'No Nextcloud version dependency declared.';
        } else {
            $minNc = (string) ($ncDeps['min-version'] ?? '');
            $maxNc = (string) ($ncDeps['max-version'] ?? '');
            $range = ($minNc !== '' ? $minNc : '?') . ' – ' . ($maxNc !== '' ? $maxNc : '?');
            $checks[] = ['label' => 'Nextcloud Compatibility', 'status' => 'pass', 'detail' => "NC {$range}"];
        }

        // ── PHP version check ────────────────────────────────────────────────
        $phpDeps = $xml->dependencies->php ?? null;
        if ($phpDeps !== null) {
            $minPhp = (string) ($phpDeps['min-version'] ?? '');
            if ($minPhp !== '' && version_compare(PHP_VERSION, $minPhp, '<')) {
                $checks[] = ['label' => 'PHP Compatibility', 'status' => 'fail', 'detail' => "Requires PHP >= {$minPhp}, server runs " . PHP_VERSION . '.'];
                $errors[] = "Requires PHP >= {$minPhp}, but server runs PHP " . PHP_VERSION . '.';
            } elseif ($minPhp !== '') {
                $checks[] = ['label' => 'PHP Compatibility', 'status' => 'pass', 'detail' => "Requires PHP >= {$minPhp}, server runs " . PHP_VERSION . '.'];
            }
        } else {
            $checks[] = ['label' => 'PHP Compatibility', 'status' => 'warn', 'detail' => 'No PHP version requirement declared.'];
            $warnings[] = 'No PHP version requirement declared.';
        }

        return $this->buildResult($checks, $errors, $warnings, $appId, $version, $name, $iconData);
    }

    private function buildResult(array $checks, array $errors, array $warnings, string $appId, string $version, string $name, ?string $icon): array
    {
        return [
            'checks' => $checks,
            'errors' => $errors,
            'warnings' => $warnings,
            'appId' => $appId,
            'version' => $version,
            'name' => $name,
            'icon' => $icon,
        ];
    }
}
