<?php

declare(strict_types=1);

namespace OCA\AppDrop\Tests\Unit\Controller;

use OCA\AppDrop\Controller\SettingsController;
use OCA\AppDrop\Service\PermissionService;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SettingsControllerTest extends TestCase {
	private IRequest&MockObject $request;
	private IUserSession&MockObject $userSession;
	private IGroupManager&MockObject $groupManager;
	private PermissionService&MockObject $permissionService;
	private IConfig&MockObject $config;
	private SettingsController $controller;

	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->permissionService = $this->createMock(PermissionService::class);
		$this->config = $this->createMock(IConfig::class);

		$this->controller = new SettingsController(
			'appdrop',
			$this->request,
			$this->userSession,
			$this->groupManager,
			$this->permissionService,
			$this->config,
		);
	}

	private function mockAdmin(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('admin');
		$this->userSession->method('getUser')->willReturn($user);
		$this->groupManager->method('isInGroup')->with('admin', 'admin')->willReturn(true);
	}

	private function mockNonAdmin(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user');
		$this->userSession->method('getUser')->willReturn($user);
		$this->groupManager->method('isInGroup')->with('user', 'admin')->willReturn(false);
	}

	// =========================================================================
	// get()
	// =========================================================================

	public function testGetDeniesNonAdmin(): void {
		$this->mockNonAdmin();
		$this->assertSame(403, $this->controller->get()->getStatus());
	}

	public function testGetReturnsSettings(): void {
		$this->mockAdmin();
		$this->config->method('getAppValue')
			->willReturnMap([
				['appdrop', 'max_upload_size_mb', '20', '50'],
				['appdrop', 'auto_enable_default', '1', '0'],
			]);

		$data = $this->controller->get()->getData();

		$this->assertTrue($data['success']);
		$this->assertSame(50, $data['maxSizeMB']);
		$this->assertFalse($data['autoEnable']);
	}

	// =========================================================================
	// save()
	// =========================================================================

	public function testSaveDeniesNonAdmin(): void {
		$this->mockNonAdmin();
		$this->assertSame(403, $this->controller->save()->getStatus());
	}
}
