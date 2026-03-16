<?php

declare(strict_types=1);

namespace OCA\AppDrop\Tests\Unit\Controller;

use OCA\AppDrop\Controller\HealthCheckController;
use OCA\AppDrop\Service\HealthCheckService;
use OCA\AppDrop\Service\PermissionService;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\ITempManager;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class HealthCheckControllerTest extends TestCase {
	private IRequest&MockObject $request;
	private IUserSession&MockObject $userSession;
	private IGroupManager&MockObject $groupManager;
	private HealthCheckService&MockObject $healthCheckService;
	private ITempManager&MockObject $tempManager;
	private PermissionService&MockObject $permissionService;
	private HealthCheckController $controller;

	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->healthCheckService = $this->createMock(HealthCheckService::class);
		$this->tempManager = $this->createMock(ITempManager::class);
		$this->permissionService = $this->createMock(PermissionService::class);

		$this->controller = new HealthCheckController(
			'appdrop',
			$this->request,
			$this->userSession,
			$this->groupManager,
			$this->healthCheckService,
			$this->tempManager,
			$this->permissionService,
		);
	}

	private function mockAdmin(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('admin');
		$this->userSession->method('getUser')->willReturn($user);
		$this->groupManager->method('isInGroup')->with('admin', 'admin')->willReturn(true);
	}

	private function mockUnauthorized(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('nobody');
		$this->userSession->method('getUser')->willReturn($user);
		$this->groupManager->method('isInGroup')->willReturn(false);
		$this->permissionService->method('canUpload')->willReturn(false);
	}

	public function testValidateDeniesUnauthorizedUser(): void {
		$this->mockUnauthorized();

		$response = $this->controller->validate();

		$this->assertSame(403, $response->getStatus());
	}

	public function testValidateNoFileReturnsError(): void {
		$this->mockAdmin();
		$this->request->method('getUploadedFile')->with('zipFile')->willReturn(null);

		$data = $this->controller->validate()->getData();

		$this->assertContains('No file uploaded.', $data['errors']);
	}

	public function testValidateEmptyTmpNameReturnsError(): void {
		$this->mockAdmin();
		$this->request->method('getUploadedFile')
			->with('zipFile')
			->willReturn(['tmp_name' => '', 'name' => 'app.zip']);

		$data = $this->controller->validate()->getData();

		$this->assertContains('No file uploaded.', $data['errors']);
	}

	public function testValidateTempFileFailureReturnsError(): void {
		$this->mockAdmin();
		$this->request->method('getUploadedFile')
			->with('zipFile')
			->willReturn(['tmp_name' => '/tmp/fake', 'name' => 'app.zip']);
		$this->tempManager->method('getTemporaryFile')->willReturn(false);

		$data = $this->controller->validate()->getData();

		$this->assertContains('Could not create temporary file.', $data['errors']);
	}
}
