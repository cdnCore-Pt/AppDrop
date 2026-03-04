/**
 * SPDX-FileCopyrightText: 2026 CDNTV
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Upload module — drag & drop, multi-file upload, health check integration.
 *
 * Flow: drop/select zip → immediate validation + icon preview → click Install → install only.
 */
(function () {
    'use strict';

    var form = document.getElementById('aum-form');
    if (!form) return;

    var maxSizeEl = document.getElementById('aum-max-size-mb');
    var maxSizeMB = maxSizeEl ? parseInt(maxSizeEl.value, 10) : 20;
    var MAX_SIZE_BYTES = maxSizeMB * 1024 * 1024;

    var fileInput = document.getElementById('aum-zipfile');
    var submitBtn = document.getElementById('aum-submit');
    var spinner = document.getElementById('aum-spinner');
    var messageArea = document.getElementById('aum-message');
    var dropzone = document.getElementById('aum-dropzone');
    var fileQueue = document.getElementById('aum-file-queue');
    var defaultBtnText = submitBtn ? submitBtn.textContent.trim() : 'Install / Update';

    // Each entry: { file: File, validated: bool, valid: bool, report: object|null }
    var pendingFiles = [];
    var validateUrl = form.action.replace('/install', '/validate');

    // ── Drag & Drop ──────────────────────────────────────────────────────────

    if (dropzone) {
        ['dragenter', 'dragover'].forEach(function (evt) {
            dropzone.addEventListener(evt, function (e) {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.add('aum-dropzone--active');
            });
        });

        ['dragleave', 'drop'].forEach(function (evt) {
            dropzone.addEventListener(evt, function (e) {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.remove('aum-dropzone--active');
            });
        });

        dropzone.addEventListener('drop', function (e) {
            var files = e.dataTransfer.files;
            if (files.length > 0) {
                addFiles(files);
            }
        });

        dropzone.addEventListener('click', function (e) {
            if (e.target === fileInput) return;
            fileInput.click();
        });
    }

    if (fileInput) {
        fileInput.addEventListener('change', function () {
            if (this.files.length > 0) {
                addFiles(this.files);
            }
        });
    }

    // ── File queue management ────────────────────────────────────────────────

    function addFiles(fileList) {
        var newIndices = [];
        for (var i = 0; i < fileList.length; i++) {
            var file = fileList[i];
            if (!file.name.toLowerCase().endsWith('.zip')) {
                showMessage('error', 'Skipped "' + file.name + '": only .zip files accepted.');
                continue;
            }
            if (file.size > MAX_SIZE_BYTES) {
                showMessage('error', 'Skipped "' + file.name + '": exceeds ' + maxSizeMB + ' MB limit.');
                continue;
            }
            var idx = pendingFiles.length;
            pendingFiles.push({ file: file, validated: false, valid: false, report: null });
            newIndices.push(idx);
        }
        renderQueue();
        clearMessage();

        // Immediately validate each new file (for icon preview + early feedback)
        newIndices.forEach(function (idx) {
            validateFile(idx);
        });
    }

    function removeFromQueue(index) {
        pendingFiles.splice(index, 1);
        renderQueue();
    }

    function renderQueue() {
        if (!fileQueue) return;

        if (pendingFiles.length === 0) {
            fileQueue.classList.add('aum-file-queue--hidden');
            fileQueue.innerHTML = '';
            return;
        }

        fileQueue.classList.remove('aum-file-queue--hidden');
        var html = '';
        pendingFiles.forEach(function (entry, idx) {
            var sizeKB = Math.round(entry.file.size / 1024);
            html += '<div class="aum-file-item" data-index="' + idx + '">'
                + '<span class="aum-file-item__icon" data-file-icon="' + idx + '"></span>'
                + '<span class="aum-file-item__name">' + escapeHtml(entry.file.name) + '</span>'
                + '<span class="aum-file-item__size">' + sizeKB + ' KB</span>'
                + '<span class="aum-file-item__status" data-file-status="' + idx + '"></span>'
                + '<button type="button" class="aum-file-item__remove" data-remove="' + idx + '" title="Remove">&times;</button>'
                + '</div>';
        });
        fileQueue.innerHTML = html;

        // Re-apply any already-fetched icons and statuses
        pendingFiles.forEach(function (entry, idx) {
            if (entry.validated) {
                showIconPreview(idx, entry.report ? entry.report.icon : null);
                if (entry.valid) {
                    var info = entry.report;
                    var label = (info.name || info.appId || '?') + ' v' + (info.version || '?');
                    if (info.warnings && info.warnings.length > 0) {
                        updateFileStatus(idx, 'warning', label + ' — ' + info.warnings.length + ' warning(s)');
                    } else {
                        updateFileStatus(idx, 'success', label + ' — Ready to install');
                    }
                } else {
                    var errors = entry.report && entry.report.errors ? entry.report.errors : ['Validation failed'];
                    updateFileStatus(idx, 'error', errors.join('; '));
                }
            } else {
                updateFileStatus(idx, 'validating', 'Checking...');
            }
        });

        // Attach remove handlers
        fileQueue.querySelectorAll('[data-remove]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                removeFromQueue(parseInt(this.getAttribute('data-remove'), 10));
            });
        });
    }

    // ── Immediate validation (on file add) ──────────────────────────────────

    function validateFile(index) {
        var entry = pendingFiles[index];
        if (!entry) return;

        updateFileStatus(index, 'validating', 'Checking...');

        var data = new FormData();
        data.append('zipFile', entry.file);
        data.append('requesttoken', getRequestToken());

        fetch(validateUrl, {
            method: 'POST',
            body: data,
            credentials: 'same-origin',
            headers: { 'requesttoken': getRequestToken() }
        })
        .then(function (r) { return r.json(); })
        .then(function (report) {
            // Store results
            entry.validated = true;
            entry.report = report;
            entry.valid = !(report.errors && report.errors.length > 0);

            // Show icon preview
            showIconPreview(index, report.icon || null);

            // Show validation result
            if (!entry.valid) {
                updateFileStatus(index, 'error', report.errors.join('; '));
            } else {
                var label = (report.name || report.appId || '?') + ' v' + (report.version || '?');
                if (report.warnings && report.warnings.length > 0) {
                    updateFileStatus(index, 'warning', label + ' — ' + report.warnings.length + ' warning(s)');
                } else {
                    updateFileStatus(index, 'success', label + ' — Ready to install');
                }
            }
        })
        .catch(function (err) {
            entry.validated = true;
            entry.valid = false;
            entry.report = { errors: ['Validation request failed: ' + err.message], warnings: [] };
            showIconPreview(index, null);
            updateFileStatus(index, 'error', 'Validation failed: ' + err.message);
        });
    }

    function showIconPreview(index, iconDataUrl) {
        var el = document.querySelector('[data-file-icon="' + index + '"]');
        if (!el) return;
        if (iconDataUrl) {
            el.innerHTML = '<img src="' + iconDataUrl + '" class="aum-file-item__icon-img" alt="App icon">';
        } else {
            el.innerHTML = '<span class="aum-file-item__icon-missing" title="No app icon found">?</span>';
        }
    }

    // ── Form submission: install validated files ─────────────────────────────

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        if (pendingFiles.length === 0) {
            showMessage('error', 'Please select at least one .zip file.');
            return;
        }

        // Check if all files have been validated
        var allValidated = pendingFiles.every(function (e) { return e.validated; });
        if (!allValidated) {
            showMessage('warning', 'Some files are still being checked. Please wait.');
            return;
        }

        // Check if any valid files to install
        var validFiles = pendingFiles.filter(function (e) { return e.valid; });
        if (validFiles.length === 0) {
            showMessage('error', 'No valid files to install. Fix the errors and try again.');
            return;
        }

        setLoading(true);
        clearMessage();
        installQueue(0);
    });

    function installQueue(index) {
        // Skip invalid or find next valid
        while (index < pendingFiles.length && !pendingFiles[index].valid) {
            index++;
        }

        if (index >= pendingFiles.length) {
            setLoading(false);
            var installed = pendingFiles.filter(function (e) { return e.valid; }).length;
            showMessage('success', installed + ' app(s) installed successfully.');
            pendingFiles = [];
            renderQueue();
            if (fileInput) fileInput.value = '';
            return;
        }

        var entry = pendingFiles[index];
        updateFileStatus(index, 'installing', 'Installing...');

        var autoEnable = document.getElementById('aum-auto-enable');
        var installData = new FormData();
        installData.append('zipFile', entry.file);
        installData.append('autoEnable', autoEnable && autoEnable.checked ? '1' : '0');
        installData.append('requesttoken', getRequestToken());

        fetch(form.action, {
            method: 'POST',
            body: installData,
            credentials: 'same-origin',
            headers: { 'requesttoken': getRequestToken() }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                updateFileStatus(index, 'success', data.message);
            } else {
                updateFileStatus(index, 'error', data.message || 'Install failed.');
            }
            installQueue(index + 1);
        })
        .catch(function (err) {
            updateFileStatus(index, 'error', 'Install failed: ' + err.message);
            installQueue(index + 1);
        });
    }

    function updateFileStatus(index, type, msg) {
        var el = document.querySelector('[data-file-status="' + index + '"]');
        if (el) {
            el.textContent = msg;
            el.className = 'aum-file-item__status aum-file-item__status--' + type;
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    function getRequestToken() {
        if (typeof OC !== 'undefined' && OC.requestToken) {
            return OC.requestToken;
        }
        var tokenInput = document.querySelector('input[name="requesttoken"]');
        return tokenInput ? tokenInput.value : '';
    }

    function showMessage(type, msg) {
        if (!messageArea) return;
        messageArea.textContent = msg;
        messageArea.className = 'aum-alert aum-alert--' + type;
        messageArea.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function clearMessage() {
        if (!messageArea) return;
        messageArea.className = 'aum-alert aum-alert--hidden';
        messageArea.textContent = '';
    }

    function setLoading(loading) {
        if (submitBtn) {
            submitBtn.disabled = loading;
            submitBtn.innerHTML = loading
                ? 'Installing\u2026'
                : '<span class="aum-btn__icon" aria-hidden="true">\u21EA</span> ' + defaultBtnText;
        }
        if (spinner) {
            spinner.className = loading ? 'aum-spinner' : 'aum-spinner aum-spinner--hidden';
        }
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

}());
