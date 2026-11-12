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
class AppManagerService {
	private const APP_ID = 'appdrop';
	private const APPID_PATTERN = '/^[a-z0-9_]{3,64}$/';

	public function __construct(
		private readonly AppPathResolver $pathResolver,
		private readonly IAppManager $appManager,
		private readonly AppUpgradeService $upgrader,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * List custom apps found in writable apps paths.
	 *
	 * @return array<array{id: string, name: string, version: string, enabled: bool}>
	 */
	public function listApps(): array {
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

		usort($apps, fn (array $a, array $b) => strcasecmp($a['id'], $b['id']));

		return $apps;
	}

	/**
	 * Enable a custom app.
	 */
	public function enableApp(string $appId): void {
		$this->validateAppId($appId);

		$this->upgrader->enable($appId);
	}

	/**
	 * Disable a custom app.
	 */
	public function disableApp(string $appId): void {
		$this->validateAppId($appId);
		$this->refuseSelf($appId);

		try {
			$this->appManager->disableApp($appId);
		} catch (\Throwable $e) {
			// e.g. core refuses shipped apps ("files can't be disabled")
			$this->logger->warning('[AppDrop] disableApp failed', ['app' => self::APP_ID, 'appId' => $appId, 'exception' => $e]);
			throw new AppInstallException("Could not disable '{$appId}': {$e->getMessage()}");
		}
	}

	/**
	 * Remove a custom app from disk.
	 */
	public function removeApp(string $appId): void {
		$this->validateAppId($appId);
		$this->refuseSelf($appId);

		$basePath = $this->pathResolver->resolveWritablePath();
		$appPath = $basePath . '/' . $appId;

		// Before disabling: never touch apps we cannot remove (shipped apps)
		if (!is_dir($appPath)) {
			throw new AppInstallException("App '{$appId}' not found at '{$appPath}'.");
		}

		try {
			$this->appManager->disableApp($appId);
		} catch (\Throwable) {
			// May already be disabled
		}

		$this->removeDirectory($appPath);
		$this->logger->info("[AppDrop] Removed app '{$appId}' from '{$appPath}'.");
	}

	private function refuseSelf(string $appId): void {
		if ($appId === self::APP_ID) {
			throw new AppInstallException('AppDrop cannot disable or remove itself. Use Settings → Apps or occ instead.');
		}
	}

	private function validateAppId(string $appId): void {
		if (preg_match(self::APPID_PATTERN, $appId) !== 1) {
			throw new AppInstallException("Invalid app ID '{$appId}'.");
		}
	}

	private function readInfoXml(string $path): ?array {
		$content = @file_get_contents($path);
		if ($content === false) {
			return null;
		}

		libxml_use_internal_errors(true);
		$xml = simplexml_load_string($content);
		if ($xml === false) {
			return null;
		}

		$id = trim((string)($xml->id ?? ''));
		if ($id === '') {
			return null;
		}

		return [
			'id' => $id,
			'name' => trim((string)($xml->name ?? $id)),
			'version' => trim((string)($xml->version ?? '0.0.0')),
		];
	}

	private function removeDirectory(string $path): void {
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
}
