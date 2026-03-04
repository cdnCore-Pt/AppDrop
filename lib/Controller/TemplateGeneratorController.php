<?php

/**
 * SPDX-FileCopyrightText: 2026 CDNTV
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\AppDrop\Controller;

use OCA\AppDrop\Service\AppInstallException;
use OCA\AppDrop\Service\PermissionService;
use OCA\AppDrop\Service\TemplateGeneratorService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;

class TemplateGeneratorController extends Controller
{
    use AdminAuthTrait;

    private const APPID_PATTERN = '/^[a-z0-9_]{3,64}$/';

    public function __construct(
        string $appName,
        IRequest $request,
        private readonly IUserSession $userSession,
        private readonly IGroupManager $groupManager,
        private readonly TemplateGeneratorService $generatorService,
        private readonly PermissionService $permissionService,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * Generate a Nextcloud app skeleton and return as zip download.
     *
     * @NoAdminRequired
     */
    public function generate(): DataDownloadResponse|JSONResponse
    {
        $denied = $this->denyIfCannotUpload();
        if ($denied !== null) {
            return $denied;
        }

        $params = json_decode(file_get_contents('php://input'), true) ?? [];

        $appName = trim($params['appName'] ?? '');
        $appId = trim($params['appId'] ?? '');
        $namespace = trim($params['namespace'] ?? '');
        $version = trim($params['version'] ?? '1.0.0');
        $author = trim($params['author'] ?? '');
        $description = trim($params['description'] ?? '');

        if ($appName === '') {
            return new JSONResponse(['success' => false, 'message' => 'App name is required.'], 400);
        }

        if ($appId === '' || preg_match(self::APPID_PATTERN, $appId) !== 1) {
            return new JSONResponse([
                'success' => false,
                'message' => 'Invalid app ID. Only lowercase letters, digits and underscores (3-64 chars).',
            ], 400);
        }

        if ($namespace === '') {
            $namespace = str_replace(' ', '', ucwords(str_replace('_', ' ', $appId)));
        }

        try {
            $zipPath = $this->generatorService->generate($appId, $appName, $namespace, $version, $author, $description);
            $content = file_get_contents($zipPath);
            @unlink($zipPath);

            if ($content === false) {
                return new JSONResponse(['success' => false, 'message' => 'Failed to read generated zip.'], 500);
            }

            return new DataDownloadResponse($content, $appId . '.zip', 'application/zip');
        } catch (AppInstallException $e) {
            return new JSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
