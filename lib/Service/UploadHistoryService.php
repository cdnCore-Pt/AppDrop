<?php

/**
 * SPDX-FileCopyrightText: 2026 CDNTV
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\AppDrop\Service;

use OCA\AppDrop\Db\UploadHistory;
use OCA\AppDrop\Db\UploadHistoryMapper;

/**
 * Records and retrieves upload history entries.
 */
class UploadHistoryService
{
    public function __construct(
        private readonly UploadHistoryMapper $mapper,
    ) {
    }

    /**
     * Record an upload event.
     */
    public function record(
        string $appId,
        string $version,
        string $filename,
        string $result,
        string $message,
        string $userId,
    ): UploadHistory {
        $entry = new UploadHistory();
        $entry->setAppId($appId);
        $entry->setVersion($version);
        $entry->setFilename($filename);
        $entry->setResult($result);
        $entry->setMessage($message);
        $entry->setUserId($userId);
        $entry->setCreatedAt(time());

        return $this->mapper->insert($entry);
    }

    /**
     * Get recent entries with pagination.
     *
     * @return array{entries: array, total: int}
     */
    public function getRecent(int $page = 1, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;
        $entities = $this->mapper->findRecent($limit, $offset);
        $total = $this->mapper->countAll();

        $entries = array_map(function (UploadHistory $e) {
            return [
                'id' => $e->getId(),
                'appId' => $e->getAppId(),
                'version' => $e->getVersion(),
                'filename' => $e->getFilename(),
                'result' => $e->getResult(),
                'message' => $e->getMessage(),
                'userId' => $e->getUserId(),
                'createdAt' => $e->getCreatedAt(),
            ];
        }, $entities);

        return ['entries' => $entries, 'total' => $total];
    }
}
