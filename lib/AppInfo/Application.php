<?php

/**
 * SPDX-FileCopyrightText: 2026 CDNTV
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\AppDrop\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\INavigationManager;
use OCP\IURLGenerator;

/**
 * Application bootstrap for appdrop.
 *
 * Registers services in the DI container and hooks into the admin settings panel.
 * Follows the Nextcloud 30+ IBootstrap pattern (register → boot).
 *
 * Navigation is always registered; access control is enforced at the
 * controller level via PermissionService::canUpload().
 * The NavigationManager closure must always return a valid entry array
 * (returning null crashes NC 32's NavigationManager).
 */
class Application extends App implements IBootstrap
{
    public const APP_ID = 'appdrop';

    public function __construct(array $urlParams = [])
    {
        parent::__construct(self::APP_ID, $urlParams);
    }

    public function register(IRegistrationContext $context): void
    {
    }

    public function boot(IBootContext $context): void
    {
        $server = $context->getServerContainer();

        $server->get(INavigationManager::class)->add(function () use ($server) {
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
