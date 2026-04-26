/**
 * Assessor evaluate: integer scores, weighted total, grade band, interactive weight donut, save UX.
 */
(function () {
    'use strict';

    var CENTER_L1 = 'Weights';
    var CENTER_L2 = '100%';

    function parseNumInput(el) {
        var v = Math.round(
            parseInt(String(el && el.value).replace(/[^\d\-]/g, ''), 10)
        );
        if (Number.isNaN(v)) {
            return 0;
        }
        return Math.min(100, Math.max(0, v));
    }

    function formatWeighted(weighted, maxW) {
        var mx = Math.max(0, Number(maxW) || 0);
        var w = Math.min(mx, Math.max(0, weighted));
        return { calc: Number(w).toFixed(2), max: Number(mx).toFixed(2) };
    }

    function setBadge(badge, total) {
        if (!badge) {
            return;
        }
        badge.classList.remove(
            'eval-grade-badge--fail',
            'eval-grade-badge--pass',
            'eval-grade-badge--merit',
            'eval-grade-badge--distinction',
            'eval-grade-badge--distinction-glow'
        );
        var label;
        var data;
        if (total < 40) {
            label = 'Fail';
            data = 'fail';
            badge.classList.add('eval-grade-badge--fail');
        } else if (total < 60) {
            label = 'Pass';
            data = 'pass';
            badge.classList.add('eval-grade-badge--pass');
        } else if (total < 70) {
            label = 'Merit';
            data = 'merit';
            badge.classList.add('eval-grade-badge--merit');
        } else {
            label = 'Distinction';
            data = 'distinction';
            badge.classList.add('eval-grade-badge--distinction', 'eval-grade-badge--distinction-glow');
        }
        badge.textContent = label;
        badge.setAttribute('data-grade', data);
    }

    function popTotal() {
        var el = document.querySelector('.live-total-mark__value');
        if (!el) {
            return;
        }
        el.classList.remove('live-total-mark__value--pop');
        void el.offsetWidth;
        el.classList.add('live-total-mark__value--pop');
    }

    function setCommentsState(total) {
        var inLow = total < 40;
        var inHigh = total >= 70;
        var comments = document.getElementById('comments');

        if (comments) {
            if (inLow || inHigh) {
                comments.classList.add('eval-comments--flagged');
            } else {
                comments.classList.remove('eval-comments--flagged');
            }
        }
    }

    function update() {
        var rows = document.querySelectorAll('[data-eval-criterion]');
        var total = 0;
        rows.forEach(function (row) {
            var w = parseFloat(row.getAttribute('data-weight') || '0');
            var maxW = parseFloat(row.getAttribute('data-max-weighted') || '0');
            var num = row.querySelector('.massive-score-input');
            var cEl = row.querySelector('.calc-score');
            var mEl = row.querySelector('.max-score');
            if (!num) {
                return;
            }
            var raw = parseNumInput(num);
            var weighted = raw * w;
            total += weighted;
            var parts = formatWeighted(weighted, maxW);
            if (cEl) {
                cEl.textContent = (Number.parseFloat(String(parts.calc)) || 0).toFixed(2);
            }
            if (mEl) {
                mEl.textContent = (Number.parseFloat(String(parts.max)) || 0).toFixed(2);
            }
        });

        var totalEl = document.querySelector('.live-total-mark__value');
        if (totalEl) {
            var next = total.toFixed(2);
            if (totalEl.textContent !== next) {
                totalEl.textContent = next;
                popTotal();
            } else {
                totalEl.textContent = next;
            }
        }

        var badge = document.querySelector('[data-grade-badge]');
        setBadge(badge, total);
        setCommentsState(total);
    }

    function wireRow(row) {
        var num = row.querySelector('.massive-score-input');
        if (!num) {
            return;
        }
        num.addEventListener('input', function () {
            var v = parseNumInput(num);
            num.value = String(v);
            update();
        });
        num.addEventListener('change', function () {
            var v = parseNumInput(num);
            num.value = String(v);
            update();
        });
    }

    function setCenterPieText(line1, line2) {
        var t1 = document.getElementById('eval-pie-center-l1');
        var t2 = document.getElementById('eval-pie-center-l2');
        if (t1) {
            t1.textContent = line1;
        }
        if (t2) {
            t2.textContent = line2;
        }
    }

    function initWeightPieInteraction() {
        var root = document.querySelector('.eval-pie--interactive');
        if (!root) {
            return;
        }
        var sectors = root.querySelectorAll('.eval-pie-sector');
        sectors.forEach(function (g) {
            g.addEventListener('mouseenter', function () {
                g.classList.add('eval-pie-sector--hover');
                g.style.transform = 'scale(1.05)';
                var lab = g.getAttribute('data-label') || '';
                var pct = g.getAttribute('data-pct') || '0';
                setCenterPieText(lab, pct + '%');
            });
            g.addEventListener('mouseleave', function () {
                g.classList.remove('eval-pie-sector--hover');
                g.style.transform = 'scale(1)';
                setCenterPieText(CENTER_L1, CENTER_L2);
            });
        });
    }

    function resetSaveButton() {
        var btn = document.getElementById('eval-save-btn');
        if (!btn) {
            return;
        }
        btn.classList.remove('eval-save-btn--loading');
        btn.disabled = false;
        var idle = btn.querySelector('.eval-save-btn__face--idle');
        var busy = btn.querySelector('.eval-save-btn__face--busy');
        if (idle) {
            idle.removeAttribute('hidden');
        }
        if (busy) {
            busy.setAttribute('hidden', '');
        }
    }

    function initSaveButton() {
        var form = document.getElementById('assessor-evaluate-form');
        var btn = document.getElementById('eval-save-btn');
        if (!form || !btn) {
            return;
        }
        form.addEventListener('submit', function () {
            if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
                return;
            }
            btn.classList.add('eval-save-btn--loading');
            btn.disabled = true;
            var idle = btn.querySelector('.eval-save-btn__face--idle');
            var busy = btn.querySelector('.eval-save-btn__face--busy');
            if (idle) {
                idle.setAttribute('hidden', '');
            }
            if (busy) {
                busy.removeAttribute('hidden');
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        resetSaveButton();
        var root = document.getElementById('assessor-evaluate-root');
        if (!root) {
            return;
        }

        document.querySelectorAll('[data-eval-criterion]').forEach(wireRow);
        update();
        window.requestAnimationFrame(function () {
            update();
        });
        initWeightPieInteraction();
        initSaveButton();
    });

    window.addEventListener('pageshow', function (e) {
        if (e.persisted) {
            resetSaveButton();
        }
    });
})();
