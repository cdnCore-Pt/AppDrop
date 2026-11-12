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

    // Nextcloud 34 removed OC.Apps (data-apps-slide-toggle) and the global
    // snapper, so the settings panel and the mobile sidebar are driven here.
    // Core CSS still reveals them via #app-settings.open / body.snapjs-left.
    var settings = document.getElementById('app-settings');
    var settingsButton = settings && settings.querySelector('.settings-button');
    if (settingsButton) {
        settingsButton.addEventListener('click', function () {
            settingsButton.setAttribute('aria-expanded', String(settings.classList.toggle('open')));
        });
    }

    // NC 30-33 still ship the snapper (and OC.Apps); core injects its own
    // toggle there, after this handler runs, so detect the API, not the element.
    if (!(window.OC && window.OC.Apps)) {
        var nav = document.getElementById('app-navigation');
        var closeNav = function () {
            document.body.classList.remove('snapjs-left');
            navToggle.setAttribute('aria-expanded', 'false');
        };
        var navToggle = document.createElement('button');
        navToggle.id = 'app-navigation-toggle';
        navToggle.className = 'icon-menu aum-nav-toggle';
        navToggle.type = 'button';
        navToggle.setAttribute('aria-label', nav.getAttribute('data-toggle-label') || 'Menu');
        navToggle.setAttribute('aria-controls', 'app-navigation');
        navToggle.setAttribute('aria-expanded', 'false');
        navToggle.addEventListener('click', function () {
            navToggle.setAttribute('aria-expanded', String(document.body.classList.toggle('snapjs-left')));
        });
        // Sibling of the drawer, not a child of #app-content: that one is its
        // own stacking context and would put the toggle under the open drawer.
        nav.parentNode.insertBefore(navToggle, nav);
        navItems.forEach(function (item) {
            item.addEventListener('click', closeNav);
        });
        document.getElementById('app-content').addEventListener('click', closeNav);
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeNav();
        });
    }

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
