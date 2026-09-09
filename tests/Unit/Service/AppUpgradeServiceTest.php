<?php

declare(strict_types=1);

namespace OCA\AppDrop\Tests\Unit\Service;

use OCA\AppDrop\Service\AppInstallException;
use OCA\AppDrop\Service\AppUpgradeService;
use OCP\App\IAppManager;
use OCP\IConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AppUpgradeServiceTest extends TestCase {
	private IAppManager&MockObject $appManager;
	private IConfig&MockObject $config;
	private LoggerInterface&MockObject $logger;
	private AppUpgradeService $service;
	private string $appPath;

	protected function setUp(): void {
		$this->appManager = $this->createMock(IAppManager::class);
		$this->config = $this->createMock(IConfig::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->service = new AppUpgradeService($this->appManager, $this->config, $this->logger);

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

	private function dbVersion(string $version): void {
		$this->config->method('getAppValue')->with('myapp', 'installed_version', '')->willReturn($version);
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
		$this->dbVersion('1.0.0');
		$this->codeVersion('1.1.0');
		$this->appManager->expects($this->once())->method('upgradeApp')->with('myapp')->willReturn(true);

		$this->service->reconcile('myapp', $this->appPath);
	}

	public function testUpgradesWhenCodeIsOlder(): void {
		// Backup restore / re-install of an older build: Nextcloud treats any
		// mismatch as "upgrade needed", so we must reconcile in both directions.
		$this->dbVersion('1.1.0');
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
