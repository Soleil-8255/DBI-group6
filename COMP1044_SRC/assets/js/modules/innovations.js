/**
 * INNOVATION 1 / 5 — future charts (radar, self vs assessor) when data + Chart.js (or similar) is wired.
 * Placeholder only: marks #innovation-charts-root with data-innovations-ready.
 *
 * Security: this file only renders data already authorised by PHP.
 */
(function () {
    'use strict';

    /**
     * @param {HTMLElement | null} mount
     */
    function initInnovationCharts(mount) {
        if (!mount) {
            return;
        }
        if (document.querySelector('.eval-hero-pie')) {
            mount.setAttribute('data-innovations-ready', 'weight-svg');
        } else {
            mount.setAttribute('data-innovations-ready', 'pending');
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        initInnovationCharts(document.getElementById('innovation-charts-root'));
    });
})();
