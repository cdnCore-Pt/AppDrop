<?php

declare(strict_types=1);

namespace OCA\AppDrop\Tests\Unit\Service;

use OCA\AppDrop\Service\AppInstallException;
use OCA\AppDrop\Service\AppManagerService;
use OCA\AppDrop\Service\AppPathResolver;
use OCA\AppDrop\Service\AppUpgradeService;
use OCP\App\IAppManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AppManagerServiceTest extends TestCase {
	private AppPathResolver&MockObject $pathResolver;
	private IAppManager&MockObject $appManager;
	private LoggerInterface&MockObject $logger;
	private AppManagerService $service;
	private AppUpgradeService&MockObject $upgrader;
	private string $tmpDir;

	protected function setUp(): void {
		$this->pathResolver = $this->createMock(AppPathResolver::class);
		$this->appManager = $this->createMock(IAppManager::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->upgrader = $this->createMock(AppUpgradeService::class);
		$this->service = new AppManagerService($this->pathResolver, $this->appManager, $this->upgrader, $this->logger);

		$this->tmpDir = sys_get_temp_dir() . '/appdrop_mgr_test_' . uniqid();
		mkdir($this->tmpDir, 0755, true);
		$this->pathResolver->method('resolveWritablePath')->willReturn($this->tmpDir);
	}

	protected function tearDown(): void {
		$this->removeDir($this->tmpDir);
	}

	private function removeDir(string $path): void {
		if (!is_dir($path)) {
			return;
		}
		$items = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::CHILD_FIRST,
		);
		foreach ($items as $item) {
			$item->isDir() ? rmdir($item->getRealPath()) : unlink($item->getRealPath());
		}
		rmdir($path);
	}

	private function createApp(string $id, string $name = '', string $version = '1.0.0'): void {
		$dir = $this->tmpDir . '/' . $id . '/appinfo';
		mkdir($dir, 0755, true);
		$appName = $name ?: $id;
		file_put_contents($dir . '/info.xml', "<?xml version=\"1.0\"?><info><id>{$id}</id><name>{$appName}</name><version>{$version}</version></info>");
	}

	// =========================================================================
	// listApps()
	// =========================================================================

	public function testListAppsReturnsEmptyForNoApps(): void {
		$this->assertSame([], $this->service->listApps());
	}

	public function testListAppsFindsAppsWithInfoXml(): void {
		$this->createApp('calc', 'Calculator', '2.0.0');
		$this->appManager->method('isEnabledForUser')->willReturn(true);

		$apps = $this->service->listApps();

		$this->assertCount(1, $apps);
		$this->assertSame('calc', $apps[0]['id']);
		$this->assertSame('Calculator', $apps[0]['name']);
		$this->assertSame('2.0.0', $apps[0]['version']);
		$this->assertTrue($apps[0]['enabled']);
	}

	public function testListAppsSkipsBackupDirectories(): void {
		$this->createApp('myapp');
		mkdir($this->tmpDir . '/myapp_backup_20260315_120000');

		$apps = $this->service->listApps();

		$this->assertCount(1, $apps);
		$this->assertSame('myapp', $apps[0]['id']);
	}

	public function testListAppsSkipsDirsWithoutInfoXml(): void {
		mkdir($this->tmpDir . '/noinfo');

		$this->assertSame([], $this->service->listApps());
	}

	public function testListAppsSortsByIdAlphabetically(): void {
		$this->createApp('zebra');
		$this->createApp('alpha');

		$apps = $this->service->listApps();

		$this->assertSame('alpha', $apps[0]['id']);
		$this->assertSame('zebra', $apps[1]['id']);
	}

	// =========================================================================
	// validateAppId
	// =========================================================================

	#[DataProvider('invalidAppIdProvider')]
	public function testEnableAppRejectsInvalidId(string $appId): void {
		$this->expectException(AppInstallException::class);
		$this->expectExceptionMessage('Invalid app ID');

		$this->service->enableApp($appId);
	}

	public static function invalidAppIdProvider(): array {
		return [
			'uppercase' => ['MyApp'],
			'dashes' => ['my-app'],
			'too short' => ['ab'],
			'spaces' => ['my app'],
			'special' => ['app@1'],
		];
	}

	// =========================================================================
	// enableApp() / disableApp()
	// =========================================================================

	public function testEnableAppDelegatesToTheSharedEnableRoutine(): void {
		$this->upgrader->expects($this->once())->method('enable')->with('my_app');

		$this->service->enableApp('my_app');
	}

	public function testDisableAppRefusesAppDropItself(): void {
		$this->appManager->expects($this->never())->method('disableApp');
		$this->expectException(AppInstallException::class);

		$this->service->disableApp('appdrop');
	}

	public function testRemoveAppRefusesAppDropItself(): void {
		$this->createApp('appdrop');
		$this->appManager->expects($this->never())->method('disableApp');

		try {
			$this->service->removeApp('appdrop');
			$this->fail('expected AppInstallException');
		} catch (AppInstallException) {
			$this->assertDirectoryExists($this->tmpDir . '/appdrop');
		}
	}

	public function testDisableAppWrapsCoreRefusalAndLogsOriginal(): void {
		// e.g. shipped apps: core throws "files can't be disabled" → was an HTTP 500
		$boom = new \Exception("files can't be disabled.");
		$this->appManager->method('disableApp')->willThrowException($boom);
		$this->logger->expects($this->once())->method('warning')
			->with($this->anything(), $this->callback(fn (array $ctx) => ($ctx['exception'] ?? null) === $boom));

		$this->expectException(AppInstallException::class);
		$this->expectExceptionMessage("can't be disabled");

		$this->service->disableApp('files');
	}

	public function testDisableAppCallsAppManager(): void {
		$this->appManager->expects($this->once())
			->method('disableApp')
			->with('my_app');

		$this->service->disableApp('my_app');
	}

	public function testDisableAppRejectsInvalidId(): void {
		$this->expectException(AppInstallException::class);

		$this->service->disableApp('Bad-Id');
	}

	// =========================================================================
	// removeApp()
	// =========================================================================

	public function testRemoveAppDeletesDirectory(): void {
		$this->createApp('my_app');

		$this->service->removeApp('my_app');

		$this->assertDirectoryDoesNotExist($this->tmpDir . '/my_app');
	}

	public function testRemoveAppDisablesFirst(): void {
		$this->createApp('my_app');

		$this->appManager->expects($this->once())
			->method('disableApp')
			->with('my_app');

		$this->service->removeApp('my_app');
	}

	public function testRemoveAppDoesNotDisableAppsItCannotRemove(): void {
		// e.g. a shipped app: not in the writable apps path, must stay untouched
		$this->appManager->expects($this->never())->method('disableApp');
		$this->expectException(AppInstallException::class);

		$this->service->removeApp('weather_status');
	}

	public function testRemoveAppThrowsForNonExistentApp(): void {
		$this->expectException(AppInstallException::class);
		$this->expectExceptionMessage('not found');

		$this->service->removeApp('nonexistent');
	}

	public function testRemoveAppRejectsInvalidId(): void {
		$this->expectException(AppInstallException::class);

		$this->service->removeApp('../etc');
	}
}
