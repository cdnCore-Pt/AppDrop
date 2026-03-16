<?php

declare(strict_types=1);

namespace OCA\AppDrop\Tests\Unit\Service;

use OCA\AppDrop\Service\AppPathResolver;
use OCP\IConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AppPathResolverTest extends TestCase {
	private IConfig&MockObject $config;
	private AppPathResolver $resolver;

	protected function setUp(): void {
		$this->config = $this->createMock(IConfig::class);
		$this->resolver = new AppPathResolver($this->config);
	}

	public function testPrefersCustomAppsPath(): void {
		$this->config->method('getSystemValue')
			->with('apps_paths', [])
			->willReturn([
				['path' => '/var/www/html/apps', 'url' => '/apps', 'writable' => true],
				['path' => '/var/www/html/custom_apps', 'url' => '/custom_apps', 'writable' => true],
			]);

		$this->assertSame('/var/www/html/custom_apps', $this->resolver->resolveWritablePath());
	}

	public function testPrefersExtraAppsPath(): void {
		$this->config->method('getSystemValue')
			->with('apps_paths', [])
			->willReturn([
				['path' => '/var/www/html/apps', 'url' => '/apps', 'writable' => true],
				['path' => '/var/www/html/extra_apps/', 'url' => '/extra', 'writable' => true],
			]);

		$this->assertSame('/var/www/html/extra_apps', $this->resolver->resolveWritablePath());
	}

	public function testFallsBackToFirstWritablePath(): void {
		$this->config->method('getSystemValue')
			->with('apps_paths', [])
			->willReturn([
				['path' => '/var/www/html/apps', 'url' => '/apps', 'writable' => false],
				['path' => '/var/www/html/myapps/', 'url' => '/myapps', 'writable' => true],
			]);

		$this->assertSame('/var/www/html/myapps', $this->resolver->resolveWritablePath());
	}

	public function testSkipsNonWritablePaths(): void {
		$this->config->method('getSystemValue')
			->with('apps_paths', [])
			->willReturn([
				['path' => '/var/www/html/apps', 'url' => '/apps', 'writable' => false],
				['path' => '/var/www/html/custom_apps', 'url' => '/custom', 'writable' => true],
			]);

		$this->assertSame('/var/www/html/custom_apps', $this->resolver->resolveWritablePath());
	}

	public function testTrimsTrailingSlashes(): void {
		$this->config->method('getSystemValue')
			->with('apps_paths', [])
			->willReturn([
				['path' => '/var/www/html/custom_apps/', 'url' => '/custom', 'writable' => true],
			]);

		$result = $this->resolver->resolveWritablePath();
		$this->assertSame('/var/www/html/custom_apps', $result);
	}
}
