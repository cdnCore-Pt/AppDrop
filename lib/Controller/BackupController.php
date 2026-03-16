<?php

/**
 * SPDX-FileCopyrightText: 2026 CDNTV
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\AppDrop\Controller;

use OCA\AppDrop\Service\AppInstallException;
use OCA\AppDrop\Service\BackupService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;

class BackupController extends Controller {
	use AdminAuthTrait;

	public function __construct(
		string $appName,
		IRequest $request,
		private readonly IUserSession $userSession,
		private readonly IGroupManager $groupManager,
		private readonly BackupService $backupService,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * List all backups.
	 */
	public function list(): JSONResponse {
		$denied = $this->denyIfNotAdmin();
		if ($denied !== null) {
			return $denied;
		}

		try {
			$backups = $this->backupService->listBackups();
			return new JSONResponse(['success' => true, 'backups' => $backups]);
		} catch (\Throwable $e) {
			return new JSONResponse(['success' => false, 'message' => $e->getMessage()]);
		}
	}

	/**
	 * Restore a backup.
	 */
	public function restore(): JSONResponse {
		$denied = $this->denyIfNotAdmin();
		if ($denied !== null) {
			return $denied;
		}

		$dirName = $this->getDirNameFromRequest();
		if ($dirName === null) {
			return new JSONResponse(['success' => false, 'message' => 'Missing dirName parameter.']);
		}

		try {
			$this->backupService->restore($dirName);
			return new JSONResponse(['success' => true, 'message' => "Backup '{$dirName}' restored."]);
		} catch (AppInstallException $e) {
			return new JSONResponse(['success' => false, 'message' => $e->getMessage()]);
		}
	}

	/**
	 * Delete a backup.
	 */
	public function delete(): JSONResponse {
		$denied = $this->denyIfNotAdmin();
		if ($denied !== null) {
			return $denied;
		}

		$dirName = $this->getDirNameFromRequest();
		if ($dirName === null) {
			return new JSONResponse(['success' => false, 'message' => 'Missing dirName parameter.']);
		}

		try {
			$this->backupService->delete($dirName);
			return new JSONResponse(['success' => true, 'message' => "Backup '{$dirName}' deleted."]);
		} catch (AppInstallException $e) {
			return new JSONResponse(['success' => false, 'message' => $e->getMessage()]);
		}
	}

	private function getDirNameFromRequest(): ?string {
		$params = json_decode(file_get_contents('php://input'), true) ?? [];
		$dirName = trim($params['dirName'] ?? '');
		return $dirName !== '' ? $dirName : null;
	}
}
