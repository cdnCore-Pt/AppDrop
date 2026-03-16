<?php

declare(strict_types=1);

namespace OCA\AppDrop\Tests\Unit\Controller;

use OCA\AppDrop\Controller\BackupController;
use OCA\AppDrop\Service\BackupService;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class BackupControllerTest extends TestCase
{
    private IRequest&MockObject $request;
    private IUserSession&MockObject $userSession;
    private IGroupManager&MockObject $groupManager;
    private BackupService&MockObject $backupService;
    private BackupController $controller;

    protected function setUp(): void
    {
        $this->request = $this->createMock(IRequest::class);
        $this->userSession = $this->createMock(IUserSession::class);
        $this->groupManager = $this->createMock(IGroupManager::class);
        $this->backupService = $this->createMock(BackupService::class);

        $this->controller = new BackupController(
            'appdrop',
            $this->request,
            $this->userSession,
            $this->groupManager,
            $this->backupService,
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

    public function testListDeniesNonAdmin(): void
    {
        $this->mockNonAdmin();
        $this->assertSame(403, $this->controller->list()->getStatus());
    }

    public function testListReturnsBackups(): void
    {
        $this->mockAdmin();
        $backups = [['appId' => 'calc', 'date' => '2026-03-15 12:00:00', 'dirName' => 'calc_backup_20260315_120000']];
        $this->backupService->method('listBackups')->willReturn($backups);

        $data = $this->controller->list()->getData();

        $this->assertTrue($data['success']);
        $this->assertSame($backups, $data['backups']);
    }

    public function testListHandlesException(): void
    {
        $this->mockAdmin();
        $this->backupService->method('listBackups')
            ->willThrowException(new \RuntimeException('fail'));

        $data = $this->controller->list()->getData();
        $this->assertFalse($data['success']);
    }

    public function testRestoreDeniesNonAdmin(): void
    {
        $this->mockNonAdmin();
        $this->assertSame(403, $this->controller->restore()->getStatus());
    }

    public function testDeleteDeniesNonAdmin(): void
    {
        $this->mockNonAdmin();
        $this->assertSame(403, $this->controller->delete()->getStatus());
    }
}
