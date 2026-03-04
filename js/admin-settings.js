/**
 * SPDX-FileCopyrightText: 2026 CDNTV
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Admin settings page — handles general settings (save) and permissions
 * (autocomplete search, add/remove users & groups).
 *
 * Loaded on Settings → Administration → AppDrop.
 */
(function () {
    'use strict';

    var baseUrl = OC.generateUrl('/apps/appdrop/admin');
    var settingsUrl = baseUrl + '/settings/save';
    var permUrl = baseUrl + '/permissions';
    var searchTimeout = null;

    // ── General settings ─────────────────────────────────────────────────────

    var saveBtn = document.getElementById('aum-settings-save');
    var savedLabel = document.getElementById('aum-settings-saved');

    if (saveBtn) {
        saveBtn.addEventListener('click', function () {
            var maxSizeInput = document.getElementById('aum-settings-max-size');
            var autoEnableInput = document.getElementById('aum-settings-auto-enable');

            var data = {
                maxSizeMB: maxSizeInput ? parseInt(maxSizeInput.value, 10) : 20,
                autoEnable: autoEnableInput ? autoEnableInput.checked : true,
            };

            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving\u2026';

            fetch(settingsUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'requesttoken': getRequestToken(),
                },
                body: JSON.stringify(data),
            })
            .then(function (r) { return r.json(); })
            .then(function (resp) {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save';

                if (resp.success) {
                    showSaved();
                } else {
                    showPermMessage('error', resp.message || 'Failed to save settings.');
                }
            })
            .catch(function (err) {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save';
                showPermMessage('error', 'Failed to save: ' + err.message);
            });
        });
    }

    function showSaved() {
        if (!savedLabel) return;
        savedLabel.classList.remove('aum-settings-saved--hidden');
        setTimeout(function () {
            savedLabel.classList.add('aum-settings-saved--hidden');
        }, 2500);
    }

    // ── Permissions ──────────────────────────────────────────────────────────

    function setupPermSearch(type) {
        var input = document.getElementById('aum-settings-' + type + '-search');
        var resultsDiv = document.getElementById('aum-settings-' + type + '-results');
        if (!input || !resultsDiv) return;

        input.addEventListener('input', function () {
            var term = this.value.trim();
            if (term.length < 2) {
                resultsDiv.innerHTML = '';
                resultsDiv.classList.remove('aum-autocomplete--visible');
                return;
            }

            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function () {
                var searchUrl = permUrl + '/search/' + type + 's?term=' + encodeURIComponent(term);
                fetch(searchUrl, {
                    credentials: 'same-origin',
                    headers: { 'requesttoken': getRequestToken() },
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.success || !data.results.length) {
                        resultsDiv.innerHTML = '<div class="aum-autocomplete__empty">No results</div>';
                        resultsDiv.classList.add('aum-autocomplete--visible');
                        return;
                    }
                    resultsDiv.innerHTML = data.results.map(function (item) {
                        var label = item.displayName
                            ? escapeHtml(item.displayName) + ' (' + escapeHtml(item.id) + ')'
                            : escapeHtml(item.id);
                        return '<div class="aum-autocomplete__item" data-id="' + escapeHtml(item.id) + '">'
                            + label + '</div>';
                    }).join('');
                    resultsDiv.classList.add('aum-autocomplete--visible');

                    resultsDiv.querySelectorAll('.aum-autocomplete__item').forEach(function (el) {
                        el.addEventListener('click', function () {
                            addPermEntry(type, this.dataset.id);
                        });
                    });
                })
                .catch(function () {
                    resultsDiv.innerHTML = '';
                    resultsDiv.classList.remove('aum-autocomplete--visible');
                });
            }, 300);
        });

        document.addEventListener('click', function (e) {
            if (!input.contains(e.target) && !resultsDiv.contains(e.target)) {
                resultsDiv.innerHTML = '';
                resultsDiv.classList.remove('aum-autocomplete--visible');
            }
        });
    }

    function addPermEntry(type, id) {
        var url = permUrl + '/' + type + 's';
        var body = type === 'user' ? { userId: id } : { groupId: id };

        fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'requesttoken': getRequestToken(),
            },
            body: JSON.stringify(body),
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                reloadPermissions();
                clearPermSearch(type);
            } else {
                showPermMessage('error', data.message || 'Failed to add.');
            }
        })
        .catch(function (err) {
            showPermMessage('error', 'Failed to add: ' + err.message);
        });
    }

    function removePermEntry(type, id) {
        var url = permUrl + '/' + type + 's/remove';
        var body = type === 'user' ? { userId: id } : { groupId: id };

        fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'requesttoken': getRequestToken(),
            },
            body: JSON.stringify(body),
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                reloadPermissions();
            } else {
                showPermMessage('error', data.message || 'Failed to remove.');
            }
        })
        .catch(function (err) {
            showPermMessage('error', 'Failed to remove: ' + err.message);
        });
    }

    function reloadPermissions() {
        fetch(permUrl, {
            credentials: 'same-origin',
            headers: { 'requesttoken': getRequestToken() },
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success) return;
            renderPermList('group', data.groups || []);
            renderPermList('user', data.users || []);
        });
    }

    function renderPermList(type, items) {
        var list = document.getElementById('aum-settings-' + type + '-list');
        if (!list) return;

        if (items.length === 0) {
            list.innerHTML = '<li class="aum-perm-empty">No ' + type + 's added yet.</li>';
            return;
        }

        list.innerHTML = items.map(function (id) {
            return '<li class="aum-perm-item">'
                + '<span class="aum-perm-item__name">' + escapeHtml(id) + '</span>'
                + '<button type="button" class="aum-btn aum-btn--danger aum-btn--small aum-settings-perm-remove" '
                + 'data-type="' + type + '" data-id="' + escapeHtml(id) + '">Remove</button>'
                + '</li>';
        }).join('');

        list.querySelectorAll('.aum-settings-perm-remove').forEach(function (btn) {
            btn.addEventListener('click', function () {
                removePermEntry(this.dataset.type, this.dataset.id);
            });
        });
    }

    function clearPermSearch(type) {
        var input = document.getElementById('aum-settings-' + type + '-search');
        var resultsDiv = document.getElementById('aum-settings-' + type + '-results');
        if (input) input.value = '';
        if (resultsDiv) {
            resultsDiv.innerHTML = '';
            resultsDiv.classList.remove('aum-autocomplete--visible');
        }
    }

    function showPermMessage(type, msg) {
        var el = document.getElementById('aum-settings-perm-message');
        if (!el) return;
        el.textContent = msg;
        el.className = 'aum-alert aum-alert--' + type;
        setTimeout(function () {
            el.className = 'aum-alert aum-alert--hidden';
        }, 5000);
    }

    // ── Initialize ───────────────────────────────────────────────────────────

    // Setup autocomplete
    setupPermSearch('user');
    setupPermSearch('group');

    // Attach remove handlers to server-rendered buttons
    document.querySelectorAll('.aum-settings-perm-remove').forEach(function (btn) {
        btn.addEventListener('click', function () {
            removePermEntry(this.dataset.type, this.dataset.id);
        });
    });

    // ── Helpers ──────────────────────────────────────────────────────────────

    function getRequestToken() {
        return (typeof OC !== 'undefined' && OC.requestToken) ? OC.requestToken : '';
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }
}());
