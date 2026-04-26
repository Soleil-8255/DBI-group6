/**
 * Student performance radar (Canvas) — HiDPI, Nott blue + champagne, staggered reveal.
 * Exposes window.IrmsStudentRadar. Used by student-dashboard.js after Reveal.
 */
(function (global) {
    'use strict';

    var NOTT = '#122a54';
    var NOTT_FILL = 'rgba(18, 42, 84, 0.16)';
    var NOTT_FILL2 = 'rgba(30, 58, 138, 0.08)';
    var CHAMP = '#c8a86b';
    var GRID = '#c7d1dd';
    var AXIS = '#94a3b8';
    var LABEL = '#0f172a';
    var LABEL_MUTED = '#475569';
    var LOGICAL = 560;
    var ANIM_MS = 1320;
    var STAGGER = 0.1;

    var LABELS = [
        'Tasks',
        'Safety',
        'Theory',
        'Report',
        'Language',
        'Lifelong',
        'Proj Mgmt',
        'Time Mgmt',
    ];

    var levels = 5;
    var startAngle = -Math.PI / 2;
    var rafId = null;

    function polarPoint(cx, cy, radius, angleRad) {
        return {
            x: cx + radius * Math.cos(angleRad),
            y: cy + radius * Math.sin(angleRad),
        };
    }

    function easeOutCubic(t) {
        return 1 - Math.pow(1 - t, 3);
    }

    function easeOutBack(t) {
        var c1 = 1.2;
        var c3 = c1 + 1;
        return 1 + c3 * Math.pow(t - 1, 3) + c1 * Math.pow(t - 1, 2);
    }

    function mixEase(t) {
        var u;
        if (t < 0.75) {
            u = easeOutCubic(t / 0.75) * 0.88;
        } else {
            var m = (t - 0.75) / 0.25;
            u = 0.88 + easeOutBack(m) * 0.12;
        }
        return Math.max(0, Math.min(1, u));
    }

    function clampScore(v) {
        var n = Number(v);
        if (!Number.isFinite(n)) {
            return 0;
        }
        return Math.max(0, Math.min(100, n));
    }

    function findMaxIndex(scores) {
        var m = 0;
        for (var i = 1; i < scores.length; i += 1) {
            if (Number(scores[i]) > Number(scores[m])) {
                m = i;
            }
        }
        return m;
    }

    /**
     * @returns {number} logical size (e.g. 560)
     */
    function syncHiDpi(canvas) {
        if (!canvas) {
            return LOGICAL;
        }
        var dpr = Math.min(global.devicePixelRatio || 1, 2.5);
        if (canvas._irmsDpr === dpr && canvas._irmsL === LOGICAL) {
            return LOGICAL;
        }
        canvas._irmsDpr = dpr;
        canvas._irmsL = LOGICAL;
        var w = Math.floor(LOGICAL * dpr);
        var h = Math.floor(LOGICAL * dpr);
        canvas.width = w;
        canvas.height = h;
        canvas.style.width = LOGICAL + 'px';
        canvas.style.height = LOGICAL + 'px';
        var ctx = canvas.getContext('2d');
        if (ctx) {
            ctx.setTransform(1, 0, 0, 1, 0, 0);
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        }
        return LOGICAL;
    }

    function vertexEasedT(globalT, k, n) {
        var maxShift = (n - 1) * STAGGER;
        var span = 1 + maxShift;
        var rel = globalT * span - k * STAGGER;
        if (rel < 0) {
            return 0;
        }
        if (rel > 1) {
            return 1;
        }
        return mixEase(rel);
    }

    function drawLabelPill(ctx, x, y, text) {
        var padX = 7;
        var padY = 4;
        var font = '11px system-ui, Segoe UI, sans-serif';
        ctx.font = font;
        var m = ctx.measureText(text);
        var tw = m.width;
        var th = 14;
        var rx = 6;
        var w = tw + padX * 2;
        var h = th + padY;
        var lx = x - w / 2;
        var ly = y - h / 2;
        ctx.save();
        ctx.beginPath();
        if (ctx.roundRect) {
            ctx.roundRect(lx, ly, w, h, rx);
        } else {
            ctx.rect(lx, ly, w, h);
        }
        ctx.fillStyle = 'rgba(255, 255, 255, 0.92)';
        ctx.shadowColor = 'rgba(15, 23, 42, 0.12)';
        ctx.shadowBlur = 6;
        ctx.shadowOffsetY = 1;
        ctx.fill();
        ctx.shadowColor = 'transparent';
        ctx.lineWidth = 1;
        ctx.strokeStyle = 'rgba(18, 42, 84, 0.18)';
        ctx.stroke();
        ctx.fillStyle = LABEL;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.font = '600 11px system-ui, Segoe UI, sans-serif';
        ctx.fillText(text, x, y + 0.5);
        ctx.restore();
    }

    /**
     * @param {HTMLCanvasElement} canvas
     * @param {number} t 0..1
     * @param {number[]} scores length 8
     * @param {boolean} showGoldHighlight
     */
    function drawFrame(canvas, scores, t, showGoldHighlight) {
        var L = syncHiDpi(canvas);
        var ctx = canvas.getContext('2d');
        if (!ctx) {
            return;
        }
        t = Math.max(0, Math.min(1, t));

        var width = L;
        var height = L;
        var cx = width / 2;
        var cy = height / 2;
        var maxRadius = Math.min(width, height) * 0.3;
        var n = 8;
        if (!scores || scores.length !== 8) {
            return;
        }

        ctx.clearRect(0, 0, width, height);
        var bgGrad = ctx.createRadialGradient(cx, cy, 0, cx, cy, maxRadius * 1.4);
        bgGrad.addColorStop(0, 'rgba(241, 245, 249, 0.9)');
        bgGrad.addColorStop(0.55, 'rgba(250, 251, 252, 0.95)');
        bgGrad.addColorStop(1, 'rgba(255, 255, 255, 0.2)');
        ctx.fillStyle = bgGrad;
        ctx.fillRect(0, 0, width, height);

        ctx.font = '11px system-ui, Segoe UI, sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';

        for (var lv = 1; lv <= levels; lv += 1) {
            var ratio = lv / levels;
            ctx.beginPath();
            for (var i2 = 0; i2 < n; i2 += 1) {
                var ang = startAngle + (Math.PI * 2 * i2) / n;
                var pt = polarPoint(cx, cy, maxRadius * ratio, ang);
                if (i2 === 0) {
                    ctx.moveTo(pt.x, pt.y);
                } else {
                    ctx.lineTo(pt.x, pt.y);
                }
            }
            ctx.closePath();
            ctx.strokeStyle = GRID;
            ctx.lineWidth = lv === levels ? 1.25 : 0.9;
            ctx.globalAlpha = 0.85 + 0.04 * t;
            ctx.stroke();
            ctx.globalAlpha = 1;
            var tick = String(lv * 20);
            ctx.fillStyle = LABEL_MUTED;
            ctx.font = '600 10px system-ui, sans-serif';
            ctx.textAlign = 'left';
            ctx.textBaseline = 'middle';
            ctx.fillText(tick, cx + 5, cy - maxRadius * ratio);
        }
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';

        for (var j = 0; j < n; j += 1) {
            var aAx = startAngle + (Math.PI * 2 * j) / n;
            var aEnd = polarPoint(cx, cy, maxRadius, aAx);
            ctx.beginPath();
            ctx.moveTo(cx, cy);
            ctx.lineTo(aEnd.x, aEnd.y);
            ctx.strokeStyle = AXIS;
            ctx.lineWidth = 1;
            ctx.setLineDash([3, 3]);
            ctx.stroke();
            ctx.setLineDash([]);
            var lp = polarPoint(cx, cy, maxRadius + 34, aAx);
            drawLabelPill(ctx, lp.x, lp.y, LABELS[j]);
        }

        if (t < 0.001) {
            return;
        }

        var maxIdx = findMaxIndex(scores);
        var points = [];
        for (var k = 0; k < n; k += 1) {
            var s = clampScore(scores[k]);
            var aSc = startAngle + (Math.PI * 2 * k) / n;
            var veT = vertexEasedT(t, k, n);
            var r = maxRadius * (s / 100) * veT;
            points.push(polarPoint(cx, cy, r, aSc));
        }

        if (t > 0.05) {
            var pulse = (1 + 0.04 * Math.sin(t * Math.PI * 1.1)) * (t > 0.9 ? 1 + 0.06 * (t - 0.9) * 5 : 1);
            var innerGrad = ctx.createRadialGradient(cx, cy, 0, cx, cy, maxRadius * 0.35 * t * pulse);
            innerGrad.addColorStop(0, NOTT_FILL2);
            innerGrad.addColorStop(1, 'rgba(255,255,255,0)');
            ctx.fillStyle = innerGrad;
            ctx.beginPath();
            ctx.arc(cx, cy, maxRadius * 0.4 * t, 0, Math.PI * 2);
            ctx.fill();
        }

        ctx.beginPath();
        for (var p = 0; p < points.length; p += 1) {
            if (p === 0) {
                ctx.moveTo(points[p].x, points[p].y);
            } else {
                ctx.lineTo(points[p].x, points[p].y);
            }
        }
        ctx.closePath();
        ctx.lineJoin = 'round';
        ctx.lineCap = 'round';
        ctx.fillStyle = NOTT_FILL;
        ctx.fill();
        ctx.shadowColor = 'rgba(18, 42, 84, 0.25)';
        ctx.shadowBlur = 12 * t;
        ctx.shadowOffsetY = 2;
        ctx.strokeStyle = NOTT;
        ctx.lineWidth = 2.25;
        ctx.stroke();
        ctx.shadowColor = 'transparent';
        ctx.shadowBlur = 0;
        ctx.shadowOffsetY = 0;

        if (showGoldHighlight && t >= 0.99) {
            for (var e = 0; e < n; e += 1) {
                var iNext = (e + 1) % n;
                if (e === maxIdx || iNext === maxIdx) {
                    ctx.beginPath();
                    ctx.moveTo(points[e].x, points[e].y);
                    ctx.lineTo(points[iNext].x, points[iNext].y);
                    ctx.strokeStyle = CHAMP;
                    ctx.lineWidth = 2.8;
                    ctx.setLineDash([]);
                    ctx.stroke();
                }
            }
            var glowR = 5 + 1.2 * Math.sin(t * 14);
            var grd = ctx.createRadialGradient(
                points[maxIdx].x,
                points[maxIdx].y,
                0,
                points[maxIdx].x,
                points[maxIdx].y,
                glowR + 3
            );
            grd.addColorStop(0, 'rgba(200, 168, 107, 0.55)');
            grd.addColorStop(0.5, 'rgba(200, 168, 107, 0.15)');
            grd.addColorStop(1, 'rgba(200, 168, 107, 0)');
            ctx.beginPath();
            ctx.arc(points[maxIdx].x, points[maxIdx].y, glowR + 3, 0, Math.PI * 2);
            ctx.fillStyle = grd;
            ctx.fill();
            ctx.beginPath();
            ctx.arc(points[maxIdx].x, points[maxIdx].y, 5, 0, Math.PI * 2);
            ctx.fillStyle = CHAMP;
            ctx.fill();
            ctx.lineWidth = 1.5;
            ctx.strokeStyle = '#7c6239';
            ctx.stroke();
        }
    }

    function cancelAnim() {
        if (rafId !== null) {
            cancelAnimationFrame(rafId);
            rafId = null;
        }
    }

    global.IrmsStudentRadar = {
        labels: LABELS,
        /** Logical size used for layout (px) */
        logicalSize: LOGICAL,

        resetCanvas: function (canvas) {
            cancelAnim();
            if (!canvas) {
                return;
            }
            var z = [0, 0, 0, 0, 0, 0, 0, 0];
            drawFrame(canvas, z, 0, false);
        },

        /**
         * @param {function(): void} [onComplete]
         */
        animate: function (canvas, scores, onComplete) {
            if (!canvas || !scores || scores.length !== 8) {
                if (typeof onComplete === 'function') {
                    onComplete();
                }
                return;
            }
            cancelAnim();
            var t0 = global.performance && global.performance.now ? global.performance.now() : Date.now();
            function frame(now) {
                var elapsed = (now - t0) / ANIM_MS;
                if (elapsed > 1) {
                    elapsed = 1;
                }
                drawFrame(canvas, scores, elapsed, true);
                if (elapsed < 1) {
                    rafId = requestAnimationFrame(frame);
                } else {
                    rafId = null;
                    if (typeof onComplete === 'function') {
                        onComplete();
                    }
                }
            }
            rafId = requestAnimationFrame(frame);
        },
    };
})(window);
