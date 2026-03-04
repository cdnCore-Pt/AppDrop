<?php

/**
 * SPDX-FileCopyrightText: 2026 CDNTV
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/** @var \OCP\IL10N $l */
?>

<h2 class="aum-section-title"><?php p($l->t('App Skeleton Generator')); ?></h2>
<p class="aum-subtitle">
    <?php p($l->t('Create a ready-to-use Nextcloud app template with all required files and folder structure. Fill in the details below and download a .zip file you can immediately install.')); ?>
</p>

<div id="aum-tpl-message" class="aum-alert aum-alert--hidden" role="status" aria-live="polite"></div>

<div class="aum-tpl-layout">
    <form id="aum-tpl-form" class="aum-tpl-form" novalidate>

        <div class="aum-field">
            <label for="aum-tpl-name" class="aum-label">
                <?php p($l->t('App Display Name')); ?>
                <span class="aum-required" aria-hidden="true">*</span>
            </label>
            <input id="aum-tpl-name" type="text" class="aum-input"
                   placeholder="<?php p($l->t('e.g. My Cool App')); ?>" required>
            <span class="aum-hint"><?php p($l->t('The human-readable name shown in the Nextcloud app list.')); ?></span>
        </div>

        <div class="aum-field-row">
            <div class="aum-field aum-field--half">
                <label for="aum-tpl-id" class="aum-label">
                    <?php p($l->t('App ID')); ?>
                </label>
                <input id="aum-tpl-id" type="text" class="aum-input"
                       placeholder="<?php p($l->t('auto-generated')); ?>">
                <span class="aum-hint"><?php p($l->t('Unique identifier (lowercase, digits, underscores). Used as the directory name. Auto-generated from the display name.')); ?></span>
            </div>
            <div class="aum-field aum-field--half">
                <label for="aum-tpl-namespace" class="aum-label">
                    <?php p($l->t('PHP Namespace')); ?>
                </label>
                <input id="aum-tpl-namespace" type="text" class="aum-input"
                       placeholder="<?php p($l->t('auto-generated')); ?>">
                <span class="aum-hint"><?php p($l->t('PascalCase PHP namespace for your app classes (e.g. MyCoolApp). Auto-generated from the display name.')); ?></span>
            </div>
        </div>

        <div class="aum-field-row">
            <div class="aum-field aum-field--half">
                <label for="aum-tpl-version" class="aum-label">
                    <?php p($l->t('Version')); ?>
                </label>
                <input id="aum-tpl-version" type="text" class="aum-input" value="1.0.0">
            </div>
            <div class="aum-field aum-field--half">
                <label for="aum-tpl-author" class="aum-label">
                    <?php p($l->t('Author')); ?>
                </label>
                <input id="aum-tpl-author" type="text" class="aum-input"
                       placeholder="<?php p($l->t('Your Name')); ?>">
            </div>
        </div>

        <div class="aum-field">
            <label for="aum-tpl-description" class="aum-label">
                <?php p($l->t('Description')); ?>
            </label>
            <textarea id="aum-tpl-description" class="aum-input aum-input--textarea" rows="3"
                      placeholder="<?php p($l->t('Short description of your app')); ?>"></textarea>
        </div>

        <div class="aum-field">
            <button id="aum-tpl-submit" type="submit" class="aum-btn aum-btn--primary">
                <span class="aum-btn__icon" aria-hidden="true">&#11015;</span>
                <?php p($l->t('Generate & Download')); ?>
            </button>
            <span id="aum-tpl-spinner" class="aum-spinner aum-spinner--hidden" aria-hidden="true"></span>
        </div>

    </form>

    <div class="aum-tpl-preview">
        <h3 class="aum-tpl-preview__title"><?php p($l->t('Generated file structure')); ?></h3>
        <pre class="aum-tpl-preview__tree" id="aum-tpl-tree"><span class="aum-tree-app">your_app/</span>
├── appinfo/
│   ├── info.xml
│   └── routes.php
├── lib/
│   ├── AppInfo/
│   │   └── Application.php
│   └── Controller/
│       └── PageController.php
├── templates/
│   └── main.php
├── css/
│   └── style.css
├── js/
│   └── script.js
└── img/
    └── app.svg</pre>
    </div>
</div>
