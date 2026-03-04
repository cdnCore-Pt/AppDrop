<?php

/**
 * SPDX-FileCopyrightText: 2026 CDNTV
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\AppDrop\Service;

/**
 * Pre-install health check: validates a zip before installation.
 * Returns a report with errors (blocking) and warnings (informational).
 */
class HealthCheckService
{
    private const APPID_PATTERN = '/^[a-z0-9_]{3,64}$/';

    /**
     * Analyze a zip file and return a health report.
     *
     * @param string $zipPath Path to the temporary zip file
     * @return array{errors: string[], warnings: string[], appId: string, version: string, name: string}
     */
    public function analyze(string $zipPath): array
    {
        $errors = [];
        $warnings = [];
        $appId = '';
        $version = '';
        $name = '';

        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return ['errors' => ['Cannot open the zip archive.'], 'warnings' => [], 'appId' => '', 'version' => '', 'name' => ''];
        }

        // Detect top-level prefix and collect entries
        $topLevelPrefix = '';
        $entries = [];
        $hasInfoXml = false;
        $infoXmlContent = null;
        $iconIndex = null;
        $iconExt = '';

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);
            if ($entryName === false) {
                continue;
            }
            $normalised = str_replace('\\', '/', $entryName);
            $entries[] = $normalised;

            // Detect top-level wrapper
            if ($i === 0 && str_ends_with($normalised, '/') && substr_count($normalised, '/') === 1) {
                $topLevelPrefix = $normalised;
            }

            // Security check
            if (str_starts_with($normalised, '/')) {
                $errors[] = "Security: absolute path in entry '{$entryName}'.";
            }
            foreach (explode('/', $normalised) as $part) {
                if ($part === '..') {
                    $errors[] = "Security: directory traversal in entry '{$entryName}'.";
                    break;
                }
            }

            // Strip prefix for relative path
            $relative = $normalised;
            if ($topLevelPrefix !== '' && str_starts_with($normalised, $topLevelPrefix)) {
                $relative = substr($normalised, strlen($topLevelPrefix));
            }

            if ($relative === 'appinfo/info.xml') {
                $hasInfoXml = true;
                $infoXmlContent = $zip->getFromIndex($i);
            }

            // Detect app icon
            if ($iconIndex === null && preg_match('#^img/app\.(svg|png|jpg|jpeg|gif)$#i', $relative, $iconMatch)) {
                $iconIndex = $i;
                $iconExt = strtolower($iconMatch[1]);
            }
        }

        // Extract icon if found
        $iconData = null;
        if ($iconIndex !== null) {
            $iconRaw = $zip->getFromIndex($iconIndex);
            if ($iconRaw !== false && $iconRaw !== '') {
                $mimeMap = [
                    'svg' => 'image/svg+xml',
                    'png' => 'image/png',
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'gif' => 'image/gif',
                ];
                $mime = $mimeMap[$iconExt] ?? 'image/png';
                $iconData = 'data:' . $mime . ';base64,' . base64_encode($iconRaw);
            }
        }

        $zip->close();

        // Check: info.xml exists
        if (!$hasInfoXml || $infoXmlContent === false || $infoXmlContent === null) {
            $errors[] = "Missing 'appinfo/info.xml'. Not a valid Nextcloud app.";
            return ['errors' => $errors, 'warnings' => $warnings, 'appId' => '', 'version' => '', 'name' => ''];
        }

        // Parse info.xml
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($infoXmlContent);
        if ($xml === false) {
            $errors[] = 'info.xml is not valid XML.';
            return ['errors' => $errors, 'warnings' => $warnings, 'appId' => '', 'version' => '', 'name' => ''];
        }

        // Required: <id>
        $appId = trim((string) ($xml->id ?? ''));
        if ($appId === '') {
            $errors[] = 'info.xml is missing the <id> element.';
        } elseif (preg_match(self::APPID_PATTERN, $appId) !== 1) {
            $errors[] = "Invalid app ID '{$appId}'. Only lowercase letters, digits and underscores (3-64 chars).";
        }

        // Version
        $version = trim((string) ($xml->version ?? ''));
        if ($version === '') {
            $warnings[] = 'No <version> in info.xml. Defaults to 0.0.0.';
            $version = '0.0.0';
        }

        // Name
        $name = trim((string) ($xml->name ?? ''));
        if ($name === '') {
            $warnings[] = 'No <name> in info.xml.';
            $name = $appId;
        }

        // Namespace check
        $namespace = trim((string) ($xml->namespace ?? ''));
        if ($namespace === '') {
            $warnings[] = 'No <namespace> in info.xml. Controllers may not autoload correctly.';
        }

        // Check for required files
        $requiredFiles = ['appinfo/info.xml'];
        $recommendedFiles = ['appinfo/routes.php', 'lib/AppInfo/Application.php'];

        foreach ($recommendedFiles as $file) {
            $found = false;
            foreach ($entries as $entry) {
                $relative = $entry;
                if ($topLevelPrefix !== '' && str_starts_with($entry, $topLevelPrefix)) {
                    $relative = substr($entry, strlen($topLevelPrefix));
                }
                if ($relative === $file) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $warnings[] = "Recommended file '{$file}' not found in the package.";
            }
        }

        // App icon check
        if ($iconData === null) {
            $warnings[] = "No app icon found (expected 'img/app.svg' or 'img/app.png'). Apps without an icon will show a generic placeholder.";
        }

        // NC version compatibility
        $ncDeps = $xml->dependencies->nextcloud ?? null;
        if ($ncDeps === null) {
            $warnings[] = 'No Nextcloud version dependency declared in info.xml.';
        }

        // PHP version check
        $phpDeps = $xml->dependencies->php ?? null;
        if ($phpDeps !== null) {
            $minPhp = (string) ($phpDeps['min-version'] ?? '');
            if ($minPhp !== '' && version_compare(PHP_VERSION, $minPhp, '<')) {
                $errors[] = "Requires PHP >= {$minPhp}, but server runs PHP " . PHP_VERSION . '.';
            }
        }

        return [
            'errors' => $errors,
            'warnings' => $warnings,
            'appId' => $appId,
            'version' => $version,
            'name' => $name,
            'icon' => $iconData,
        ];
    }
}
