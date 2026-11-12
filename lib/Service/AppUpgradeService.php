<?php

/**
 * SPDX-FileCopyrightText: 2026 CDNTV
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\AppDrop\Service;

use OCP\App\IAppManager;
use OCP\IAppConfig;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

/**
 * Keeps oc_appconfig `installed_version` in line with the code on disk.
 *
 * Nextcloud compares both on every request; as soon as they differ for an
 * enabled app it answers 503 "upgrade needed" everywhere until an admin runs
 * `occ upgrade`. AppDrop swaps app code in three places (update, re-install
 * after a remove, backup restore), so each of them must call reconcile().
 */
class AppUpgradeService {
	private const APP_ID = 'appdrop';

	public function __construct(
		private readonly IAppManager $appManager,
		private readonly IAppConfig $appConfig,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * Version the database has installed for $appId ('' = never installed).
	 *
	 * Read straight from the database: `occ app:enable` runs in another
	 * process, and a cached '' here skips the upgrade and leaves the whole
	 * instance answering 503 "upgrade needed". The shared app-config cache is
	 * deliberately left alone — clearing it around an upgrade made concurrent
	 * requests reload the old version and answer 503 for a few seconds.
	 */
	public function installedVersion(string $appId): string {
		return $this->queryInstalledVersion($appId);
	}

	protected function queryInstalledVersion(string $appId): string {
		// ponytail: resolved here, not injected — the only DB access in this class, and
		// IDBConnection cannot be mocked in unit tests without doctrine/dbal installed
		$qb = \OCP\Server::get(IDBConnection::class)->getQueryBuilder();
		$result = $qb->select('configvalue')
			->from('appconfig')
			->where($qb->expr()->eq('appid', $qb->createNamedParameter($appId)))
			->andWhere($qb->expr()->eq('configkey', $qb->createNamedParameter('installed_version')))
			->executeQuery();
		$value = $result->fetchOne();
		$result->closeCursor();

		return $value === false || $value === null ? '' : (string)$value;
	}

	/**
	 * Enable $appId, running the real installer for apps the database has
	 * never seen. IAppManager::enableApp() only flips `enabled=yes` on NC 30/31
	 * (no migrations, no installed_version) and refuses outright on NC 32+,
	 * whereas `occ app:enable` installs first on every version.
	 *
	 * @throws AppInstallException when the app could not be enabled
	 */
	public function enable(string $appId): void {
		$neverInstalled = $this->installedVersion($appId) === '';

		if ($neverInstalled && function_exists('exec') && $this->appManager->isEnabledForUser($appId)) {
			// Left behind by AppDrop <= 1.4.0 on NC 30/31 (enabled, never installed):
			// `occ app:enable` would answer "already enabled" and skip the installer.
			try {
				$this->appManager->disableApp($appId);
			} catch (\Throwable $e) {
				$this->logger->warning('[AppDrop] Could not disable half-installed app before installing it', [
					'app' => self::APP_ID,
					'appId' => $appId,
					'exception' => $e,
				]);
			}
		}

		if (!$neverInstalled && $this->enableNatively($appId)) {
			return;
		}
		if ($this->runOccEnable($appId)) {
			// make the app visible to this and the next web requests right away
			$this->appConfig->clearCache();
			return;
		}
		if ($neverInstalled && $this->enableNatively($appId)) {
			return;
		}

		throw new AppInstallException(
			"Could not enable '{$appId}' automatically. Please enable it manually in Settings → Apps.",
		);
	}

	private function enableNatively(string $appId): bool {
		try {
			$this->appManager->enableApp($appId);
			return true;
		} catch (\Throwable $e) {
			$this->logger->warning('[AppDrop] Native enableApp failed', [
				'app' => self::APP_ID,
				'appId' => $appId,
				'exception' => $e,
			]);
			return false;
		}
	}

	/**
	 * `occ app:enable` in a subprocess (fresh app scan + installer).
	 */
	protected function runOccEnable(string $appId): bool {
		if (!function_exists('exec')) {
			$this->logger->warning('[AppDrop] exec() is disabled: cannot run `occ app:enable`, falling back to the native API', [
				'app' => self::APP_ID,
				'appId' => $appId,
			]);
			return false;
		}
		$occ = escapeshellarg(\OC::$SERVERROOT . '/occ');
		exec("php {$occ} app:enable " . escapeshellarg($appId) . ' 2>&1', $output, $exitCode);
		if ($exitCode !== 0) {
			$this->logger->warning('[AppDrop] occ app:enable failed', [
				'app' => self::APP_ID,
				'appId' => $appId,
				'exitCode' => $exitCode,
				'output' => implode(' ', $output),
			]);
			return false;
		}
		return true;
	}

	/**
	 * Run the app's upgrade routine (migrations, repair steps, installed_version
	 * bump) if the database knows the app under a different version than the
	 * code now at $appPath. No-op for apps the database has never installed —
	 * `occ app:enable` takes care of those.
	 *
	 * @throws AppInstallException when the upgrade routine fails
	 */
	public function reconcile(string $appId, string $appPath): void {
		$installedVersion = $this->installedVersion($appId);
		if ($installedVersion === '') {
			return;
		}

		$codeVersion = $this->readCodeVersion($appPath . '/appinfo/info.xml');
		if ($codeVersion === '' || $codeVersion === $installedVersion) {
			return;
		}

		try {
			if (method_exists($this->appManager, 'upgradeApp')) {
				// OCP API since Nextcloud 32
				$this->appManager->upgradeApp($appId);
			} else {
				// ponytail: private API, only path on NC 30/31; drop when min-version >= 32
				\OC_App::updateApp($appId);
			}
		} catch (\Throwable $e) {
			$this->logger->error('[AppDrop] App upgrade failed after code change', [
				'app' => self::APP_ID,
				'appId' => $appId,
				'from' => $installedVersion,
				'to' => $codeVersion,
				'exception' => $e,
			]);
			throw new AppInstallException(
				"Files for '{$appId}' are in place but its upgrade from v{$installedVersion} to v{$codeVersion} failed: "
				. "{$e->getMessage()}. Run `occ upgrade` to finish it, or restore a backup from the Backups tab.",
			);
		}

		$now = $this->installedVersion($appId);
		if ($now !== $codeVersion) {
			$this->logger->error('[AppDrop] App upgrade ran but installed_version did not move', [
				'app' => self::APP_ID,
				'appId' => $appId,
				'from' => $installedVersion,
				'to' => $codeVersion,
				'installedVersion' => $now,
			]);
			throw new AppInstallException(
				"Files for '{$appId}' are in place but Nextcloud still records v{$now} instead of v{$codeVersion}. "
				. 'Run `occ upgrade` to finish the update.',
			);
		}
	}

	private function readCodeVersion(string $infoXmlPath): string {
		$content = @file_get_contents($infoXmlPath);
		if ($content === false) {
			return '';
		}
		libxml_use_internal_errors(true);
		$xml = simplexml_load_string($content);
		return $xml === false ? '' : trim((string)($xml->version ?? ''));
	}
}
