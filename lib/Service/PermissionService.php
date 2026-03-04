<?php

/**
 * SPDX-FileCopyrightText: 2026 CDNTV
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\AppDrop\Service;

use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUser;

class PermissionService
{
    private const APP_ID = 'appdrop';
    private const KEY_ALLOWED_USERS = 'allowed_users';
    private const KEY_ALLOWED_GROUPS = 'allowed_groups';

    public function __construct(
        private readonly IConfig $config,
        private readonly IGroupManager $groupManager,
    ) {
    }

    public function canUpload(?IUser $user): bool
    {
        if ($user === null) {
            return false;
        }

        $uid = $user->getUID();

        // Admin always has access
        if ($this->groupManager->isInGroup($uid, 'admin')) {
            return true;
        }

        // Check allowed users
        if (in_array($uid, $this->getAllowedUsers(), true)) {
            return true;
        }

        // Check allowed groups
        foreach ($this->getAllowedGroups() as $groupId) {
            if ($this->groupManager->isInGroup($uid, $groupId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string[]
     */
    public function getAllowedUsers(): array
    {
        $json = $this->config->getAppValue(self::APP_ID, self::KEY_ALLOWED_USERS, '[]');
        $users = json_decode($json, true);
        return is_array($users) ? $users : [];
    }

    public function addUser(string $userId): void
    {
        $users = $this->getAllowedUsers();
        if (!in_array($userId, $users, true)) {
            $users[] = $userId;
            $this->config->setAppValue(self::APP_ID, self::KEY_ALLOWED_USERS, json_encode(array_values($users)));
        }
    }

    public function removeUser(string $userId): void
    {
        $users = $this->getAllowedUsers();
        $users = array_filter($users, fn(string $u) => $u !== $userId);
        $this->config->setAppValue(self::APP_ID, self::KEY_ALLOWED_USERS, json_encode(array_values($users)));
    }

    /**
     * @return string[]
     */
    public function getAllowedGroups(): array
    {
        $json = $this->config->getAppValue(self::APP_ID, self::KEY_ALLOWED_GROUPS, '[]');
        $groups = json_decode($json, true);
        return is_array($groups) ? $groups : [];
    }

    public function addGroup(string $groupId): void
    {
        $groups = $this->getAllowedGroups();
        if (!in_array($groupId, $groups, true)) {
            $groups[] = $groupId;
            $this->config->setAppValue(self::APP_ID, self::KEY_ALLOWED_GROUPS, json_encode(array_values($groups)));
        }
    }

    public function removeGroup(string $groupId): void
    {
        $groups = $this->getAllowedGroups();
        $groups = array_filter($groups, fn(string $g) => $g !== $groupId);
        $this->config->setAppValue(self::APP_ID, self::KEY_ALLOWED_GROUPS, json_encode(array_values($groups)));
    }
}
