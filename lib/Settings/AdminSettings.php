<?php

/**
 * SPDX-FileCopyrightText: 2026 CDNTV
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\AppDrop\Settings;

use OCA\AppDrop\Service\PermissionService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\ISettings;

/**
 * Admin settings panel for AppDrop.
 *
 * Renders under the dedicated "AppDrop" section in
 * Settings → Administration. Exposes general settings (max upload size,
 * auto-enable default) and permission management (allowed users/groups).
 */
class AdminSettings implements ISettings {
	private const APP_ID = 'appdrop';

	public function __construct(
		private readonly IL10N $l10n,
		private readonly IConfig $config,
		private readonly IURLGenerator $urlGenerator,
		private readonly PermissionService $permissionService,
	) {
	}

	public function getForm(): TemplateResponse {
		$maxSizeMB = (int)$this->config->getAppValue(self::APP_ID, 'max_upload_size_mb', '20');
		$autoEnable = $this->config->getAppValue(self::APP_ID, 'auto_enable_default', '1') === '1';

		return new TemplateResponse(
			self::APP_ID,
			'settings/admin',
			[
				'maxSizeMB' => $maxSizeMB,
				'autoEnable' => $autoEnable,
				'allowedUsers' => $this->permissionService->getAllowedUsers(),
				'allowedGroups' => $this->permissionService->getAllowedGroups(),
				'appUrl' => $this->urlGenerator->linkToRoute('appdrop.admin.index'),
			],
			TemplateResponse::RENDER_AS_BLANK,
		);
	}

	/** Settings section — our dedicated section. */
	public function getSection(): string {
		return 'appdrop';
	}

	/** Priority within the section (lower = higher position). */
	public function getPriority(): int {
		return 10;
	}
}
