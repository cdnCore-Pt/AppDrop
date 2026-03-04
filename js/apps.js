/**
 * SPDX-FileCopyrightText: 2026 CDNTV
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * App Manager module — list, enable, disable, remove custom apps via AJAX.
 */
(function () {
    'use strict';

    var tbody = document.getElementById('aum-apps-tbody');
    var messageArea = document.getElementById('aum-apps-message');
    var refreshBtn = document.getElementById('aum-apps-refresh');
    var loaded = false;

    var baseUrl = OC.generateUrl('/apps/appdrop/admin/apps');

    // Load when tab activated
    document.addEventListener('aum:tabactivated', function (e) {
        if (e.detail.tab === 'apps') {
            loadApps();
        }
    });

    if (refreshBtn) {
        refreshBtn.addEventListener('click', function () { loadApps(); });
    }

    function loadApps() {
        showLoading();
        fetch(baseUrl, {
            credentials: 'same-origin',
            headers: { 'requesttoken': getRequestToken() }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success && data.apps) {
                renderApps(data.apps);
                loaded = true;
            } else {
                showMessage('error', data.message || 'Failed to load apps.');
            }
        })
        .catch(function (err) {
            showMessage('error', 'Request failed: ' + err.message);
        });
    }

    function renderApps(apps) {
        if (!tbody) return;

        if (apps.length === 0) {
            tbody.innerHTML = '<tr class="aum-empty-row"><td colspan="5">No custom apps found.</td></tr>';
            return;
        }

        var html = '';
        apps.forEach(function (app) {
            var statusClass = app.enabled ? 'aum-badge--success' : 'aum-badge--warning';
            var statusText = app.enabled ? 'Enabled' : 'Disabled';

            html += '<tr>'
                + '<td><code>' + escapeHtml(app.id) + '</code></td>'
                + '<td>' + escapeHtml(app.name) + '</td>'
                + '<td>' + escapeHtml(app.version) + '</td>'
                + '<td><span class="aum-badge ' + statusClass + '">' + statusText + '</span></td>'
                + '<td class="aum-actions">';

            if (app.enabled) {
                html += '<button class="aum-btn aum-btn--small aum-btn--warning" '
                    + 'data-action="disable" data-app="' + escapeHtml(app.id) + '">Disable</button>';
            } else {
                html += '<button class="aum-btn aum-btn--small aum-btn--success" '
                    + 'data-action="enable" data-app="' + escapeHtml(app.id) + '">Enable</button>';
            }

            html += ' <button class="aum-btn aum-btn--small aum-btn--danger" '
                + 'data-action="remove" data-app="' + escapeHtml(app.id) + '">Remove</button>';

            html += '</td></tr>';
        });

        tbody.innerHTML = html;
        attachActionHandlers();
    }

    function attachActionHandlers() {
        if (!tbody) return;
        tbody.querySelectorAll('[data-action]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var action = this.getAttribute('data-action');
                var appId = this.getAttribute('data-app');

                if (action === 'remove') {
                    if (!confirm('Remove app "' + appId + '"? This will delete the app files. Backups are not affected.')) {
                        return;
                    }
                }

                performAction(action, appId);
            });
        });
    }

    function performAction(action, appId) {
        var url = baseUrl + '/' + action;
        clearMessage();

        fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'requesttoken': getRequestToken()
            },
            body: JSON.stringify({ appId: appId })
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                showMessage('success', data.message);
                loadApps();
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
            tbody.innerHTML = '<tr class="aum-empty-row"><td colspan="5">Loading...</td></tr>';
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
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }
}());
