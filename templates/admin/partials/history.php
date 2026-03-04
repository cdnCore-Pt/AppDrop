<?php

/**
 * SPDX-FileCopyrightText: 2026 CDNTV
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/** @var \OCP\IL10N $l */
?>

<h2 class="aum-section-title"><?php p($l->t('History')); ?></h2>
<p class="aum-subtitle">
    <?php p($l->t('Upload history for all app installations performed via this tool.')); ?>
</p>

<div id="aum-history-message" class="aum-alert aum-alert--hidden" role="status" aria-live="polite"></div>

<div id="aum-history-list" class="aum-table-wrap">
    <table class="aum-table">
        <thead>
            <tr>
                <th><?php p($l->t('Date')); ?></th>
                <th><?php p($l->t('App ID')); ?></th>
                <th><?php p($l->t('Version')); ?></th>
                <th><?php p($l->t('File')); ?></th>
                <th><?php p($l->t('Result')); ?></th>
                <th><?php p($l->t('User')); ?></th>
            </tr>
        </thead>
        <tbody id="aum-history-tbody">
            <tr class="aum-empty-row">
                <td colspan="6"><?php p($l->t('Loading...')); ?></td>
            </tr>
        </tbody>
    </table>
</div>

<div id="aum-history-pagination" class="aum-pagination aum-pagination--hidden">
    <button id="aum-history-prev" class="aum-btn aum-btn--secondary" type="button" disabled>
        <?php p($l->t('Previous')); ?>
    </button>
    <span id="aum-history-page" class="aum-pagination__info"></span>
    <button id="aum-history-next" class="aum-btn aum-btn--secondary" type="button">
        <?php p($l->t('Next')); ?>
    </button>
</div>
