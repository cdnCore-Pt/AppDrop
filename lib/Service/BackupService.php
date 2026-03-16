<?php

/**
 * SPDX-FileCopyrightText: 2026 CDNTV
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\AppDrop\Service;

use Psr\Log\LoggerInterface;

/**
 * Manages backups created during app updates.
 * Backup dirs follow the pattern: {appId}_backup_{YYYYMMDD_HHiiss}
 */
class BackupService {
	private const BACKUP_PATTERN = '/^([a-z0-9_]{3,64})_backup_(\d{8}_\d{6})$/';

	public function __construct(
		private readonly AppPathResolver $pathResolver,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * List all backup directories.
	 *
	 * @return array<array{appId: string, date: string, dirName: string}>
	 */
	public function listBackups(): array {
		$basePath = $this->pathResolver->resolveWritablePath();
		$backups = [];

		if (!is_dir($basePath)) {
			return $backups;
		}

		$entries = new \DirectoryIterator($basePath);
		foreach ($entries as $entry) {
			if ($entry->isDot() || !$entry->isDir()) {
				continue;
			}

			$dirName = $entry->getFilename();
			if (preg_match(self::BACKUP_PATTERN, $dirName, $m)) {
				$dateStr = $m[2]; // YYYYMMDD_HHiiss
				$formatted = substr($dateStr, 0, 4) . '-' . substr($dateStr, 4, 2) . '-' . substr($dateStr, 6, 2)
					. ' ' . substr($dateStr, 9, 2) . ':' . substr($dateStr, 11, 2) . ':' . substr($dateStr, 13, 2);

				$backups[] = [
					'appId' => $m[1],
					'date' => $formatted,
					'dirName' => $dirName,
				];
			}
		}

		usort($backups, fn (array $a, array $b) => strcmp($b['date'], $a['date']));

		return $backups;
	}

	/**
	 * Restore a backup: rename backup dir → app dir.
	 */
	public function restore(string $dirName): void {
		$this->validateDirName($dirName);

		$basePath = $this->pathResolver->resolveWritablePath();
		$backupPath = $basePath . '/' . $dirName;

		if (!is_dir($backupPath)) {
			throw new AppInstallException("Backup directory '{$dirName}' not found.");
		}

		preg_match(self::BACKUP_PATTERN, $dirName, $m);
		$appId = $m[1];
		$appPath = $basePath . '/' . $appId;

		// If current app exists, back it up first
		if (is_dir($appPath)) {
			$newBackup = $appPath . '_backup_' . date('Ymd_His');
			if (!rename($appPath, $newBackup)) {
				throw new AppInstallException("Could not back up current app '{$appId}' before restoring.");
			}
			$this->logger->info("[AppDrop] Backed up current '{$appId}' to '{$newBackup}' before restore.");
		}

		if (!rename($backupPath, $appPath)) {
			throw new AppInstallException("Could not restore backup '{$dirName}'.");
		}

		$this->logger->info("[AppDrop] Restored backup '{$dirName}' to '{$appPath}'.");
	}

	/**
	 * Delete a backup directory.
	 */
	public function delete(string $dirName): void {
		$this->validateDirName($dirName);

		$basePath = $this->pathResolver->resolveWritablePath();
		$backupPath = $basePath . '/' . $dirName;

		if (!is_dir($backupPath)) {
			throw new AppInstallException("Backup directory '{$dirName}' not found.");
		}

		$this->removeDirectory($backupPath);
		$this->logger->info("[AppDrop] Deleted backup '{$dirName}'.");
	}

	/**
	 * Validate backup dir name against regex (prevents directory traversal).
	 */
	private function validateDirName(string $dirName): void {
		if (preg_match(self::BACKUP_PATTERN, $dirName) !== 1) {
			throw new AppInstallException("Invalid backup directory name '{$dirName}'.");
		}
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
