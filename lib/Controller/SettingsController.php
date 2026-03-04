<?php

/**
 * SPDX-FileCopyrightText: 2026 CDNTV
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\AppDrop\Controller;

use OCA\AppDrop\Service\PermissionService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Handles saving/loading general settings from the admin settings page.
 */
class SettingsController extends Controller
{
    use AdminAuthTrait;

    private const APP_ID = 'appdrop';

    public function __construct(
        string $appName,
        IRequest $request,
        private readonly IUserSession $userSession,
        private readonly IGroupManager $groupManager,
        private readonly PermissionService $permissionService,
        private readonly IConfig $config,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * Get current settings.
     *
     * @NoCSRFRequired
     */
    public function get(): JSONResponse
    {
        $denied = $this->denyIfNotAdmin();
        if ($denied !== null) {
            return $denied;
        }

        return new JSONResponse([
            'success' => true,
            'maxSizeMB' => (int) $this->config->getAppValue(self::APP_ID, 'max_upload_size_mb', '20'),
            'autoEnable' => $this->config->getAppValue(self::APP_ID, 'auto_enable_default', '1') === '1',
        ]);
    }

    /**
     * Save general settings (max upload size, auto-enable default).
     */
    public function save(): JSONResponse
    {
        $denied = $this->denyIfNotAdmin();
        if ($denied !== null) {
            return $denied;
        }

        $params = json_decode(file_get_contents('php://input'), true) ?? [];

        if (isset($params['maxSizeMB'])) {
            $maxSize = (int) $params['maxSizeMB'];
            if ($maxSize < 1 || $maxSize > 512) {
                return new JSONResponse([
                    'success' => false,
                    'message' => 'Max upload size must be between 1 and 512 MB.',
                ], 400);
            }
            $this->config->setAppValue(self::APP_ID, 'max_upload_size_mb', (string) $maxSize);
        }

        if (isset($params['autoEnable'])) {
            $this->config->setAppValue(
                self::APP_ID,
                'auto_enable_default',
                $params['autoEnable'] ? '1' : '0',
            );
        }

        return new JSONResponse([
            'success' => true,
            'message' => 'Settings saved.',
        ]);
    }
}
