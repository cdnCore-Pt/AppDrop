<?php

declare(strict_types=1);

namespace OCA\AppDrop\Tests\Unit\Controller;

use OCA\AppDrop\Controller\TemplateGeneratorController;
use OCA\AppDrop\Service\PermissionService;
use OCA\AppDrop\Service\TemplateGeneratorService;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TemplateGeneratorControllerTest extends TestCase
{
    private IRequest&MockObject $request;
    private IUserSession&MockObject $userSession;
    private IGroupManager&MockObject $groupManager;
    private TemplateGeneratorService&MockObject $generatorService;
    private PermissionService&MockObject $permissionService;
    private TemplateGeneratorController $controller;

    protected function setUp(): void
    {
        $this->request = $this->createMock(IRequest::class);
        $this->userSession = $this->createMock(IUserSession::class);
        $this->groupManager = $this->createMock(IGroupManager::class);
        $this->generatorService = $this->createMock(TemplateGeneratorService::class);
        $this->permissionService = $this->createMock(PermissionService::class);

        $this->controller = new TemplateGeneratorController(
            'appdrop',
            $this->request,
            $this->userSession,
            $this->groupManager,
            $this->generatorService,
            $this->permissionService,
        );
    }

    private function mockUnauthorized(): void
    {
        $user = $this->createMock(IUser::class);
        $user->method('getUID')->willReturn('nobody');
        $this->userSession->method('getUser')->willReturn($user);
        $this->groupManager->method('isInGroup')->willReturn(false);
        $this->permissionService->method('canUpload')->willReturn(false);
    }

    public function testGenerateDeniesUnauthorizedUser(): void
    {
        $this->mockUnauthorized();

        $response = $this->controller->generate();

        $this->assertSame(403, $response->getStatus());
    }
}
