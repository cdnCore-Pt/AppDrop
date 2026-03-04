<?php

/**
 * SPDX-FileCopyrightText: 2026 CDNTV
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\AppDrop\Controller;

use OCA\AppDrop\Service\AppInstallException;
use OCA\AppDrop\Service\AppManagerService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;

class AppManagerController extends Controller
{
    use AdminAuthTrait;

    public function __construct(
        string $appName,
        IRequest $request,
        private readonly IUserSession $userSession,
        private readonly IGroupManager $groupManager,
        private readonly AppManagerService $appManagerService,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * List custom apps.
     */
    public function list(): JSONResponse
    {
        $denied = $this->denyIfNotAdmin();
        if ($denied !== null) {
            return $denied;
        }

        try {
            $apps = $this->appManagerService->listApps();
            return new JSONResponse(['success' => true, 'apps' => $apps]);
        } catch (\Throwable $e) {
            return new JSONResponse(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Enable a custom app.
     */
    public function enable(): JSONResponse
    {
        $denied = $this->denyIfNotAdmin();
        if ($denied !== null) {
            return $denied;
        }

        $appId = $this->getAppIdFromRequest();
        if ($appId === null) {
            return new JSONResponse(['success' => false, 'message' => 'Missing appId parameter.']);
        }

        try {
            $this->appManagerService->enableApp($appId);
            return new JSONResponse(['success' => true, 'message' => "App '{$appId}' enabled."]);
        } catch (AppInstallException $e) {
            return new JSONResponse(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Disable a custom app.
     */
    public function disable(): JSONResponse
    {
        $denied = $this->denyIfNotAdmin();
        if ($denied !== null) {
            return $denied;
        }

        $appId = $this->getAppIdFromRequest();
        if ($appId === null) {
            return new JSONResponse(['success' => false, 'message' => 'Missing appId parameter.']);
        }

        try {
            $this->appManagerService->disableApp($appId);
            return new JSONResponse(['success' => true, 'message' => "App '{$appId}' disabled."]);
        } catch (AppInstallException $e) {
            return new JSONResponse(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Remove a custom app.
     */
    public function remove(): JSONResponse
    {
        $denied = $this->denyIfNotAdmin();
        if ($denied !== null) {
            return $denied;
        }

        $appId = $this->getAppIdFromRequest();
        if ($appId === null) {
            return new JSONResponse(['success' => false, 'message' => 'Missing appId parameter.']);
        }

        try {
            $this->appManagerService->removeApp($appId);
            return new JSONResponse(['success' => true, 'message' => "App '{$appId}' removed."]);
        } catch (AppInstallException $e) {
            return new JSONResponse(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function getAppIdFromRequest(): ?string
    {
        $params = json_decode(file_get_contents('php://input'), true) ?? [];
        $appId = trim($params['appId'] ?? '');
        return $appId !== '' ? $appId : null;
    }
}
