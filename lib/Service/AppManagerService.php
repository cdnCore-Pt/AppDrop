<?php

/**
 * SPDX-FileCopyrightText: 2026 CDNTV
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\AppDrop\Service;

use OCP\App\IAppManager;
use Psr\Log\LoggerInterface;

/**
 * Manages custom apps: list, enable, disable, remove.
 */
class AppManagerService
{
    private const APPID_PATTERN = '/^[a-z0-9_]{3,64}$/';

    public function __construct(
        private readonly AppPathResolver $pathResolver,
        private readonly IAppManager $appManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * List custom apps found in writable apps paths.
     *
     * @return array<array{id: string, name: string, version: string, enabled: bool}>
     */
    public function listApps(): array
    {
        $basePath = $this->pathResolver->resolveWritablePath();
        $apps = [];

        if (!is_dir($basePath)) {
            return $apps;
        }

        $entries = new \DirectoryIterator($basePath);
        foreach ($entries as $entry) {
            if ($entry->isDot() || !$entry->isDir()) {
                continue;
            }

            $dirName = $entry->getFilename();

            // Skip backup directories
            if (str_contains($dirName, '_backup_')) {
                continue;
            }

            $infoPath = $entry->getPathname() . '/appinfo/info.xml';
            if (!file_exists($infoPath)) {
                continue;
            }

            $info = $this->readInfoXml($infoPath);
            if ($info === null) {
                continue;
            }

            $enabled = false;
            try {
                $enabled = $this->appManager->isEnabledForUser($info['id']);
            } catch (\Throwable) {
                // App might not be known to the app manager yet
            }

            $apps[] = [
                'id' => $info['id'],
                'name' => $info['name'],
                'version' => $info['version'],
                'enabled' => $enabled,
            ];
        }

        usort($apps, fn(array $a, array $b) => strcasecmp($a['id'], $b['id']));

        return $apps;
    }

    /**
     * Enable a custom app.
     */
    public function enableApp(string $appId): void
    {
        $this->validateAppId($appId);

        try {
            $this->appManager->enableApp($appId);
        } catch (\Throwable $e) {
            // Fallback to occ
            $this->runOcc('app:enable', $appId);
        }
    }

    /**
     * Disable a custom app.
     */
    public function disableApp(string $appId): void
    {
        $this->validateAppId($appId);
        $this->appManager->disableApp($appId);
    }

    /**
     * Remove a custom app from disk.
     */
    public function removeApp(string $appId): void
    {
        $this->validateAppId($appId);

        // Disable first
        try {
            $this->appManager->disableApp($appId);
        } catch (\Throwable) {
            // May already be disabled
        }

        $basePath = $this->pathResolver->resolveWritablePath();
        $appPath = $basePath . '/' . $appId;

        if (!is_dir($appPath)) {
            throw new AppInstallException("App '{$appId}' not found at '{$appPath}'.");
        }

        $this->removeDirectory($appPath);
        $this->logger->info("[AppDrop] Removed app '{$appId}' from '{$appPath}'.");
    }

    private function validateAppId(string $appId): void
    {
        if (preg_match(self::APPID_PATTERN, $appId) !== 1) {
            throw new AppInstallException("Invalid app ID '{$appId}'.");
        }
    }

    private function readInfoXml(string $path): ?array
    {
        $content = @file_get_contents($path);
        if ($content === false) {
            return null;
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);
        if ($xml === false) {
            return null;
        }

        $id = trim((string) ($xml->id ?? ''));
        if ($id === '') {
            return null;
        }

        return [
            'id' => $id,
            'name' => trim((string) ($xml->name ?? $id)),
            'version' => trim((string) ($xml->version ?? '0.0.0')),
        ];
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            if ($item->isDir() && !$item->isLink()) {
                rmdir($item->getRealPath());
            } else {
                unlink($item->getRealPath());
            }
        }

        rmdir($path);
    }

    private function runOcc(string $command, string $appId): void
    {
        $occ = \OC::$SERVERROOT . '/occ';
        $escapedOcc = escapeshellarg($occ);
        $escapedAppId = escapeshellarg($appId);

        exec("php {$escapedOcc} {$command} {$escapedAppId} 2>&1", $output, $exitCode);

        if ($exitCode !== 0) {
            $msg = implode(' ', $output);
            throw new AppInstallException("occ {$command} failed: {$msg}");
        }
    }
}
