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
 * Registers services in the DI container and hooks into the admin settings panel.
 * Follows the Nextcloud 30+ IBootstrap pattern (register → boot).
 */
class Application extends App implements IBootstrap
{
    public const APP_ID = 'appdrop';

    public function __construct(array $urlParams = [])
    {
        parent::__construct(self::APP_ID, $urlParams);
    }

    /**
     * Register services, settings sections and admin settings.
     * Called before boot() and before the DI container is fully built.
     */
    public function register(IRegistrationContext $context): void
    {
        // Admin settings panel is registered via info.xml <settings><admin>.
        // Navigation is registered dynamically in boot() based on permissions.
    }

    /**
     * Boot phase: register navigation entry only for users with permission.
     */
    public function boot(IBootContext $context): void
    {
        $server = $context->getServerContainer();

        $server->get(INavigationManager::class)->add(function () use ($server) {
            $userSession = $server->get(IUserSession::class);
            $user = $userSession->getUser();

            // Only show navigation entry if user has permission
            if ($user === null) {
                return null;
            }

            try {
                $permissionService = $server->get(PermissionService::class);
                if (!$permissionService->canUpload($user)) {
                    return null;
                }
            } catch (\Throwable $e) {
                return null;
            }

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
