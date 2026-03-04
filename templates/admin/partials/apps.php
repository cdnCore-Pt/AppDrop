<?php

/**
 * SPDX-FileCopyrightText: 2026 CDNTV
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/** @var \OCP\IL10N $l */
?>

<h2 class="aum-section-title"><?php p($l->t('Apps')); ?></h2>
<p class="aum-subtitle">
    <?php p($l->t('Manage custom apps installed via AppDrop.')); ?>
</p>

<div class="aum-toolbar">
    <button id="aum-apps-refresh" class="aum-btn aum-btn--secondary" type="button">
        <?php p($l->t('Refresh')); ?>
    </button>
</div>

<div id="aum-apps-message" class="aum-alert aum-alert--hidden" role="status" aria-live="polite"></div>

<div id="aum-apps-list" class="aum-table-wrap">
    <table class="aum-table">
        <thead>
            <tr>
                <th><?php p($l->t('App ID')); ?></th>
                <th><?php p($l->t('Name')); ?></th>
                <th><?php p($l->t('Version')); ?></th>
                <th><?php p($l->t('Status')); ?></th>
                <th><?php p($l->t('Actions')); ?></th>
            </tr>
        </thead>
        <tbody id="aum-apps-tbody">
            <tr class="aum-empty-row">
                <td colspan="5"><?php p($l->t('Loading...')); ?></td>
            </tr>
        </tbody>
    </table>
</div>
