<?php

declare(strict_types=1);

namespace OCA\AppDrop\Tests\Unit\Service;

use OCA\AppDrop\Service\AppInstallException;
use OCA\AppDrop\Service\AppInstallService;
use OCA\AppDrop\Service\AppPathResolver;
use OCP\App\IAppManager;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\ITempManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AppInstallServiceTest extends TestCase {
	private AppPathResolver&MockObject $pathResolver;
	private ITempManager&MockObject $tempManager;
	private IAppManager&MockObject $appManager;
	private IConfig&MockObject $config;
	private LoggerInterface&MockObject $logger;
	private AppInstallService $service;
	private RecordingUpgradeService $upgrader;

	protected function setUp(): void {
		$this->pathResolver = $this->createMock(AppPathResolver::class);
		$this->tempManager = $this->createMock(ITempManager::class);
		$this->appManager = $this->createMock(IAppManager::class);
		$this->config = $this->createMock(IConfig::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->upgrader = new RecordingUpgradeService($this->appManager, $this->createMock(IAppConfig::class), $this->logger);
		$this->upgrader->installedVersionReader = static fn (): string => '';

		$this->service = new AppInstallService(
			$this->pathResolver,
			$this->tempManager,
			$this->appManager,
			$this->config,
			$this->upgrader,
			$this->logger,
		);
	}

	// =========================================================================
	// File validation tests
	// =========================================================================

	public function testInstallThrowsOnUploadError(): void {
		$this->expectException(AppInstallException::class);
		$this->expectExceptionMessage('File upload error');

		$this->service->install([
			'name' => 'app.zip',
			'tmp_name' => '/tmp/fake',
			'error' => UPLOAD_ERR_INI_SIZE,
		]);
	}

	public function testInstallThrowsOnWrongExtension(): void {
		$this->expectException(AppInstallException::class);
		$this->expectExceptionMessage('Only .zip files are accepted');

		$this->service->install([
			'name' => 'app.tar.gz',
			'tmp_name' => '/tmp/fake',
			'error' => UPLOAD_ERR_OK,
		]);
	}

	public function testInstallThrowsOnMissingTmpFile(): void {
		$this->expectException(AppInstallException::class);
		$this->expectExceptionMessage('Uploaded temporary file not found');

		$this->service->install([
			'name' => 'app.zip',
			'tmp_name' => '/tmp/nonexistent_' . uniqid(),
			'error' => UPLOAD_ERR_OK,
		]);
	}

	public function testInstallThrowsOnInvalidMimeType(): void {
		$this->expectException(AppInstallException::class);
		$this->expectExceptionMessage('Invalid file type');

		$tmp = tempnam(sys_get_temp_dir(), 'test_');
		file_put_contents($tmp, 'this is not a zip file');

		try {
			$this->service->install([
				'name' => 'app.zip',
				'tmp_name' => $tmp,
				'error' => UPLOAD_ERR_OK,
			]);
		} finally {
			@unlink($tmp);
		}
	}

	// =========================================================================
	// App ID validation tests (via parseInfoXml, invoked through install)
	// =========================================================================

	#[DataProvider('validAppIdProvider')]
	public function testValidAppIdPattern(string $appId): void {
		$this->assertMatchesRegularExpression('/^[a-z0-9_]{3,64}$/', $appId);
	}

	public static function validAppIdProvider(): array {
		return [
			'simple' => ['myapp'],
			'with underscores' => ['my_app'],
			'with numbers' => ['app123'],
			'minimum length' => ['abc'],
			'all digits' => ['123'],
			'mixed' => ['my_cool_app_42'],
		];
	}

	#[DataProvider('invalidAppIdProvider')]
	public function testInvalidAppIdPattern(string $appId): void {
		$this->assertDoesNotMatchRegularExpression('/^[a-z0-9_]{3,64}$/', $appId);
	}

	public static function invalidAppIdProvider(): array {
		return [
			'too short' => ['ab'],
			'uppercase' => ['MyApp'],
			'with dash' => ['my-app'],
			'with spaces' => ['my app'],
			'empty' => [''],
			'special chars' => ['app@home'],
			'with dots' => ['my.app'],
		];
	}

	// =========================================================================
	// info.xml parsing tests (via reflection to test the private method)
	// =========================================================================

	public function testParseInfoXmlValid(): void {
		$method = new \ReflectionMethod(AppInstallService::class, 'parseInfoXml');

		$xml = <<<'XML'
        <?xml version="1.0"?>
        <info>
            <id>my_test_app</id>
            <name>My Test App</name>
            <version>1.2.3</version>
        </info>
        XML;

		$result = $method->invoke($this->service, $xml);

		$this->assertSame('my_test_app', $result['appId']);
		$this->assertSame('1.2.3', $result['version']);
		$this->assertSame('My Test App', $result['name']);
	}

	public function testParseInfoXmlMissingId(): void {
		$method = new \ReflectionMethod(AppInstallService::class, 'parseInfoXml');

		$xml = <<<'XML'
        <?xml version="1.0"?>
        <info>
            <name>My Test App</name>
            <version>1.0.0</version>
        </info>
        XML;

		$this->expectException(AppInstallException::class);
		$this->expectExceptionMessage('missing the required <id> element');

		$method->invoke($this->service, $xml);
	}

	public function testParseInfoXmlInvalidAppId(): void {
		$method = new \ReflectionMethod(AppInstallService::class, 'parseInfoXml');

		$xml = <<<'XML'
        <?xml version="1.0"?>
        <info>
            <id>Invalid-App-ID</id>
            <name>Bad App</name>
            <version>1.0.0</version>
        </info>
        XML;

		$this->expectException(AppInstallException::class);
		$this->expectExceptionMessage('Invalid app ID');

		$method->invoke($this->service, $xml);
	}

	public function testParseInfoXmlInvalidXml(): void {
		$method = new \ReflectionMethod(AppInstallService::class, 'parseInfoXml');

		$this->expectException(AppInstallException::class);
		$this->expectExceptionMessage('Could not parse info.xml');

		$method->invoke($this->service, 'this is not xml at all');
	}

	public function testParseInfoXmlDefaultsVersionAndName(): void {
		$method = new \ReflectionMethod(AppInstallService::class, 'parseInfoXml');

		$xml = <<<'XML'
        <?xml version="1.0"?>
        <info>
            <id>test_app</id>
        </info>
        XML;

		$result = $method->invoke($this->service, $xml);

		$this->assertSame('test_app', $result['appId']);
		$this->assertSame('0.0.0', $result['version']);
		$this->assertSame('test_app', $result['name']);
	}

	// =========================================================================
	// End-to-end install() flow (real zip, real temp apps dir)
	// =========================================================================

	private function makeAppsDir(): string {
		$dir = sys_get_temp_dir() . '/appdrop_apps_' . uniqid();
		mkdir($dir, 0755, true);
		return $dir;
	}

	private function makeAppZip(string $dir, string $appId, string $version): string {
		$path = $dir . '/' . $appId . '-' . $version . '.zip';
		$zip = new \ZipArchive();
		$zip->open($path, \ZipArchive::CREATE);
		$zip->addEmptyDir($appId);
		$zip->addFromString(
			$appId . '/appinfo/info.xml',
			"<?xml version=\"1.0\"?><info><id>{$appId}</id><name>Demo</name><version>{$version}</version></info>",
		);
		$zip->addFromString($appId . '/lib/AppInfo/Application.php', '<?php');
		$zip->close();
		return $path;
	}

	private function removeDir(string $path): void {
		if (!is_dir($path)) {
			return;
		}
		$it = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::CHILD_FIRST,
		);
		foreach ($it as $item) {
			$item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
		}
		rmdir($path);
	}

	/**
	 * Wires the mocks so install() can run end-to-end against $appsDir.
	 *
	 * @param string $installedVersion what oc_appconfig says about dropdemo ('' = unknown to the DB)
	 */
	private function wireInstall(string $appsDir, string $installedVersion = ''): void {
		$this->pathResolver->method('resolveWritablePath')->willReturn($appsDir);
		$this->tempManager->method('getTemporaryFile')->willReturn($appsDir . '/upload.tmp.zip');
		// The DB answers $installedVersion until the upgrade routine has had
		// its chance to run; from the second read on it reflects the code on
		// disk, like a successful IAppManager::upgradeApp() would leave it.
		$reads = 0;
		$this->upgrader->installedVersionReader = static function (string $app) use ($appsDir, $installedVersion, &$reads): string {
			if ($installedVersion === '' || $reads++ === 0) {
				return $installedVersion;
			}
			$xml = simplexml_load_file($appsDir . '/' . $app . '/appinfo/info.xml');
			return (string)$xml->version;
		};
		$this->config->method('getAppValue')->willReturnCallback(
			static fn (string $app, string $key, $default) => $key === 'max_upload_size_mb' ? '20' : $default,
		);
	}

	private function makeExistingApp(string $appsDir, string $appId, string $version): void {
		mkdir($appsDir . '/' . $appId . '/appinfo', 0755, true);
		file_put_contents(
			$appsDir . '/' . $appId . '/appinfo/info.xml',
			"<?xml version=\"1.0\"?><info><id>{$appId}</id><version>{$version}</version></info>",
		);
	}

	public function testInstallsWrappedZipWithoutDirectoryEntries(): void {
		// Zips built with ZipArchive::addFromString / `zip -D` / Python zipfile
		// (and AppDrop's own template generator) carry no "app/" folder entry.
		$appsDir = $this->makeAppsDir();
		$this->wireInstall($appsDir);
		$path = $appsDir . '/nodirs.zip';
		$zip = new \ZipArchive();
		$zip->open($path, \ZipArchive::CREATE);
		$zip->addFromString(
			'dropdemo/appinfo/info.xml',
			'<?xml version="1.0"?><info><id>dropdemo</id><name>Demo</name><version>1.0.0</version></info>',
		);
		$zip->addFromString('dropdemo/lib/AppInfo/Application.php', '<?php');
		$zip->close();

		$result = $this->service->install(['name' => 'dropdemo.zip', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK]);

		$this->assertTrue($result['success']);
		$this->assertFileExists($appsDir . '/dropdemo/appinfo/info.xml');
		$this->assertFileExists($appsDir . '/dropdemo/lib/AppInfo/Application.php');
		$this->assertDirectoryDoesNotExist($appsDir . '/dropdemo/dropdemo');

		$this->removeDir($appsDir);
	}

	public function testInstallsWrappedZipWithBackslashEntryNames(): void {
		// Windows PowerShell 5 Compress-Archive writes "app\appinfo\info.xml".
		$appsDir = $this->makeAppsDir();
		$this->wireInstall($appsDir);
		$path = $appsDir . '/backslash.zip';
		$zip = new \ZipArchive();
		$zip->open($path, \ZipArchive::CREATE);
		$zip->addFromString('dropdemo/README.md', 'x');
		$zip->addFromString(
			'dropdemo/appinfo/info.xml',
			'<?xml version="1.0"?><info><id>dropdemo</id><name>Demo</name><version>1.0.0</version></info>',
		);
		$zip->renameName('dropdemo/README.md', 'dropdemo\\README.md');
		$zip->renameName('dropdemo/appinfo/info.xml', 'dropdemo\\appinfo\\info.xml');
		$zip->close();

		$result = $this->service->install(['name' => 'dropdemo.zip', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK]);

		$this->assertTrue($result['success']);
		$this->assertFileExists($appsDir . '/dropdemo/appinfo/info.xml');

		$this->removeDir($appsDir);
	}

	public function testFlatZipWhoseFirstEntryIsAFolderKeepsThatFolder(): void {
		// `cd myapp && zip -r ../myapp.zip .` → first entry "lib/": that is
		// not a wrapper, the zip root already holds appinfo/info.xml.
		$appsDir = $this->makeAppsDir();
		$this->wireInstall($appsDir);
		$path = $appsDir . '/flat.zip';
		$zip = new \ZipArchive();
		$zip->open($path, \ZipArchive::CREATE);
		$zip->addEmptyDir('lib');
		$zip->addFromString('lib/Thing.php', '<?php');
		$zip->addEmptyDir('appinfo');
		$zip->addFromString('appinfo/info.xml', '<?xml version="1.0"?><info><id>dropdemo</id><version>1.0.0</version></info>');
		$zip->close();

		$result = $this->service->install(['name' => 'flat.zip', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK]);

		$this->assertTrue($result['success']);
		$this->assertFileExists($appsDir . '/dropdemo/lib/Thing.php');
		$this->assertFileExists($appsDir . '/dropdemo/appinfo/info.xml');

		$this->removeDir($appsDir);
	}

	public function testAmbiguousPathProblemFlagsDuplicatesAndFileFolderClashes(): void {
		$seen = [];
		$this->assertNull(AppInstallService::ambiguousPathProblem('appinfo/', $seen));
		$this->assertNull(AppInstallService::ambiguousPathProblem('appinfo/', $seen));
		$this->assertNull(AppInstallService::ambiguousPathProblem('appinfo/info.xml', $seen));
		$this->assertStringContainsString('more than once', (string)AppInstallService::ambiguousPathProblem('appinfo/info.xml', $seen));
		// a path cannot be both a file and a folder: extraction would fail half-way
		$this->assertStringContainsString('both a file and a folder', (string)AppInstallService::ambiguousPathProblem('appinfo/info.xml/x', $seen));
		$this->assertNull(AppInstallService::ambiguousPathProblem('lib/a.php', $seen));
		$this->assertStringContainsString('both a file and a folder', (string)AppInstallService::ambiguousPathProblem('lib', $seen));
	}

	#[DataProvider('aliasedInfoXmlProvider')]
	public function testRejectsPathAliasesThatWouldOverwriteAnotherEntry(string $alias): void {
		// "./appinfo/info.xml" or "appinfo//info.xml" land on the same file as
		// "appinfo/info.xml" at extraction time but look different to analysis.
		$appsDir = $this->makeAppsDir();
		$this->wireInstall($appsDir);
		$path = $appsDir . '/alias.zip';
		$zip = new \ZipArchive();
		$zip->open($path, \ZipArchive::CREATE);
		$zip->addFromString('appinfo/info.xml', '<?xml version="1.0"?><info><id>dropdemo</id><version>1.0.0</version></info>');
		$zip->addFromString('placeholder', '<?xml version="1.0"?><info><id>appdrop</id><version>9.9.9</version></info>');
		$zip->renameName('placeholder', $alias);
		$zip->close();

		try {
			$this->service->install(['name' => 'alias.zip', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK]);
			$this->fail('expected AppInstallException');
		} catch (AppInstallException $e) {
			$this->assertStringContainsString('Invalid package', $e->getMessage());
		}
		$this->assertDirectoryDoesNotExist($appsDir . '/dropdemo');

		$this->removeDir($appsDir);
	}

	public static function aliasedInfoXmlProvider(): array {
		return [
			'leading ./' => ['./appinfo/info.xml'],
			'double slash' => ['appinfo//info.xml'],
			'inner ./' => ['appinfo/./info.xml'],
		];
	}

	public function testAcceptsRepeatedFolderEntriesAndDotsInsideFileNames(): void {
		$appsDir = $this->makeAppsDir();
		$this->wireInstall($appsDir);
		$path = $appsDir . '/ok.zip';
		$zip = new \ZipArchive();
		$zip->open($path, \ZipArchive::CREATE);
		$zip->addEmptyDir('dropdemo');
		$zip->addEmptyDir('dropdemo/lib');
		$zip->addFromString('dropdemo/tmpdir', '');
		$zip->renameName('dropdemo/tmpdir', 'dropdemo/lib/');
		$zip->addFromString('dropdemo/appinfo/info.xml', '<?xml version="1.0"?><info><id>dropdemo</id><version>1.0.0</version></info>');
		$zip->addFromString('dropdemo/js/app..min.js', 'x');
		$zip->close();

		$result = $this->service->install(['name' => 'ok.zip', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK]);

		$this->assertTrue($result['success']);
		$this->assertFileExists($appsDir . '/dropdemo/js/app..min.js');

		$this->removeDir($appsDir);
	}

	public function testUpdateGivesInfoXmlANewerMtimeThanTheReplacedOne(): void {
		// Nextcloud caches the parsed info.xml by mtime: an update landing in
		// the same second would upgrade to the OLD version → instance-wide 503.
		$appsDir = $this->makeAppsDir();
		$this->wireInstall($appsDir, '1.0.0');
		$this->makeExistingApp($appsDir, 'dropdemo', '1.0.0');
		$oldMtime = time() + 30;
		touch($appsDir . '/dropdemo/appinfo/info.xml', $oldMtime);
		$zip = $this->makeAppZip($appsDir, 'dropdemo', '1.1.0');
		$this->appManager->method('upgradeApp')->willReturn(true);

		$this->service->install(['name' => 'dropdemo.zip', 'tmp_name' => $zip, 'error' => UPLOAD_ERR_OK]);

		clearstatcache();
		$this->assertGreaterThan($oldMtime, filemtime($appsDir . '/dropdemo/appinfo/info.xml'));

		$this->removeDir($appsDir);
	}

	public function testTwoUpdatesInTheSameSecondKeepBothBackups(): void {
		$appsDir = $this->makeAppsDir();
		$this->wireInstall($appsDir, '1.0.0');
		$this->makeExistingApp($appsDir, 'dropdemo', '1.0.0');
		$this->appManager->method('upgradeApp')->willReturn(true);

		foreach (['1.1.0', '1.2.0'] as $version) {
			$zip = $this->makeAppZip($appsDir, 'dropdemo', $version);
			$this->service->install(['name' => 'dropdemo.zip', 'tmp_name' => $zip, 'error' => UPLOAD_ERR_OK]);
		}

		$this->assertCount(2, glob($appsDir . '/dropdemo_backup_*'));

		$this->removeDir($appsDir);
	}

	public function testMessageDoesNotClaimEnabledWhenAutoEnableIsOff(): void {
		$appsDir = $this->makeAppsDir();
		$this->wireInstall($appsDir);
		$zip = $this->makeAppZip($appsDir, 'dropdemo', '1.0.0');

		$result = $this->service->install(['name' => 'dropdemo.zip', 'tmp_name' => $zip, 'error' => UPLOAD_ERR_OK], false);

		$this->assertStringNotContainsString('enabled', $result['message']);
		$this->assertSame([], $this->upgrader->occCalls);

		$this->removeDir($appsDir);
	}

	public function testFreshInstallEnablesButDoesNotUpgrade(): void {
		$appsDir = $this->makeAppsDir();
		$this->wireInstall($appsDir);
		$zip = $this->makeAppZip($appsDir, 'dropdemo', '1.0.0');

		$this->appManager->expects($this->never())->method('upgradeApp');
		// never installed → the installer (occ app:enable) must run, not the bare enable flag
		$this->appManager->expects($this->never())->method('enableApp');

		$result = $this->service->install(['name' => 'dropdemo.zip', 'tmp_name' => $zip, 'error' => UPLOAD_ERR_OK]);

		$this->assertSame(['dropdemo'], $this->upgrader->occCalls);
		$this->assertTrue($result['success']);
		$this->assertFalse($result['isUpdate']);
		$this->assertSame('1.0.0', $result['version']);
		$this->assertFileExists($appsDir . '/dropdemo/appinfo/info.xml');

		$this->removeDir($appsDir);
	}

	public function testUpdateRunsAppUpgradeSoInstanceDoesNotEnterUpgradeMode(): void {
		$appsDir = $this->makeAppsDir();
		$this->wireInstall($appsDir, '1.0.0');
		$this->makeExistingApp($appsDir, 'dropdemo', '1.0.0');
		$zip = $this->makeAppZip($appsDir, 'dropdemo', '1.1.0');

		// Without this, installed_version (1.0.0) != code version (1.1.0) and Nextcloud
		// answers 503 "upgrade needed" to every request until `occ upgrade`.
		$this->appManager->expects($this->once())->method('upgradeApp')->with('dropdemo')->willReturn(true);
		$this->appManager->expects($this->once())->method('enableApp')->with('dropdemo');

		$result = $this->service->install(['name' => 'dropdemo.zip', 'tmp_name' => $zip, 'error' => UPLOAD_ERR_OK]);

		$this->assertTrue($result['isUpdate']);
		$this->assertSame('1.0.0', $result['previousVersion']);
		$this->assertSame('1.1.0', $result['version']);
		$this->assertNotEmpty(glob($appsDir . '/dropdemo_backup_*'), 'previous version must be backed up');

		$this->removeDir($appsDir);
	}

	public function testUpdateUpgradesEvenWhenAutoEnableIsOff(): void {
		$appsDir = $this->makeAppsDir();
		$this->wireInstall($appsDir, '1.0.0');
		$this->makeExistingApp($appsDir, 'dropdemo', '1.0.0');
		$zip = $this->makeAppZip($appsDir, 'dropdemo', '1.1.0');

		$this->appManager->expects($this->once())->method('upgradeApp')->with('dropdemo')->willReturn(true);
		$this->appManager->expects($this->never())->method('enableApp');

		$this->service->install(['name' => 'dropdemo.zip', 'tmp_name' => $zip, 'error' => UPLOAD_ERR_OK], false);

		$this->removeDir($appsDir);
	}

	public function testUpdateReportsUpgradeFailureWithOccHint(): void {
		$appsDir = $this->makeAppsDir();
		$this->wireInstall($appsDir, '1.0.0');
		$this->makeExistingApp($appsDir, 'dropdemo', '1.0.0');
		$zip = $this->makeAppZip($appsDir, 'dropdemo', '1.1.0');

		$this->appManager->method('upgradeApp')->willThrowException(new \RuntimeException('migration boom'));
		$this->logger->expects($this->atLeastOnce())->method('error');

		try {
			$this->service->install(['name' => 'dropdemo.zip', 'tmp_name' => $zip, 'error' => UPLOAD_ERR_OK]);
			$this->fail('expected AppInstallException');
		} catch (AppInstallException $e) {
			$this->assertStringContainsString('occ upgrade', $e->getMessage());
			$this->assertStringContainsString('migration boom', $e->getMessage());
		}

		$this->removeDir($appsDir);
	}

	public function testReinstallAfterRemoveUpgradesWhenDbStillKnowsAnotherVersion(): void {
		$appsDir = $this->makeAppsDir();
		// Directory is gone (removed via AppDrop or `occ app:remove`) but the DB
		// still records 1.1.0; re-installing 1.0.0 must reconcile or NC goes 503.
		$this->wireInstall($appsDir, '1.1.0');
		$zip = $this->makeAppZip($appsDir, 'dropdemo', '1.0.0');

		$this->appManager->expects($this->once())->method('upgradeApp')->with('dropdemo')->willReturn(true);
		$this->appManager->expects($this->once())->method('enableApp')->with('dropdemo');

		$result = $this->service->install(['name' => 'dropdemo.zip', 'tmp_name' => $zip, 'error' => UPLOAD_ERR_OK]);

		$this->assertFalse($result['isUpdate']);

		$this->removeDir($appsDir);
	}

	public function testReuploadingSameVersionDoesNotUpgrade(): void {
		$appsDir = $this->makeAppsDir();
		$this->wireInstall($appsDir, '1.0.0');
		$this->makeExistingApp($appsDir, 'dropdemo', '1.0.0');
		$zip = $this->makeAppZip($appsDir, 'dropdemo', '1.0.0');

		$this->appManager->expects($this->never())->method('upgradeApp');
		$this->appManager->expects($this->once())->method('enableApp')->with('dropdemo');

		$result = $this->service->install(['name' => 'dropdemo.zip', 'tmp_name' => $zip, 'error' => UPLOAD_ERR_OK]);

		$this->assertTrue($result['isUpdate']);

		$this->removeDir($appsDir);
	}
}
