/**
 * SPDX-FileCopyrightText: 2026 CDNTV
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Sidebar navigation module — hash-based routing with custom events.
 * Replaces the old tab-based navigation with Nextcloud's standard #app-navigation pattern.
 */
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    var navItems = document.querySelectorAll('#app-navigation li[data-section]');
    var sections = document.querySelectorAll('#app-content .aum-section[data-section]');

    if (!navItems.length) return;

    function activateSection(sectionName) {
        var found = false;

        navItems.forEach(function (item) {
            var isActive = item.getAttribute('data-section') === sectionName;
            item.classList.toggle('active', isActive);
            if (isActive) found = true;
        });

        // If section not found (e.g. non-admin trying to access admin section), fall back to upload
        if (!found) {
            sectionName = 'upload';
            navItems.forEach(function (item) {
                item.classList.toggle('active', item.getAttribute('data-section') === 'upload');
            });
        }

        sections.forEach(function (section) {
            var isActive = section.getAttribute('data-section') === sectionName;
            section.classList.toggle('aum-section--active', isActive);
        });

        // Dispatch custom event so section-specific modules can react (lazy loading)
        document.dispatchEvent(new CustomEvent('aum:tabactivated', {
            detail: { tab: sectionName }
        }));
    }

    // Click handler for nav items
    navItems.forEach(function (item) {
        item.querySelector('a').addEventListener('click', function (e) {
            e.preventDefault();
            var sectionName = item.getAttribute('data-section');
            window.location.hash = sectionName;
            activateSection(sectionName);
        });
    });

    // Handle hash on page load and hash changes
    function handleHash() {
        var hash = window.location.hash.replace('#', '');
        if (hash && document.querySelector('#app-navigation li[data-section="' + hash + '"]')) {
            activateSection(hash);
        }
    }

    window.addEventListener('hashchange', handleHash);
    handleHash();
});
