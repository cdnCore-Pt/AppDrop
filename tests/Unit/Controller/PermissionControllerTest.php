<?php

declare(strict_types=1);

namespace OCA\AppDrop\Tests\Unit\Controller;

use OCA\AppDrop\Controller\PermissionController;
use OCA\AppDrop\Service\PermissionService;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PermissionControllerTest extends TestCase
{
    private IRequest&MockObject $request;
    private IUserSession&MockObject $userSession;
    private IGroupManager&MockObject $groupManager;
    private PermissionService&MockObject $permissionService;
    private IUserManager&MockObject $userManager;
    private PermissionController $controller;

    protected function setUp(): void
    {
        $this->request = $this->createMock(IRequest::class);
        $this->userSession = $this->createMock(IUserSession::class);
        $this->groupManager = $this->createMock(IGroupManager::class);
        $this->permissionService = $this->createMock(PermissionService::class);
        $this->userManager = $this->createMock(IUserManager::class);

        $this->controller = new PermissionController(
            'appdrop',
            $this->request,
            $this->userSession,
            $this->groupManager,
            $this->permissionService,
            $this->userManager,
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
    // Admin checks
    // =========================================================================

    public function testGetDeniesNonAdmin(): void
    {
        $this->mockNonAdmin();
        $this->assertSame(403, $this->controller->get()->getStatus());
    }

    public function testAddUserDeniesNonAdmin(): void
    {
        $this->mockNonAdmin();
        $this->assertSame(403, $this->controller->addUser()->getStatus());
    }

    public function testRemoveUserDeniesNonAdmin(): void
    {
        $this->mockNonAdmin();
        $this->assertSame(403, $this->controller->removeUser()->getStatus());
    }

    public function testAddGroupDeniesNonAdmin(): void
    {
        $this->mockNonAdmin();
        $this->assertSame(403, $this->controller->addGroup()->getStatus());
    }

    public function testRemoveGroupDeniesNonAdmin(): void
    {
        $this->mockNonAdmin();
        $this->assertSame(403, $this->controller->removeGroup()->getStatus());
    }

    public function testSearchUsersDeniesNonAdmin(): void
    {
        $this->mockNonAdmin();
        $this->assertSame(403, $this->controller->searchUsers()->getStatus());
    }

    public function testSearchGroupsDeniesNonAdmin(): void
    {
        $this->mockNonAdmin();
        $this->assertSame(403, $this->controller->searchGroups()->getStatus());
    }

    // =========================================================================
    // get()
    // =========================================================================

    public function testGetReturnsUsersAndGroups(): void
    {
        $this->mockAdmin();
        $this->permissionService->method('getAllowedUsers')->willReturn(['alice']);
        $this->permissionService->method('getAllowedGroups')->willReturn(['editors']);

        $data = $this->controller->get()->getData();

        $this->assertTrue($data['success']);
        $this->assertSame(['alice'], $data['users']);
        $this->assertSame(['editors'], $data['groups']);
    }

    // =========================================================================
    // searchUsers()
    // =========================================================================

    public function testSearchUsersEmptyTermReturnsEmpty(): void
    {
        $this->mockAdmin();
        $this->request->method('getParam')->with('term', '')->willReturn('');

        $data = $this->controller->searchUsers()->getData();

        $this->assertTrue($data['success']);
        $this->assertEmpty($data['results']);
    }

    public function testSearchUsersReturnsResults(): void
    {
        $this->mockAdmin();
        $this->request->method('getParam')->with('term', '')->willReturn('ali');

        $user = $this->createMock(IUser::class);
        $user->method('getUID')->willReturn('alice');
        $user->method('getDisplayName')->willReturn('Alice');
        $this->userManager->method('searchDisplayName')->with('ali', 20)->willReturn([$user]);

        $data = $this->controller->searchUsers()->getData();

        $this->assertCount(1, $data['results']);
        $this->assertSame('alice', $data['results'][0]['id']);
        $this->assertSame('Alice', $data['results'][0]['displayName']);
    }

    // =========================================================================
    // searchGroups()
    // =========================================================================

    public function testSearchGroupsEmptyTermReturnsEmpty(): void
    {
        $this->mockAdmin();
        $this->request->method('getParam')->with('term', '')->willReturn('');

        $data = $this->controller->searchGroups()->getData();

        $this->assertTrue($data['success']);
        $this->assertEmpty($data['results']);
    }

    public function testSearchGroupsReturnsResults(): void
    {
        $this->mockAdmin();
        $this->request->method('getParam')->with('term', '')->willReturn('ed');

        $group = $this->createMock(IGroup::class);
        $group->method('getGID')->willReturn('editors');
        $group->method('getDisplayName')->willReturn('Editors');
        $this->groupManager->method('search')->with('ed', 20)->willReturn([$group]);

        $data = $this->controller->searchGroups()->getData();

        $this->assertCount(1, $data['results']);
        $this->assertSame('editors', $data['results'][0]['id']);
    }
}
