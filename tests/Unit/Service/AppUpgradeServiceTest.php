<?php

declare(strict_types=1);

namespace OCA\AppDrop\Tests\Unit\Service;

use OCA\AppDrop\Service\AppInstallException;
use OCA\AppDrop\Service\AppUpgradeService;
use OCP\App\IAppManager;
use OCP\IAppConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AppUpgradeServiceTest extends TestCase {
	private IAppManager&MockObject $appManager;
	private LoggerInterface&MockObject $logger;
	private AppUpgradeService $service;
	private string $appPath;
	private IAppConfig&\PHPUnit\Framework\MockObject\MockObject $appConfig;

	protected function setUp(): void {
		$this->appManager = $this->createMock(IAppManager::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->service = new RecordingUpgradeService($this->appManager, $this->appConfig, $this->logger);

		$this->appPath = sys_get_temp_dir() . '/appdrop_upg_' . uniqid();
		mkdir($this->appPath . '/appinfo', 0755, true);
	}

	protected function tearDown(): void {
		@unlink($this->appPath . '/appinfo/info.xml');
		@rmdir($this->appPath . '/appinfo');
		@rmdir($this->appPath);
	}

	private function codeVersion(string $version): void {
		file_put_contents(
			$this->appPath . '/appinfo/info.xml',
			"<?xml version=\"1.0\"?><info><id>myapp</id><version>{$version}</version></info>",
		);
	}

	/** @param string ...$versions what the DB answers on each successive read (last one repeats) */
	private function dbVersion(string ...$versions): void {
		$this->service->installedVersionReader = static function (string $appId) use (&$versions): string {
			return count($versions) > 1 ? array_shift($versions) : $versions[0];
		};
	}

	public function testReconcileLeavesTheSharedConfigCacheAlone(): void {
		// installed_version is read straight from the DB. Clearing the shared
		// app-config cache around an upgrade made concurrent requests see the
		// old version and answer 503 for seconds after a successful update.
		$this->appConfig->expects($this->never())->method('clearCache');
		$this->dbVersion('1.0.0', '1.1.0');
		$this->codeVersion('1.1.0');
		$this->appManager->expects($this->once())->method('upgradeApp')->willReturn(true);

		$this->service->reconcile('myapp', $this->appPath);
	}

	public function testFailsLoudlyWhenTheUpgradeDidNotMoveTheInstalledVersion(): void {
		// Never report success while the instance is about to answer 503.
		$this->dbVersion('1.0.0');
		$this->codeVersion('1.1.0');
		$this->appManager->method('upgradeApp')->willReturn(true);
		$this->logger->expects($this->once())->method('error');

		$this->expectException(AppInstallException::class);
		$this->expectExceptionMessage('occ upgrade');

		$this->service->reconcile('myapp', $this->appPath);
	}

	// ── enable() ────────────────────────────────────────────────────────────

	public function testEnableRepairsAppLeftEnabledButNeverInstalled(): void {
		// State left behind by AppDrop <= 1.4.0 on NC 30/31: `occ app:enable`
		// answers "already enabled" and skips the installer unless we disable first.
		$this->dbVersion('');
		$this->appManager->method('isEnabledForUser')->with('myapp')->willReturn(true);
		$this->appManager->expects($this->once())->method('disableApp')->with('myapp');

		$this->service->enable('myapp');

		$this->assertSame(['myapp'], $this->service->occCalls);
	}

	public function testEnableOfNeverInstalledAppRunsTheInstallerViaOcc(): void {
		// IAppManager::enableApp() on NC 30/31 only flips enabled=yes: no
		// migrations, no installed_version. `occ app:enable` runs the installer.
		$this->dbVersion('');
		$this->appManager->expects($this->never())->method('enableApp');
		// the subprocess changed the DB behind this process' back
		$this->appConfig->expects($this->once())->method('clearCache');

		$this->service->enable('myapp');

		$this->assertSame(['myapp'], $this->service->occCalls);
	}

	public function testEnableOfNeverInstalledAppFallsBackToNativeWhenOccFails(): void {
		$this->dbVersion('');
		$this->service->occSucceeds = false;
		$this->appManager->expects($this->once())->method('enableApp')->with('myapp');

		$this->service->enable('myapp');
	}

	public function testEnableOfKnownAppUsesNativeApiOnly(): void {
		$this->dbVersion('1.0.0');
		$this->appManager->expects($this->once())->method('enableApp')->with('myapp');

		$this->service->enable('myapp');

		$this->assertSame([], $this->service->occCalls);
	}

	public function testEnableOfKnownAppFallsBackToOccWhenNativeFails(): void {
		$this->dbVersion('1.0.0');
		$this->appManager->method('enableApp')->willThrowException(new \RuntimeException('native failed'));

		$this->service->enable('myapp');

		$this->assertSame(['myapp'], $this->service->occCalls);
	}

	public function testEnableThrowsAndLogsOriginalWhenEverythingFails(): void {
		$this->dbVersion('');
		$this->service->occSucceeds = false;
		$boom = new \RuntimeException('native failed');
		$this->appManager->method('enableApp')->willThrowException($boom);
		$this->logger->expects($this->atLeastOnce())->method('warning')
			->with($this->anything(), $this->callback(fn (array $ctx) => ($ctx['exception'] ?? null) === $boom));

		$this->expectException(AppInstallException::class);
		$this->expectExceptionMessage('Settings');

		$this->service->enable('myapp');
	}

	public function testDoesNothingWhenAppUnknownToDatabase(): void {
		$this->dbVersion('');
		$this->codeVersion('1.0.0');
		$this->appManager->expects($this->never())->method('upgradeApp');

		$this->service->reconcile('myapp', $this->appPath);
	}

	public function testDoesNothingWhenVersionsMatch(): void {
		$this->dbVersion('1.0.0');
		$this->codeVersion('1.0.0');
		$this->appManager->expects($this->never())->method('upgradeApp');

		$this->service->reconcile('myapp', $this->appPath);
	}

	public function testUpgradesWhenCodeIsNewer(): void {
		$this->dbVersion('1.0.0', '1.1.0');
		$this->codeVersion('1.1.0');
		$this->appManager->expects($this->once())->method('upgradeApp')->with('myapp')->willReturn(true);

		$this->service->reconcile('myapp', $this->appPath);
	}

	public function testUpgradesWhenCodeIsOlder(): void {
		// Backup restore / re-install of an older build: Nextcloud treats any
		// mismatch as "upgrade needed", so we must reconcile in both directions.
		$this->dbVersion('1.1.0', '1.0.0');
		$this->codeVersion('1.0.0');
		$this->appManager->expects($this->once())->method('upgradeApp')->with('myapp')->willReturn(true);

		$this->service->reconcile('myapp', $this->appPath);
	}

	public function testDoesNothingWhenInfoXmlUnreadable(): void {
		$this->dbVersion('1.0.0');
		// no info.xml written
		$this->appManager->expects($this->never())->method('upgradeApp');

		$this->service->reconcile('myapp', $this->appPath);
	}

	public function testWrapsUpgradeFailureWithOccHintAndLogsOriginal(): void {
		$this->dbVersion('1.0.0');
		$this->codeVersion('1.1.0');
		$boom = new \RuntimeException('migration boom');
		$this->appManager->method('upgradeApp')->willThrowException($boom);
		$this->logger->expects($this->once())->method('error')
			->with($this->anything(), $this->callback(fn (array $ctx) => ($ctx['exception'] ?? null) === $boom && $ctx['appId'] === 'myapp'));

		try {
			$this->service->reconcile('myapp', $this->appPath);
			$this->fail('expected AppInstallException');
		} catch (AppInstallException $e) {
			$this->assertStringContainsString('occ upgrade', $e->getMessage());
			$this->assertStringContainsString('migration boom', $e->getMessage());
		}
	}
}
