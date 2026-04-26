/**
 * Core: tiny helpers shared by pages (no side effects, no DOM).
 * Exposed on window.Irms to avoid global pollution.
 */
(function (global) {
    'use strict';

    const Irms = (global.Irms = global.Irms || {});

    Irms.utils = {
        /**
         * @returns {number}
         */
        currentYear: function () {
            return new Date().getFullYear();
        },

        /**
         * @param {number | string | null | undefined} value
         * @param {number} [defaultValue]
         * @returns {number}
         */
        toFiniteOr: function (value, defaultValue) {
            const d = defaultValue === undefined ? 0 : defaultValue;
            const n = Number(value);
            return Number.isFinite(n) ? n : d;
        },
    };
})(typeof window !== 'undefined' ? window : this);
