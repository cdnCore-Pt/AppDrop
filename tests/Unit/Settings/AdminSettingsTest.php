<?php

declare(strict_types=1);

namespace OCA\AppDrop\Tests\Unit\Settings;

use OCA\AppDrop\Service\PermissionService;
use OCA\AppDrop\Settings\AdminSettings;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AdminSettingsTest extends TestCase {
	private IL10N&MockObject $l10n;
	private IConfig&MockObject $config;
	private IURLGenerator&MockObject $urlGenerator;
	private PermissionService&MockObject $permissionService;
	private AdminSettings $settings;

	protected function setUp(): void {
		$this->l10n = $this->createMock(IL10N::class);
		$this->config = $this->createMock(IConfig::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->permissionService = $this->createMock(PermissionService::class);

		$this->settings = new AdminSettings(
			$this->l10n,
			$this->config,
			$this->urlGenerator,
			$this->permissionService,
		);
	}

	public function testGetSection(): void {
		$this->assertSame('appdrop', $this->settings->getSection());
	}

	public function testGetPriority(): void {
		$this->assertSame(10, $this->settings->getPriority());
	}

	public function testGetFormReturnsTemplateResponse(): void {
		$this->config->method('getAppValue')
			->willReturnMap([
				['appdrop', 'max_upload_size_mb', '20', '30'],
				['appdrop', 'auto_enable_default', '1', '1'],
			]);
		$this->permissionService->method('getAllowedUsers')->willReturn(['alice']);
		$this->permissionService->method('getAllowedGroups')->willReturn(['editors']);
		$this->urlGenerator->method('linkToRoute')
			->with('appdrop.admin.index')
			->willReturn('/apps/appdrop/admin');

		$response = $this->settings->getForm();

		$this->assertInstanceOf(TemplateResponse::class, $response);
		$this->assertSame('settings/admin', $response->getTemplateName());

		$params = $response->getParams();
		$this->assertSame(30, $params['maxSizeMB']);
		$this->assertTrue($params['autoEnable']);
		$this->assertSame(['alice'], $params['allowedUsers']);
		$this->assertSame(['editors'], $params['allowedGroups']);
		$this->assertSame('/apps/appdrop/admin', $params['appUrl']);
	}
}
