<?php

declare(strict_types=1);

namespace OCA\AppDrop\Tests\Unit\Db;

use OCA\AppDrop\Db\UploadHistory;
use PHPUnit\Framework\TestCase;

class UploadHistoryTest extends TestCase {
	public function testGettersAndSetters(): void {
		$entity = new UploadHistory();

		$entity->setAppId('my_app');
		$entity->setVersion('1.2.3');
		$entity->setFilename('my_app.zip');
		$entity->setResult('success');
		$entity->setMessage('Installed successfully');
		$entity->setUserId('admin');
		$entity->setCreatedAt(1709568000);

		$this->assertSame('my_app', $entity->getAppId());
		$this->assertSame('1.2.3', $entity->getVersion());
		$this->assertSame('my_app.zip', $entity->getFilename());
		$this->assertSame('success', $entity->getResult());
		$this->assertSame('Installed successfully', $entity->getMessage());
		$this->assertSame('admin', $entity->getUserId());
		$this->assertSame(1709568000, $entity->getCreatedAt());
	}

	public function testDefaultValues(): void {
		$entity = new UploadHistory();

		$this->assertSame('', $entity->getAppId());
		$this->assertSame('', $entity->getVersion());
		$this->assertSame('', $entity->getFilename());
		$this->assertSame('', $entity->getResult());
		$this->assertSame('', $entity->getMessage());
		$this->assertSame('', $entity->getUserId());
		$this->assertSame(0, $entity->getCreatedAt());
	}
}
