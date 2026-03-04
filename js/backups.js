/**
 * SPDX-FileCopyrightText: 2026 CDNTV
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Backup Management module — list, restore, delete backups via AJAX.
 */
(function () {
    'use strict';

    var tbody = document.getElementById('aum-backups-tbody');
    var messageArea = document.getElementById('aum-backups-message');
    var refreshBtn = document.getElementById('aum-backups-refresh');

    var baseUrl = OC.generateUrl('/apps/appdrop/admin/backups');
    var loaded = false;

    // Load when tab activated
    document.addEventListener('aum:tabactivated', function (e) {
        if (e.detail.tab === 'backups') {
            loadBackups();
        }
    });

    if (refreshBtn) {
        refreshBtn.addEventListener('click', function () { loadBackups(); });
    }

    function loadBackups() {
        showLoading();
        fetch(baseUrl, {
            credentials: 'same-origin',
            headers: { 'requesttoken': getRequestToken() }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                renderBackups(data.backups || []);
                loaded = true;
            } else {
                showMessage('error', data.message || 'Failed to load backups.');
            }
        })
        .catch(function (err) {
            showMessage('error', 'Request failed: ' + err.message);
        });
    }

    function renderBackups(backups) {
        if (!tbody) return;

        if (backups.length === 0) {
            tbody.innerHTML = '<tr class="aum-empty-row"><td colspan="4">No backups found.</td></tr>';
            return;
        }

        var html = '';
        backups.forEach(function (backup) {
            html += '<tr>'
                + '<td><code>' + escapeHtml(backup.appId) + '</code></td>'
                + '<td>' + escapeHtml(backup.date) + '</td>'
                + '<td><code>' + escapeHtml(backup.dirName) + '</code></td>'
                + '<td class="aum-actions">'
                + '<button class="aum-btn aum-btn--small aum-btn--primary" '
                + 'data-action="restore" data-dir="' + escapeHtml(backup.dirName) + '">Restore</button> '
                + '<button class="aum-btn aum-btn--small aum-btn--danger" '
                + 'data-action="delete" data-dir="' + escapeHtml(backup.dirName) + '">Delete</button>'
                + '</td></tr>';
        });

        tbody.innerHTML = html;
        attachActionHandlers();
    }

    function attachActionHandlers() {
        if (!tbody) return;
        tbody.querySelectorAll('[data-action]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var action = this.getAttribute('data-action');
                var dirName = this.getAttribute('data-dir');

                if (action === 'delete') {
                    if (!confirm('Delete backup "' + dirName + '"? This cannot be undone.')) {
                        return;
                    }
                }

                if (action === 'restore') {
                    if (!confirm('Restore backup "' + dirName + '"? This will replace the current app version.')) {
                        return;
                    }
                }

                performAction(action, dirName);
            });
        });
    }

    function performAction(action, dirName) {
        var url = baseUrl + '/' + action;
        clearMessage();

        fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'requesttoken': getRequestToken()
            },
            body: JSON.stringify({ dirName: dirName })
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                showMessage('success', data.message);
                loadBackups();
            } else {
                showMessage('error', data.message || 'Action failed.');
            }
        })
        .catch(function (err) {
            showMessage('error', 'Request failed: ' + err.message);
        });
    }

    function showLoading() {
        if (tbody) {
            tbody.innerHTML = '<tr class="aum-empty-row"><td colspan="4">Loading...</td></tr>';
        }
    }

    function showMessage(type, msg) {
        if (!messageArea) return;
        messageArea.textContent = msg;
        messageArea.className = 'aum-alert aum-alert--' + type;
    }

    function clearMessage() {
        if (!messageArea) return;
        messageArea.className = 'aum-alert aum-alert--hidden';
        messageArea.textContent = '';
    }

    function getRequestToken() {
        return (typeof OC !== 'undefined' && OC.requestToken) ? OC.requestToken : '';
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str || ''));
        return div.innerHTML;
    }
}());
