<?php

/**
 * SPDX-FileCopyrightText: 2026 CDNTV
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\AppDrop\AppInfo;

use OCA\AppDrop\Service\PermissionService;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\INavigationManager;
use OCP\IURLGenerator;
use OCP\IUserSession;

/**
 * Application bootstrap for appdrop.
 *
 * Navigation is registered only when the current user has upload permission.
 * The permission check runs at boot() time and registration is skipped when
 * denied: NC 32's NavigationManager crashes on a closure that returns null or
 * an empty array. The entry itself IS a closure (always a complete array):
 * an array entry makes NavigationManager::add() resolve every app's nav
 * routes right here, before the other apps are loaded, which left their
 * header links (Files, ...) empty on NC 30-34.
 */
class Application extends App implements IBootstrap {
	public const APP_ID = 'appdrop';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
	}

	public function boot(IBootContext $context): void {
		$server = $context->getAppContainer();

		try {
			$user = $server->get(IUserSession::class)->getUser();
			if ($user === null) {
				return;
			}

			$permissionService = $server->get(PermissionService::class);
			if (!$permissionService->canUpload($user)) {
				return;
			}
		} catch (\Throwable) {
			return;
		}

		$server->get(INavigationManager::class)->add(static function () use ($server): array {
			$urlGenerator = $server->get(IURLGenerator::class);
			return [
				'id' => self::APP_ID,
				'name' => 'AppDrop',
				'href' => $urlGenerator->linkToRoute('appdrop.admin.index'),
				'icon' => $urlGenerator->imagePath(self::APP_ID, 'app.svg'),
				'order' => 90,
			];
		});
	}
}
