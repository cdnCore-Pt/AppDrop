<?php

declare(strict_types=1);

namespace OCA\AppDrop\Tests\Unit\Controller;

use OCA\AppDrop\Controller\AppManagerController;
use OCA\AppDrop\Service\AppInstallException;
use OCA\AppDrop\Service\AppManagerService;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AppManagerControllerTest extends TestCase
{
    private IRequest&MockObject $request;
    private IUserSession&MockObject $userSession;
    private IGroupManager&MockObject $groupManager;
    private AppManagerService&MockObject $appManagerService;
    private AppManagerController $controller;

    protected function setUp(): void
    {
        $this->request = $this->createMock(IRequest::class);
        $this->userSession = $this->createMock(IUserSession::class);
        $this->groupManager = $this->createMock(IGroupManager::class);
        $this->appManagerService = $this->createMock(AppManagerService::class);

        $this->controller = new AppManagerController(
            'appdrop',
            $this->request,
            $this->userSession,
            $this->groupManager,
            $this->appManagerService,
        );
    }

    private function mockAdmin(): void
    {
        $user = $this->createMock(IUser::class);
        $user->method('getUID')->willReturn('admin');
        $this->userSession->method('getUser')->willReturn($user);
        $this->groupManager->method('isInGroup')->with('admin', 'admin')->willReturn(true);
    }

    private function mockNonAdmin(): void
    {
        $user = $this->createMock(IUser::class);
        $user->method('getUID')->willReturn('user');
        $this->userSession->method('getUser')->willReturn($user);
        $this->groupManager->method('isInGroup')->with('user', 'admin')->willReturn(false);
    }

    // =========================================================================
    // list()
    // =========================================================================

    public function testListDeniesNonAdmin(): void
    {
        $this->mockNonAdmin();

        $response = $this->controller->list();

        $this->assertSame(403, $response->getStatus());
    }

    public function testListReturnsApps(): void
    {
        $this->mockAdmin();
        $apps = [['id' => 'calc', 'name' => 'Calc', 'version' => '1.0.0', 'enabled' => true]];
        $this->appManagerService->method('listApps')->willReturn($apps);

        $response = $this->controller->list();
        $data = $response->getData();

        $this->assertTrue($data['success']);
        $this->assertSame($apps, $data['apps']);
    }

    public function testListHandlesException(): void
    {
        $this->mockAdmin();
        $this->appManagerService->method('listApps')
            ->willThrowException(new \RuntimeException('fail'));

        $response = $this->controller->list();
        $data = $response->getData();

        $this->assertFalse($data['success']);
        $this->assertSame('fail', $data['message']);
    }

    // =========================================================================
    // enable() / disable() / remove() admin check
    // =========================================================================

    public function testEnableDeniesNonAdmin(): void
    {
        $this->mockNonAdmin();
        $this->assertSame(403, $this->controller->enable()->getStatus());
    }

    public function testDisableDeniesNonAdmin(): void
    {
        $this->mockNonAdmin();
        $this->assertSame(403, $this->controller->disable()->getStatus());
    }

    public function testRemoveDeniesNonAdmin(): void
    {
        $this->mockNonAdmin();
        $this->assertSame(403, $this->controller->remove()->getStatus());
    }
}
