<?php

declare(strict_types=1);

namespace OCA\AppDrop\Tests\Unit\Service;

use OCA\AppDrop\Service\AppUpgradeService;

/** AppUpgradeService with the `occ app:enable` subprocess replaced by a recorder. */
class RecordingUpgradeService extends AppUpgradeService {
	/** @var list<string> */
	public array $occCalls = [];
	public bool $occSucceeds = true;
	/** @var \Closure(string): string */
	public \Closure $installedVersionReader;

	protected function queryInstalledVersion(string $appId): string {
		return ($this->installedVersionReader)($appId);
	}

	protected function runOccEnable(string $appId): bool {
		$this->occCalls[] = $appId;
		return $this->occSucceeds;
	}
}
