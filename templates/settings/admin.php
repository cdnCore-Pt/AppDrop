<?php

/**
 * SPDX-FileCopyrightText: 2026 CDNTV
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

/**
 * Admin settings panel template — rendered by AdminSettings::getForm().
 *
 * This template is embedded inside Nextcloud's Settings → Administration page
 * under the "AppDrop" section. It provides:
 * - General settings (max upload size, auto-enable default)
 * - Permission management (allowed users and groups)
 * - Link to the main app page
 */

/** @var \OCP\IL10N $l */
/** @var array $_ */
$maxSizeMB = $_['maxSizeMB'] ?? 20;
$autoEnable = $_['autoEnable'] ?? true;
$allowedUsers = $_['allowedUsers'] ?? [];
$allowedGroups = $_['allowedGroups'] ?? [];
$appUrl = $_['appUrl'] ?? '#';

\OCP\Util::addStyle('appdrop', 'admin');
\OCP\Util::addScript('appdrop', 'admin-settings');
?>

<!-- General Settings -->
<div class="section">
    <h2><?php p($l->t('General')); ?></h2>
    <p class="aum-settings-desc">
        <?php p($l->t('Configure upload limits and default behavior for the AppDrop.')); ?>
    </p>

    <div class="aum-settings-form">
        <div class="aum-settings-row">
            <label for="aum-settings-max-size" class="aum-settings-label">
                <?php p($l->t('Maximum upload size')); ?>
            </label>
            <div class="aum-settings-control">
                <input id="aum-settings-max-size"
                       type="number"
                       class="aum-input aum-input--narrow"
                       min="1"
                       max="512"
                       value="<?php p($maxSizeMB); ?>">
                <span class="aum-settings-unit">MB</span>
                <span class="aum-hint"><?php p($l->t('Between 1 and 512 MB. Also limited by PHP upload_max_filesize.')); ?></span>
            </div>
        </div>

        <div class="aum-settings-row">
            <label for="aum-settings-auto-enable" class="aum-settings-label">
                <?php p($l->t('Auto-enable apps')); ?>
            </label>
            <div class="aum-settings-control">
                <label class="aum-checkbox-label">
                    <input id="aum-settings-auto-enable"
                           type="checkbox"
                           <?php if ($autoEnable): ?>checked<?php endif; ?>>
                    <?php p($l->t('Automatically enable apps after upload (users can override per upload)')); ?>
                </label>
            </div>
        </div>

        <div class="aum-settings-row">
            <span class="aum-settings-label"></span>
            <div class="aum-settings-control">
                <button id="aum-settings-save" type="button" class="aum-btn aum-btn--primary aum-btn--small">
                    <?php p($l->t('Save')); ?>
                </button>
                <span id="aum-settings-saved" class="aum-settings-saved aum-settings-saved--hidden">
                    <?php p($l->t('Saved')); ?>
                </span>
            </div>
        </div>
    </div>
</div>

<!-- Permissions -->
<div class="section">
    <h2><?php p($l->t('Permissions')); ?></h2>
    <p class="aum-settings-desc">
        <?php p($l->t('Administrators always have full access. Add groups or individual users below to grant them upload permission.')); ?>
    </p>

    <div id="aum-settings-perm-message" class="aum-alert aum-alert--hidden" role="status"></div>

    <!-- Groups -->
    <div class="aum-perm-block">
        <h3 class="aum-perm-heading"><?php p($l->t('Allowed Groups')); ?></h3>
        <div class="aum-perm-search">
            <input id="aum-settings-group-search"
                   type="text"
                   class="aum-input"
                   placeholder="<?php p($l->t('Search groups...')); ?>"
                   autocomplete="off">
            <div id="aum-settings-group-results" class="aum-autocomplete"></div>
        </div>
        <ul id="aum-settings-group-list" class="aum-perm-list">
            <?php if (empty($allowedGroups)): ?>
                <li class="aum-perm-empty"><?php p($l->t('No groups added yet.')); ?></li>
            <?php else: ?>
                <?php foreach ($allowedGroups as $groupId): ?>
                    <li class="aum-perm-item">
                        <span class="aum-perm-item__name"><?php p($groupId); ?></span>
                        <button type="button"
                                class="aum-btn aum-btn--danger aum-btn--small aum-settings-perm-remove"
                                data-type="group"
                                data-id="<?php p($groupId); ?>">
                            <?php p($l->t('Remove')); ?>
                        </button>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </div>

    <!-- Users -->
    <div class="aum-perm-block">
        <h3 class="aum-perm-heading"><?php p($l->t('Allowed Users')); ?></h3>
        <div class="aum-perm-search">
            <input id="aum-settings-user-search"
                   type="text"
                   class="aum-input"
                   placeholder="<?php p($l->t('Search users...')); ?>"
                   autocomplete="off">
            <div id="aum-settings-user-results" class="aum-autocomplete"></div>
        </div>
        <ul id="aum-settings-user-list" class="aum-perm-list">
            <?php if (empty($allowedUsers)): ?>
                <li class="aum-perm-empty"><?php p($l->t('No users added yet.')); ?></li>
            <?php else: ?>
                <?php foreach ($allowedUsers as $userId): ?>
                    <li class="aum-perm-item">
                        <span class="aum-perm-item__name"><?php p($userId); ?></span>
                        <button type="button"
                                class="aum-btn aum-btn--danger aum-btn--small aum-settings-perm-remove"
                                data-type="user"
                                data-id="<?php p($userId); ?>">
                            <?php p($l->t('Remove')); ?>
                        </button>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </div>
</div>

<!-- Quick link -->
<div class="section">
    <h2><?php p($l->t('Open App')); ?></h2>
    <p class="aum-settings-desc">
        <?php p($l->t('Use the full app interface for uploading, managing apps, viewing history, and generating app skeletons.')); ?>
    </p>
    <a class="button aum-settings-link"
       href="<?php print_unescaped($appUrl); ?>">
        <?php p($l->t('Open AppDrop')); ?>
    </a>
</div>
