/**
 * SPDX-FileCopyrightText: 2026 CDNTV
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * History module — paginated upload history table.
 */
(function () {
    'use strict';

    var tbody = document.getElementById('aum-history-tbody');
    var messageArea = document.getElementById('aum-history-message');
    var pagination = document.getElementById('aum-history-pagination');
    var prevBtn = document.getElementById('aum-history-prev');
    var nextBtn = document.getElementById('aum-history-next');
    var pageInfo = document.getElementById('aum-history-page');

    var baseUrl = OC.generateUrl('/apps/appdrop/admin/history');
    var currentPage = 1;
    var perPage = 20;
    var loaded = false;

    // Load when tab activated
    document.addEventListener('aum:tabactivated', function (e) {
        if (e.detail.tab === 'history') {
            loadHistory(1);
        }
    });

    if (prevBtn) {
        prevBtn.addEventListener('click', function () {
            if (currentPage > 1) loadHistory(currentPage - 1);
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', function () {
            loadHistory(currentPage + 1);
        });
    }

    function loadHistory(page) {
        showLoading();
        var url = baseUrl + '?page=' + page + '&limit=' + perPage;

        fetch(url, {
            credentials: 'same-origin',
            headers: { 'requesttoken': getRequestToken() }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                currentPage = page;
                renderHistory(data.entries || []);
                updatePagination(data.total || 0);
                loaded = true;
            } else {
                showMessage('error', data.message || 'Failed to load history.');
            }
        })
        .catch(function (err) {
            showMessage('error', 'Request failed: ' + err.message);
        });
    }

    function renderHistory(entries) {
        if (!tbody) return;

        if (entries.length === 0) {
            tbody.innerHTML = '<tr class="aum-empty-row"><td colspan="6">No upload history found.</td></tr>';
            return;
        }

        var html = '';
        entries.forEach(function (entry) {
            var resultClass = entry.result === 'success' ? 'aum-badge--success' : 'aum-badge--error';
            var date = entry.createdAt ? new Date(entry.createdAt * 1000).toLocaleString() : '—';

            html += '<tr>'
                + '<td>' + escapeHtml(date) + '</td>'
                + '<td><code>' + escapeHtml(entry.appId || '—') + '</code></td>'
                + '<td>' + escapeHtml(entry.version || '—') + '</td>'
                + '<td>' + escapeHtml(entry.filename || '—') + '</td>'
                + '<td><span class="aum-badge ' + resultClass + '">' + escapeHtml(entry.result) + '</span></td>'
                + '<td>' + escapeHtml(entry.userId || '—') + '</td>'
                + '</tr>';
        });

        tbody.innerHTML = html;
    }

    function updatePagination(total) {
        if (!pagination) return;

        var totalPages = Math.max(1, Math.ceil(total / perPage));

        if (total <= perPage) {
            pagination.classList.add('aum-pagination--hidden');
            return;
        }

        pagination.classList.remove('aum-pagination--hidden');
        if (pageInfo) pageInfo.textContent = 'Page ' + currentPage + ' of ' + totalPages;
        if (prevBtn) prevBtn.disabled = currentPage <= 1;
        if (nextBtn) nextBtn.disabled = currentPage >= totalPages;
    }

    function showLoading() {
        if (tbody) {
            tbody.innerHTML = '<tr class="aum-empty-row"><td colspan="6">Loading...</td></tr>';
        }
    }

    function showMessage(type, msg) {
        if (!messageArea) return;
        messageArea.textContent = msg;
        messageArea.className = 'aum-alert aum-alert--' + type;
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
