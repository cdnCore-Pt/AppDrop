<?php

/**
 * SPDX-FileCopyrightText: 2026 CDNTV
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\AppDrop\Service;

use OCP\IConfig;

/**
 * Resolves the writable apps path for installing custom apps.
 */
class AppPathResolver
{
    public function __construct(
        private readonly IConfig $config,
    ) {
    }

    /**
     * Find a writable apps directory from Nextcloud config.
     * Prefers paths containing 'custom' or 'extra', falls back to first writable.
     */
    public function resolveWritablePath(): string
    {
        /** @var array<array{path: string, url: string, writable: bool}> $appsPaths */
        $appsPaths = $this->config->getSystemValue('apps_paths', []);
        $writable = array_filter(
            $appsPaths,
            static fn(array $e): bool => !empty($e['writable']) && !empty($e['path']),
        );

        if (empty($writable)) {
            return $this->fallbackCustomAppsPath();
        }

        foreach ($writable as $entry) {
            $lower = strtolower($entry['path']);
            if (str_contains($lower, 'custom') || str_contains($lower, 'extra')) {
                return rtrim($entry['path'], '/\\');
            }
        }

        return rtrim(reset($writable)['path'], '/\\');
    }

    private function fallbackCustomAppsPath(): string
    {
        $ncRoot = realpath(__DIR__ . '/../../../../..') ?: '';
        if ($ncRoot === '') {
            throw new AppInstallException('Could not determine Nextcloud root. Configure apps_paths in config.php.');
        }

        $path = $ncRoot . '/custom_apps';
        if (!is_dir($path) && !mkdir($path, 0750, true)) {
            throw new AppInstallException("Cannot create '{$path}'.");
        }

        return $path;
    }
}
