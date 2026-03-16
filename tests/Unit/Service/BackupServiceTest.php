<?php

declare(strict_types=1);

namespace OCA\AppDrop\Tests\Unit\Service;

use OCA\AppDrop\Service\AppInstallException;
use OCA\AppDrop\Service\AppPathResolver;
use OCA\AppDrop\Service\BackupService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class BackupServiceTest extends TestCase
{
    private AppPathResolver&MockObject $pathResolver;
    private LoggerInterface&MockObject $logger;
    private BackupService $service;
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->pathResolver = $this->createMock(AppPathResolver::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->service = new BackupService($this->pathResolver, $this->logger);

        $this->tmpDir = sys_get_temp_dir() . '/appdrop_backup_test_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
        $this->pathResolver->method('resolveWritablePath')->willReturn($this->tmpDir);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    private function removeDir(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getRealPath()) : unlink($item->getRealPath());
        }
        rmdir($path);
    }

    // =========================================================================
    // listBackups()
    // =========================================================================

    public function testListBackupsReturnsEmptyForNoBackups(): void
    {
        $this->assertSame([], $this->service->listBackups());
    }

    public function testListBackupsFindsBackupDirectories(): void
    {
        mkdir($this->tmpDir . '/myapp_backup_20260315_120000');
        mkdir($this->tmpDir . '/other_app_backup_20260314_100000');
        mkdir($this->tmpDir . '/not_a_backup');

        $result = $this->service->listBackups();

        $this->assertCount(2, $result);
        $this->assertSame('myapp', $result[0]['appId']);
        $this->assertSame('2026-03-15 12:00:00', $result[0]['date']);
        $this->assertSame('other_app', $result[1]['appId']);
    }

    public function testListBackupsSkipsFiles(): void
    {
        file_put_contents($this->tmpDir . '/myapp_backup_20260315_120000', 'file, not dir');

        $this->assertSame([], $this->service->listBackups());
    }

    public function testListBackupsSortsByDateDescending(): void
    {
        mkdir($this->tmpDir . '/myapp_backup_20260101_000000');
        mkdir($this->tmpDir . '/myapp_backup_20260315_120000');
        mkdir($this->tmpDir . '/myapp_backup_20260201_060000');

        $result = $this->service->listBackups();

        $this->assertSame('2026-03-15 12:00:00', $result[0]['date']);
        $this->assertSame('2026-02-01 06:00:00', $result[1]['date']);
        $this->assertSame('2026-01-01 00:00:00', $result[2]['date']);
    }

    // =========================================================================
    // delete()
    // =========================================================================

    public function testDeleteRemovesBackupDirectory(): void
    {
        $backupDir = $this->tmpDir . '/myapp_backup_20260315_120000';
        mkdir($backupDir);
        file_put_contents($backupDir . '/file.txt', 'test');

        $this->service->delete('myapp_backup_20260315_120000');

        $this->assertDirectoryDoesNotExist($backupDir);
    }

    public function testDeleteThrowsForInvalidDirName(): void
    {
        $this->expectException(AppInstallException::class);
        $this->expectExceptionMessage('Invalid backup directory name');

        $this->service->delete('../etc/passwd');
    }

    public function testDeleteThrowsForNonExistentBackup(): void
    {
        $this->expectException(AppInstallException::class);
        $this->expectExceptionMessage('not found');

        $this->service->delete('myapp_backup_20260315_120000');
    }

    // =========================================================================
    // restore()
    // =========================================================================

    public function testRestoreMovesBackupToAppDir(): void
    {
        $backupDir = $this->tmpDir . '/myapp_backup_20260315_120000';
        mkdir($backupDir);
        file_put_contents($backupDir . '/appinfo/info.xml', '<info/>', 0, null);
        @mkdir($backupDir . '/appinfo', 0755, true);
        file_put_contents($backupDir . '/appinfo/info.xml', '<info/>');

        $this->service->restore('myapp_backup_20260315_120000');

        $this->assertDirectoryExists($this->tmpDir . '/myapp');
        $this->assertDirectoryDoesNotExist($backupDir);
    }

    public function testRestoreBacksUpCurrentAppFirst(): void
    {
        // Create current app
        mkdir($this->tmpDir . '/myapp');
        file_put_contents($this->tmpDir . '/myapp/old.txt', 'old');

        // Create backup to restore
        mkdir($this->tmpDir . '/myapp_backup_20260315_120000');
        file_put_contents($this->tmpDir . '/myapp_backup_20260315_120000/new.txt', 'new');

        $this->service->restore('myapp_backup_20260315_120000');

        // New app should be in place
        $this->assertFileExists($this->tmpDir . '/myapp/new.txt');
        // Old app should have been backed up
        $backups = glob($this->tmpDir . '/myapp_backup_*');
        $this->assertGreaterThanOrEqual(1, count($backups));
    }

    public function testRestoreThrowsForInvalidDirName(): void
    {
        $this->expectException(AppInstallException::class);
        $this->expectExceptionMessage('Invalid backup directory name');

        $this->service->restore('../../etc');
    }

    public function testRestoreThrowsForNonExistentBackup(): void
    {
        $this->expectException(AppInstallException::class);
        $this->expectExceptionMessage('not found');

        $this->service->restore('myapp_backup_20260315_120000');
    }
}
