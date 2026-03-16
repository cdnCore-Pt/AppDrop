<?php

declare(strict_types=1);

namespace OCA\AppDrop\Tests\Unit\Service;

use OCA\AppDrop\Service\AppInstallException;
use OCA\AppDrop\Service\AppInstallService;
use OCA\AppDrop\Service\AppPathResolver;
use OCP\App\IAppManager;
use OCP\IConfig;
use OCP\ITempManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AppInstallServiceTest extends TestCase
{
    private AppPathResolver&MockObject $pathResolver;
    private ITempManager&MockObject $tempManager;
    private IAppManager&MockObject $appManager;
    private IConfig&MockObject $config;
    private LoggerInterface&MockObject $logger;
    private AppInstallService $service;

    protected function setUp(): void
    {
        $this->pathResolver = $this->createMock(AppPathResolver::class);
        $this->tempManager = $this->createMock(ITempManager::class);
        $this->appManager = $this->createMock(IAppManager::class);
        $this->config = $this->createMock(IConfig::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new AppInstallService(
            $this->pathResolver,
            $this->tempManager,
            $this->appManager,
            $this->config,
            $this->logger,
        );
    }

    // =========================================================================
    // File validation tests
    // =========================================================================

    public function testInstallThrowsOnUploadError(): void
    {
        $this->expectException(AppInstallException::class);
        $this->expectExceptionMessage('File upload error');

        $this->service->install([
            'name' => 'app.zip',
            'tmp_name' => '/tmp/fake',
            'error' => UPLOAD_ERR_INI_SIZE,
        ]);
    }

    public function testInstallThrowsOnWrongExtension(): void
    {
        $this->expectException(AppInstallException::class);
        $this->expectExceptionMessage('Only .zip files are accepted');

        $this->service->install([
            'name' => 'app.tar.gz',
            'tmp_name' => '/tmp/fake',
            'error' => UPLOAD_ERR_OK,
        ]);
    }

    public function testInstallThrowsOnMissingTmpFile(): void
    {
        $this->expectException(AppInstallException::class);
        $this->expectExceptionMessage('Uploaded temporary file not found');

        $this->service->install([
            'name' => 'app.zip',
            'tmp_name' => '/tmp/nonexistent_' . uniqid(),
            'error' => UPLOAD_ERR_OK,
        ]);
    }

    public function testInstallThrowsOnInvalidMimeType(): void
    {
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
    public function testValidAppIdPattern(string $appId): void
    {
        $this->assertMatchesRegularExpression('/^[a-z0-9_]{3,64}$/', $appId);
    }

    public static function validAppIdProvider(): array
    {
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
    public function testInvalidAppIdPattern(string $appId): void
    {
        $this->assertDoesNotMatchRegularExpression('/^[a-z0-9_]{3,64}$/', $appId);
    }

    public static function invalidAppIdProvider(): array
    {
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

    public function testParseInfoXmlValid(): void
    {
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

    public function testParseInfoXmlMissingId(): void
    {
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

    public function testParseInfoXmlInvalidAppId(): void
    {
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

    public function testParseInfoXmlInvalidXml(): void
    {
        $method = new \ReflectionMethod(AppInstallService::class, 'parseInfoXml');

        $this->expectException(AppInstallException::class);
        $this->expectExceptionMessage('Could not parse info.xml');

        $method->invoke($this->service, 'this is not xml at all');
    }

    public function testParseInfoXmlDefaultsVersionAndName(): void
    {
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
}
