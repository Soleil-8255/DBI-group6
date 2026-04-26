/**
 * Student dashboard: placement selector + tabs + IrmsStudentRadar Reveal.
 */
(function () {
    'use strict';

    var R = window.IrmsStudentRadar;
    if (!R) {
        return;
    }

    var root;
    var placements;
    var current = null;
    var canvas;
    var revealBtn;
    var canvasWrap;
    var exportBtn;
    var selectEl;
    var noRadarHint;
    var revealCta;
    var tabButtons;
    var tabPanels;
    var tableBody;
    var feedbackBlock;

    function $(sel, c) {
        return (c || document).querySelector(sel);
    }

    function $all(sel, c) {
        return [].slice.call((c || document).querySelectorAll(sel));
    }

    function esc(s) {
        if (s == null) {
            return '';
        }
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function findPlacement(id) {
        for (var i = 0; i < placements.length; i += 1) {
            if (String(placements[i].internship_id) === String(id)) {
                return placements[i];
            }
        }
        return null;
    }

    function setTab(id) {
        var i2;
        for (i2 = 0; i2 < tabButtons.length; i2 += 1) {
            var b = tabButtons[i2];
            var isSel = b.getAttribute('data-tab') === id;
            b.setAttribute('aria-selected', isSel ? 'true' : 'false');
        }
        for (i2 = 0; i2 < tabPanels.length; i2 += 1) {
            var p = tabPanels[i2];
            var match = p.getAttribute('data-tab-panel') === id;
            p.hidden = !match;
        }
    }

    function formatMark(v) {
        if (v == null || v === '') {
            return '—';
        }
        var n = Number(v);
        if (!Number.isFinite(n)) {
            return '—';
        }
        return n.toFixed(2);
    }

    function gradeClass(g) {
        if (g === 'Distinction') {
            return 'student-grade-badge--distinction';
        }
        if (g === 'Merit') {
            return 'student-grade-badge--merit';
        }
        if (g === 'Pass') {
            return 'student-grade-badge--pass';
        }
        if (g === 'Fail') {
            return 'student-grade-badge--fail';
        }
        return 'student-grade-badge--neutral';
    }

    function gradeFromTotal(n) {
        if (!Number.isFinite(n)) {
            return '';
        }
        if (n < 40) {
            return 'Fail';
        }
        if (n < 60) {
            return 'Pass';
        }
        if (n < 70) {
            return 'Merit';
        }
        return 'Distinction';
    }

    function renderScoreTable(p) {
        if (!tableBody) {
            return;
        }
        if (!p || !p.can_radar) {
            tableBody.innerHTML = '<tr><td colspan="3" class="student-dashboard-hint">No final component scores for this placement.</td></tr>';
            return;
        }
        var rows = p.scores_detail || [];
        var body = rows
            .map(function (r) {
                return (
                    '<tr><td>' +
                    esc(r.label) +
                    '</td><td>' +
                    esc(r.weight) +
                    '</td><td class="num">' +
                    formatMark(r.mark) +
                    '</td></tr>'
                );
            })
            .join('');
        tableBody.innerHTML = body;
    }

    function renderHeroInternship(p) {
        var wrap = document.getElementById('student-hero-internship');
        var empty = document.getElementById('student-hero-internship-empty');
        if (!wrap || !empty) {
            return;
        }
        if (!p) {
            wrap.setAttribute('hidden', '');
            wrap.innerHTML = '';
            empty.removeAttribute('hidden');
            return;
        }
        empty.setAttribute('hidden', '');
        wrap.removeAttribute('hidden');
        var ref = 'INT-' + p.internship_id;
        wrap.innerHTML =
            '<ul class="meta-grid" role="list">' +
            '<li class="meta-grid__item"><div class="meta-grid__text"><span class="meta-grid__label">Company</span><span class="meta-grid__value">' +
            esc(p.company_name) +
            '</span></div></li>' +
            '<li class="meta-grid__item"><div class="meta-grid__text"><span class="meta-grid__label">Assessor</span><span class="meta-grid__value">' +
            esc(p.assessor_name) +
            '</span></div></li>' +
            '<li class="meta-grid__item"><div class="meta-grid__text"><span class="meta-grid__label">Period</span><span class="meta-grid__value">' +
            esc(p.period_caption) +
            '</span></div></li>' +
            '</ul>' +
            '<p class="meta-sub"><span class="meta-sub__ref">' +
            esc(ref) +
            '</span> · <span class="meta-sub__status">' +
            esc(p.status) +
            '</span> · <span class="meta-sub__state">' +
            esc(p.state) +
            '</span></p>';
    }

    function renderHeroOutcome(p) {
        var root = document.getElementById('student-hero-outcome-root');
        if (!root) {
            return;
        }
        if (!p) {
            root.innerHTML =
                '<div class="hero-outcome hero-outcome--pending" role="status">' +
                '<p class="hero-outcome__pending-title">No placement on record</p>' +
                '<p class="hero-outcome__pending-hint">Add an internship in the registry to see outcomes here.</p></div>';
            return;
        }
        var tm = p.total_mark;
        if (tm == null) {
            root.innerHTML =
                '<div class="hero-outcome hero-outcome--pending" role="status">' +
                '<p class="hero-outcome__pending-title">Under evaluation</p>' +
                '<p class="hero-outcome__pending-hint">Your total and classification will show here once published.</p></div>';
            return;
        }
        var g = p.grade;
        if (!g) {
            g = gradeFromTotal(Number(tm));
        }
        var gCls = gradeClass(g);
        root.innerHTML =
            '<div class="hero-outcome hero-outcome--scored">' +
            '<p class="hero-outcome__label">Total score</p>' +
            '<p class="hero-outcome__value" aria-label="Total score out of 100"><span class="hero-outcome__value-num">' +
            formatMark(tm) +
            '</span><span class="hero-outcome__value-suffix" aria-hidden="true">/100</span></p>' +
            '<p class="student-grade-badge ' +
            esc(gCls) +
            '">' +
            esc(g) +
            '</p></div>';
    }

    function renderFeedback(p) {
        if (!feedbackBlock) {
            return;
        }
        if (!p || !p.can_radar) {
            feedbackBlock.innerHTML = '<p class="student-dashboard-hint">No published assessment or final total for this placement yet.</p>';
            return;
        }
        var comments = p.comments != null && String(p.comments).trim() !== '' ? p.comments : '—';
        feedbackBlock.innerHTML =
            '<div class="student-feedback-prose" role="region" aria-label="Assessor comments">' +
            esc(comments) +
            '</div>';
    }

    function resetRadarUI() {
        if (!revealBtn || !canvas) {
            return;
        }
        revealBtn.removeAttribute('hidden');
        if (exportBtn) {
            exportBtn.setAttribute('hidden', '');
        }
        if (canvasWrap) {
            canvasWrap.setAttribute('hidden', '');
        }
        R.resetCanvas(canvas);
    }

    function onRevealClick() {
        if (!current || !current.can_radar || !current.scores) {
            return;
        }
        revealBtn.setAttribute('hidden', '');
        if (canvasWrap) {
            canvasWrap.removeAttribute('hidden');
        }
        R.animate(canvas, current.scores, function () {
            if (exportBtn) {
                exportBtn.removeAttribute('hidden');
            }
            if (canvasWrap) {
                canvasWrap.classList.add('radar-canvas--done');
                setTimeout(function () {
                    canvasWrap.classList.remove('radar-canvas--done');
                }, 1000);
            }
        });
    }

    function applyPlacement() {
        if (!root) {
            return;
        }
        if (selectEl && selectEl.value) {
            var np = findPlacement(selectEl.value);
            if (np) {
                current = np;
            }
        } else if (!current && placements[0]) {
            current = placements[0];
        }
        if (!current) {
            return;
        }
        renderHeroInternship(current);
        renderHeroOutcome(current);
        if (noRadarHint && current) {
            noRadarHint.hidden = !!current.can_radar;
        }
        if (revealCta && current) {
            revealCta.hidden = !current.can_radar;
        }
        renderScoreTable(current);
        renderFeedback(current);
        if (revealBtn) {
            if (current && current.can_radar) {
                revealBtn.removeAttribute('disabled');
            } else {
                revealBtn.setAttribute('disabled', 'disabled');
            }
        }
        resetRadarUI();
    }

    function init() {
        root = document.getElementById('student-dashboard-root');
        if (!root) {
            return;
        }
        var raw = root.getAttribute('data-student-placements') || '[]';
        try {
            placements = JSON.parse(raw);
        } catch (e) {
            placements = [];
        }
        if (!Array.isArray(placements) || placements.length === 0) {
            return;
        }
        tabButtons = $all('[data-tab]', root);
        tabPanels = $all('.tab-pane', root);
        tableBody = $('#student-detail-tbody', root);
        feedbackBlock = $('#student-feedback-block', root);
        canvas = document.getElementById('student-radar-canvas');
        revealBtn = document.getElementById('radar-reveal-btn');
        canvasWrap = document.getElementById('student-radar-canvas-wrap');
        exportBtn = document.getElementById('radar-export-btn');
        selectEl = document.getElementById('student-placement-select');
        noRadarHint = document.getElementById('radar-no-data-hint');
        revealCta = document.querySelector('.radar-reveal-block');

        if (selectEl && selectEl.value) {
            current = findPlacement(selectEl.value) || placements[0];
        } else {
            current = placements[0];
        }
        if (selectEl) {
            selectEl.addEventListener('change', function () {
                current = findPlacement(selectEl.value) || placements[0];
                applyPlacement();
            });
        }

        var ti;
        for (ti = 0; ti < tabButtons.length; ti += 1) {
            tabButtons[ti].addEventListener('click', function (ev) {
                var id = ev.currentTarget.getAttribute('data-tab');
                if (id) {
                    setTab(id);
                }
            });
        }
        if (tabButtons[0]) {
            setTab(tabButtons[0].getAttribute('data-tab') || 'overview');
        }
        if (revealBtn) {
            revealBtn.addEventListener('click', onRevealClick);
        }
        applyPlacement();
        if (exportBtn) {
            exportBtn.addEventListener('click', function () {
                try {
                    if (!canvas) {
                        return;
                    }
                    var link = document.createElement('a');
                    var id2 = current ? current.internship_id : 'export';
                    link.href = canvas.toDataURL('image/png');
                    link.download = 'student_radar_' + String(id2) + '.png';
                    link.click();
                } catch (e) {
                    /* no-op */
                }
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
