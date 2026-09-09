<?php

/**
 * SPDX-FileCopyrightText: 2026 CDNTV
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\AppDrop\Service;

use OCP\App\IAppManager;
use OCP\IConfig;
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
		private readonly IConfig $config,
		private readonly LoggerInterface $logger,
	) {
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
		$installedVersion = $this->config->getAppValue($appId, 'installed_version', '');
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
