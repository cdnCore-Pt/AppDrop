<?php

/**
 * SPDX-FileCopyrightText: 2026 CDNTV
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\AppDrop\Controller;

use OCA\AppDrop\Service\PermissionService;
use OCA\AppDrop\Service\UploadHistoryService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;

class HistoryController extends Controller
{
    use AdminAuthTrait;

    public function __construct(
        string $appName,
        IRequest $request,
        private readonly IUserSession $userSession,
        private readonly IGroupManager $groupManager,
        private readonly UploadHistoryService $historyService,
        private readonly PermissionService $permissionService,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * List upload history (paginated).
     *
     * @NoCSRFRequired
     */
    public function list(): JSONResponse
    {
        $denied = $this->denyIfCannotUpload();
        if ($denied !== null) {
            return $denied;
        }

        $page = max(1, (int) $this->request->getParam('page', '1'));
        $limit = min(100, max(1, (int) $this->request->getParam('limit', '20')));

        try {
            $result = $this->historyService->getRecent($page, $limit);
            return new JSONResponse([
                'success' => true,
                'entries' => $result['entries'],
                'total' => $result['total'],
            ]);
        } catch (\Throwable $e) {
            return new JSONResponse(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
