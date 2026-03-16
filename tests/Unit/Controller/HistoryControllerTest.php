<?php

declare(strict_types=1);

namespace OCA\AppDrop\Tests\Unit\Controller;

use OCA\AppDrop\Controller\HistoryController;
use OCA\AppDrop\Service\PermissionService;
use OCA\AppDrop\Service\UploadHistoryService;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class HistoryControllerTest extends TestCase {
	private IRequest&MockObject $request;
	private IUserSession&MockObject $userSession;
	private IGroupManager&MockObject $groupManager;
	private UploadHistoryService&MockObject $historyService;
	private PermissionService&MockObject $permissionService;
	private HistoryController $controller;

	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->historyService = $this->createMock(UploadHistoryService::class);
		$this->permissionService = $this->createMock(PermissionService::class);

		$this->controller = new HistoryController(
			'appdrop',
			$this->request,
			$this->userSession,
			$this->groupManager,
			$this->historyService,
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

	public function testListDeniesUnauthorizedUser(): void {
		$this->mockUnauthorized();

		$this->assertSame(403, $this->controller->list()->getStatus());
	}

	public function testListReturnsHistory(): void {
		$this->mockAdmin();
		$this->request->method('getParam')->willReturnMap([
			['page', '1', '1'],
			['limit', '20', '20'],
		]);
		$this->historyService->method('getRecent')
			->with(1, 20)
			->willReturn(['entries' => [], 'total' => 0]);

		$data = $this->controller->list()->getData();

		$this->assertTrue($data['success']);
		$this->assertSame(0, $data['total']);
	}

	public function testListHandlesException(): void {
		$this->mockAdmin();
		$this->request->method('getParam')->willReturn('1');
		$this->historyService->method('getRecent')
			->willThrowException(new \RuntimeException('db error'));

		$data = $this->controller->list()->getData();

		$this->assertFalse($data['success']);
	}

	public function testListClampsPageAndLimit(): void {
		$this->mockAdmin();
		$this->request->method('getParam')->willReturnMap([
			['page', '1', '-5'],
			['limit', '20', '999'],
		]);
		$this->historyService->expects($this->once())
			->method('getRecent')
			->with(1, 100)
			->willReturn(['entries' => [], 'total' => 0]);

		$this->controller->list();
	}
}
