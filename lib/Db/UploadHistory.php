<?php

/**
 * SPDX-FileCopyrightText: 2026 CDNTV
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\AppDrop\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getAppId()
 * @method void setAppId(string $appId)
 * @method string getVersion()
 * @method void setVersion(string $version)
 * @method string getFilename()
 * @method void setFilename(string $filename)
 * @method string getResult()
 * @method void setResult(string $result)
 * @method string getMessage()
 * @method void setMessage(string $message)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 */
class UploadHistory extends Entity {
	protected string $appId = '';
	protected string $version = '';
	protected string $filename = '';
	protected string $result = '';
	protected string $message = '';
	protected string $userId = '';
	protected int $createdAt = 0;

	public function __construct() {
		$this->addType('appId', 'string');
		$this->addType('version', 'string');
		$this->addType('filename', 'string');
		$this->addType('result', 'string');
		$this->addType('message', 'string');
		$this->addType('userId', 'string');
		$this->addType('createdAt', 'integer');
	}
}
