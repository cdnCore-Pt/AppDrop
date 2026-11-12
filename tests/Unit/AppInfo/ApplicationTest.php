<?php

declare(strict_types=1);

namespace OCA\AppDrop\Tests\Unit\AppInfo;

use OCA\AppDrop\AppInfo\Application;
use OCA\AppDrop\Service\PermissionService;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\IAppContainer;
use OCP\INavigationManager;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

class ApplicationTest extends TestCase {
	private function boot(bool $canUpload, INavigationManager $nav, IURLGenerator $url): void {
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($this->createMock(IUser::class));
		$permissions = $this->createMock(PermissionService::class);
		$permissions->method('canUpload')->willReturn($canUpload);

		$container = $this->createMock(IAppContainer::class);
		$container->method('get')->willReturnMap([
			[IUserSession::class, $session],
			[PermissionService::class, $permissions],
			[IURLGenerator::class, $url],
			[INavigationManager::class, $nav],
		]);
		$context = $this->createMock(IBootContext::class);
		$context->method('getAppContainer')->willReturn($container);
		// IServerContainer is deprecated and logs on every request
		$context->expects($this->never())->method('getServerContainer');

		(new \ReflectionClass(Application::class))->newInstanceWithoutConstructor()->boot($context);
	}

	/**
	 * An array entry makes NavigationManager::add() resolve every app's nav
	 * routes during boot, before the other apps are loaded, which leaves
	 * their header links empty (NC 30-34). A closure is resolved later.
	 */
	public function testNavigationEntryIsDeferredAndGeneratesNoUrlDuringBoot(): void {
		$url = $this->createMock(IURLGenerator::class);
		$url->expects($this->never())->method('linkToRoute');
		$entry = null;
		$nav = $this->createMock(INavigationManager::class);
		$nav->expects($this->once())->method('add')->willReturnCallback(function ($e) use (&$entry): void {
			$entry = $e;
		});

		$this->boot(true, $nav, $url);

		$this->assertInstanceOf(\Closure::class, $entry);
	}

	public function testDeferredEntryResolvesToACompleteArray(): void {
		$url = $this->createMock(IURLGenerator::class);
		$url->method('linkToRoute')->with('appdrop.admin.index')->willReturn('/apps/appdrop/admin');
		$url->method('imagePath')->willReturn('/apps/appdrop/img/app.svg');
		$entry = null;
		$nav = $this->createMock(INavigationManager::class);
		$nav->method('add')->willReturnCallback(function ($e) use (&$entry): void {
			$entry = $e;
		});

		$this->boot(true, $nav, $url);

		$resolved = $entry();
		$this->assertSame('appdrop', $resolved['id']);
		$this->assertSame('/apps/appdrop/admin', $resolved['href']);
		$this->assertNotEmpty($resolved['name']);
		$this->assertNotEmpty($resolved['icon']);
	}

	public function testNoEntryWithoutUploadPermission(): void {
		$nav = $this->createMock(INavigationManager::class);
		$nav->expects($this->never())->method('add');

		$this->boot(false, $nav, $this->createMock(IURLGenerator::class));
	}
}
