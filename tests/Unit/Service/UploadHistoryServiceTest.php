<?php

declare(strict_types=1);

namespace OCA\AppDrop\Tests\Unit\Service;

use OCA\AppDrop\Db\UploadHistory;
use OCA\AppDrop\Db\UploadHistoryMapper;
use OCA\AppDrop\Service\UploadHistoryService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class UploadHistoryServiceTest extends TestCase
{
    private UploadHistoryMapper&MockObject $mapper;
    private UploadHistoryService $service;

    protected function setUp(): void
    {
        $this->mapper = $this->createMock(UploadHistoryMapper::class);
        $this->service = new UploadHistoryService($this->mapper);
    }

    public function testRecordCreatesAndInsertsEntity(): void
    {
        $this->mapper->expects($this->once())
            ->method('insert')
            ->with($this->callback(function (UploadHistory $entity): bool {
                return $entity->getAppId() === 'my_app'
                    && $entity->getVersion() === '1.0.0'
                    && $entity->getFilename() === 'my_app.zip'
                    && $entity->getResult() === 'success'
                    && $entity->getMessage() === 'Installed OK'
                    && $entity->getUserId() === 'admin'
                    && $entity->getCreatedAt() > 0;
            }))
            ->willReturnCallback(fn(UploadHistory $e) => $e);

        $result = $this->service->record('my_app', '1.0.0', 'my_app.zip', 'success', 'Installed OK', 'admin');

        $this->assertInstanceOf(UploadHistory::class, $result);
    }

    public function testGetRecentReturnsFormattedEntries(): void
    {
        $entity = new UploadHistory();
        $entity->setAppId('calc');
        $entity->setVersion('2.0.0');
        $entity->setFilename('calc.zip');
        $entity->setResult('success');
        $entity->setMessage('OK');
        $entity->setUserId('admin');
        $entity->setCreatedAt(1709568000);

        $this->mapper->method('findRecent')
            ->with(20, 0)
            ->willReturn([$entity]);
        $this->mapper->method('countAll')
            ->willReturn(1);

        $result = $this->service->getRecent(1, 20);

        $this->assertSame(1, $result['total']);
        $this->assertCount(1, $result['entries']);
        $this->assertSame('calc', $result['entries'][0]['appId']);
        $this->assertSame('2.0.0', $result['entries'][0]['version']);
    }

    public function testGetRecentCalculatesOffsetCorrectly(): void
    {
        $this->mapper->expects($this->once())
            ->method('findRecent')
            ->with(10, 20);
        $this->mapper->method('countAll')->willReturn(0);

        $this->service->getRecent(3, 10);
    }

    public function testGetRecentReturnsEmptyForNoEntries(): void
    {
        $this->mapper->method('findRecent')->willReturn([]);
        $this->mapper->method('countAll')->willReturn(0);

        $result = $this->service->getRecent();

        $this->assertSame(0, $result['total']);
        $this->assertEmpty($result['entries']);
    }
}
