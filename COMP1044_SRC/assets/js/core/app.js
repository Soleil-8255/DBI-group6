/**
 * Core app shell: always-loaded behaviour (footer year, form UX, admin cards).
 * Feature-specific scripts live in assets/js/modules/ and load after this file.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const sessionUid = (document.body.getAttribute('data-session-user-id') || '').trim();
        const LS_SIDEBAR = sessionUid ? 'ir-sidebar-collapsed-' + sessionUid : 'ir-sidebar-collapsed';

        function syncSidebarAria() {
            if (!sidebarToggle) {
                return;
            }
            const collapsed = document.body.classList.contains('sidebar-collapsed');
            sidebarToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        }
        if (sidebarToggle) {
            let stored = localStorage.getItem(LS_SIDEBAR);
            if (stored === null && sessionUid) {
                const legacy = localStorage.getItem('ir-sidebar-collapsed');
                if (legacy === '1') {
                    localStorage.setItem(LS_SIDEBAR, '1');
                    localStorage.removeItem('ir-sidebar-collapsed');
                }
                stored = localStorage.getItem(LS_SIDEBAR);
            }
            if (stored === '1') {
                document.body.classList.add('sidebar-collapsed');
            }
            syncSidebarAria();
            sidebarToggle.addEventListener('click', function () {
                document.body.classList.toggle('sidebar-collapsed');
                localStorage.setItem(LS_SIDEBAR, document.body.classList.contains('sidebar-collapsed') ? '1' : '0');
                syncSidebarAria();
            });
        }

        const userMenuRoot = document.querySelector('[data-dashboard-user-menu]');
        const userMenuTrigger = document.getElementById('user-menu-trigger');
        const userMenuPanel = document.getElementById('user-menu-panel');
        function closeUserMenu() {
            if (!userMenuPanel || !userMenuTrigger) {
                return;
            }
            userMenuPanel.classList.remove('active');
            userMenuTrigger.setAttribute('aria-expanded', 'false');
            userMenuPanel.setAttribute('aria-hidden', 'true');
        }
        function toggleUserMenu() {
            if (!userMenuPanel || !userMenuTrigger) {
                return;
            }
            const open = userMenuPanel.classList.toggle('active');
            userMenuTrigger.setAttribute('aria-expanded', open ? 'true' : 'false');
            userMenuPanel.setAttribute('aria-hidden', open ? 'false' : 'true');
        }
        if (userMenuTrigger && userMenuPanel && userMenuRoot) {
            userMenuTrigger.addEventListener('click', function () {
                toggleUserMenu();
            });
            document.addEventListener('click', function (e) {
                if (!userMenuRoot.contains(e.target)) {
                    closeUserMenu();
                }
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    closeUserMenu();
                }
            });
        }

        const utils = window.Irms && window.Irms.utils;
        const year = utils ? utils.currentYear() : new Date().getFullYear();

        const yearEl = document.querySelector('[data-current-year]');
        if (yearEl) {
            yearEl.textContent = String(year);
        }

        const greetEl = document.getElementById('dashboard-greeting');
        if (greetEl) {
            const name = (greetEl.getAttribute('data-user-name') || '').trim();
            const hours = new Date().getHours();
            let prefix = 'Good evening';
            if (hours < 12) {
                prefix = 'Good morning';
            } else if (hours < 18) {
                prefix = 'Good afternoon';
            }
            const who = name !== '' ? name : 'there';
            greetEl.textContent = prefix + ', ' + who + '.';
        }

        document.querySelectorAll('.js-confirm-delete').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                const message = form.getAttribute('data-confirm-message') || 'Are you sure you want to continue?';
                if (!window.confirm(message)) {
                    event.preventDefault();
                }
            });
        });

        document.querySelectorAll('.admin-messages [data-auto-dismiss="true"]').forEach(function (flash) {
            const ms = 4500;
            window.setTimeout(function () {
                flash.classList.add('admin-toast--leaving');
                function removeNode() {
                    const wrap = document.getElementById('admin-messages');
                    flash.remove();
                    if (wrap && wrap.children.length === 0) {
                        wrap.remove();
                    }
                }
                function onEnd(e) {
                    if (e.propertyName === 'opacity') {
                        flash.removeEventListener('transitionend', onEnd);
                        removeNode();
                    }
                }
                flash.addEventListener('transitionend', onEnd);
                window.setTimeout(function () {
                    if (flash.parentNode) {
                        removeNode();
                    }
                }, 600);
            }, ms);
        });

        document.querySelectorAll('.js-data-card [data-toggle-panel]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const id = btn.getAttribute('data-toggle-panel');
                if (!id) {
                    return;
                }
                const panel = document.getElementById(id);
                if (!panel || !panel.classList.contains('js-data-card-panel')) {
                    return;
                }
                const card = btn.closest('.js-data-card');
                if (!card) {
                    return;
                }
                const wasHidden = panel.hasAttribute('hidden');
                card.querySelectorAll('.js-data-card-panel').forEach(function (p) {
                    p.setAttribute('hidden', '');
                });
                card.querySelectorAll('[data-toggle-panel]').forEach(function (b) {
                    b.setAttribute('aria-expanded', 'false');
                });
                if (wasHidden) {
                    panel.removeAttribute('hidden');
                    btn.setAttribute('aria-expanded', 'true');
                }
            });
        });

        const appHeader = document.querySelector('header.app-header');
        const pageMainTitle = document.getElementById('page-main-title');
        if (appHeader && pageMainTitle && 'IntersectionObserver' in window) {
            function syncScrollLinkedTitle(entry) {
                if (entry.isIntersecting) {
                    appHeader.classList.remove('scrolled-past-title');
                    return;
                }
                if (entry.boundingClientRect.top < 0) {
                    appHeader.classList.add('scrolled-past-title');
                } else {
                    appHeader.classList.remove('scrolled-past-title');
                }
            }
            const titleObserver = new IntersectionObserver(
                function (entries) {
                    entries.forEach(function (e) {
                        syncScrollLinkedTitle(e);
                    });
                },
                { root: null, threshold: 0, rootMargin: '0px' }
            );
            titleObserver.observe(pageMainTitle);
        }
    });
})();
