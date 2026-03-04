/**
 * SPDX-FileCopyrightText: 2026 CDNTV
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Template Generator module — auto-generates appId/namespace, downloads skeleton .zip.
 */
(function () {
    'use strict';

    var form = document.getElementById('aum-tpl-form');
    if (!form) return;

    var nameInput = document.getElementById('aum-tpl-name');
    var idInput = document.getElementById('aum-tpl-id');
    var nsInput = document.getElementById('aum-tpl-namespace');
    var versionInput = document.getElementById('aum-tpl-version');
    var authorInput = document.getElementById('aum-tpl-author');
    var descInput = document.getElementById('aum-tpl-description');
    var submitBtn = document.getElementById('aum-tpl-submit');
    var spinner = document.getElementById('aum-tpl-spinner');
    var messageArea = document.getElementById('aum-tpl-message');

    var generateUrl = OC.generateUrl('/apps/appdrop/admin/generate');
    var treeAppName = document.getElementById('aum-tpl-tree')
        ? document.getElementById('aum-tpl-tree').querySelector('.aum-tree-app')
        : null;

    // Auto-generate appId and namespace from display name
    if (nameInput) {
        nameInput.addEventListener('input', function () {
            var name = this.value;

            // Auto-generate appId (snake_case) if user hasn't manually edited it
            if (!idInput.dataset.manual) {
                idInput.value = toSnakeCase(name);
            }

            // Auto-generate namespace (PascalCase) if user hasn't manually edited it
            if (!nsInput.dataset.manual) {
                nsInput.value = toPascalCase(name);
            }

            // Update tree preview
            updateTreePreview();
        });
    }

    // Mark as manually edited if user types in these fields
    if (idInput) {
        idInput.addEventListener('input', function () {
            this.dataset.manual = '1';
            updateTreePreview();
        });
    }
    if (nsInput) {
        nsInput.addEventListener('input', function () {
            this.dataset.manual = '1';
        });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        var name = nameInput ? nameInput.value.trim() : '';
        if (!name) {
            showMessage('error', 'Please enter an app display name.');
            return;
        }

        var appId = idInput ? idInput.value.trim() : toSnakeCase(name);
        if (!/^[a-z0-9_]{3,64}$/.test(appId)) {
            showMessage('error', 'App ID must be 3-64 characters, lowercase letters, digits and underscores only.');
            return;
        }

        setLoading(true);
        clearMessage();

        var params = {
            appName: name,
            appId: appId,
            namespace: nsInput ? nsInput.value.trim() : toPascalCase(name),
            version: versionInput ? versionInput.value.trim() : '1.0.0',
            author: authorInput ? authorInput.value.trim() : '',
            description: descInput ? descInput.value.trim() : ''
        };

        fetch(generateUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'requesttoken': getRequestToken()
            },
            body: JSON.stringify(params)
        })
        .then(function (response) {
            if (!response.ok) {
                return response.json().then(function (data) {
                    throw new Error(data.message || 'Generation failed.');
                });
            }

            var filename = appId + '.zip';
            var disposition = response.headers.get('Content-Disposition');
            if (disposition) {
                var match = disposition.match(/filename="?([^"]+)"?/);
                if (match) filename = match[1];
            }

            return response.blob().then(function (blob) {
                var url = window.URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                a.remove();
                window.URL.revokeObjectURL(url);

                setLoading(false);
                showMessage('success', 'App skeleton "' + appId + '" generated and downloaded.');
            });
        })
        .catch(function (err) {
            setLoading(false);
            showMessage('error', err.message || 'Generation failed.');
        });
    });

    function updateTreePreview() {
        if (!treeAppName) return;
        var appId = idInput ? idInput.value.trim() : '';
        treeAppName.textContent = (appId || 'your_app') + '/';
    }

    function toSnakeCase(str) {
        return str
            .replace(/[^a-zA-Z0-9\s]/g, '')
            .replace(/\s+/g, '_')
            .toLowerCase()
            .replace(/^_+|_+$/g, '');
    }

    function toPascalCase(str) {
        return str
            .replace(/[^a-zA-Z0-9\s]/g, '')
            .split(/\s+/)
            .map(function (word) {
                return word.charAt(0).toUpperCase() + word.slice(1).toLowerCase();
            })
            .join('');
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

    function setLoading(loading) {
        if (submitBtn) submitBtn.disabled = loading;
        if (spinner) spinner.className = loading ? 'aum-spinner' : 'aum-spinner aum-spinner--hidden';
    }

    function getRequestToken() {
        return (typeof OC !== 'undefined' && OC.requestToken) ? OC.requestToken : '';
    }
}());
