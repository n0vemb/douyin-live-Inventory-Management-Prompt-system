/*!
 * date-range-picker.js — 轻量日期范围选择器（酒店入住式：先点开始日，再点结束日）
 * 无依赖，样式内置，跟随主题变量。
 *
 * 用法：
 *   var drp = DateRangePicker.attach({
 *     input: '#dateRangeInput',                 // 只读文本框，显示已选区间
 *     onChange: function (start, end) { ... }   // 'YYYY-MM-DD'，未选时为空串
 *   });
 *   drp.clear();                                  // 清空（触发 onChange('','')）
 *   drp.setRange('2026-09-01', '2026-09-30');     // 设置区间（触发 onChange）
 *   drp.sync(start, end);                         // 只同步显示，不触发回调
 */
(function (global) {
    'use strict';
    if (global.DateRangePicker) return;

    var WEEK_CN = ['一', '二', '三', '四', '五', '六', '日'];
    var STYLE_ID = 'drp-style';
    var DAY = 86400000;

    function pad2(n) { return (n < 10 ? '0' : '') + n; }
    function ymd(dt) { return dt.getFullYear() + '-' + pad2(dt.getMonth() + 1) + '-' + pad2(dt.getDate()); }
    function mdCn(dt) { return pad2(dt.getMonth() + 1) + '月' + pad2(dt.getDate()) + '日'; }
    function parseYmd(s) {
        var m = /^(\d{4})-(\d{1,2})-(\d{1,2})/.exec(String(s || ''));
        if (!m) return null;
        var dt = new Date(+m[1], +m[2] - 1, +m[3]);
        return isNaN(dt.getTime()) ? null : dt;
    }
    function midnight(dt) { return new Date(dt.getFullYear(), dt.getMonth(), dt.getDate()); }
    function addDays(dt, n) { return new Date(dt.getFullYear(), dt.getMonth(), dt.getDate() + n); }
    function monthStart(dt) { return new Date(dt.getFullYear(), dt.getMonth(), 1); }
    function addMonths(dt, n) { return new Date(dt.getFullYear(), dt.getMonth() + n, 1); }
    function sameDay(a, b) { return !!a && !!b && a.getTime() === b.getTime(); }
    function daysBetween(a, b) { return Math.round((midnight(b).getTime() - midnight(a).getTime()) / DAY); }
    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    var CSS = ''
        + '.drp-panel{position:fixed;z-index:3000;display:none;padding:12px;border-radius:12px;'
        + 'background:var(--bg-surface,#fff);border:1px solid var(--border,#e5e7eb);'
        + 'box-shadow:0 14px 36px rgba(0,0,0,.18);color:var(--text,#111827);font-size:13px;}'
        + '.drp-panel.show{display:flex;gap:12px;}'
        + '.drp-quick{display:flex;flex-direction:column;gap:4px;padding-right:12px;'
        + 'border-right:1px dashed var(--border,#e5e7eb);min-width:78px;}'
        + '.drp-quick button{border:0;background:transparent;color:var(--text-secondary,#6b7280);'
        + 'text-align:left;padding:6px 8px;border-radius:6px;cursor:pointer;font-size:13px;white-space:nowrap;}'
        + '.drp-quick button:hover{background:var(--bg-hover,#f3f4f6);color:var(--primary,#6366f1);}'
        + '.drp-main{display:flex;flex-direction:column;gap:6px;}'
        + '.drp-cals{display:flex;gap:14px;}'
        + '.drp-cal{min-width:224px;}'
        + '.drp-title{display:flex;align-items:center;justify-content:space-between;padding:2px 0 6px;}'
        + '.drp-title b{font-weight:600;font-size:13px;}'
        + '.drp-nav{border:1px solid var(--border,#e5e7eb);background:var(--bg-surface,#fff);'
        + 'color:var(--text-secondary,#6b7280);width:26px;height:26px;line-height:1;border-radius:8px;cursor:pointer;font-size:15px;}'
        + '.drp-nav:hover{border-color:var(--primary,#6366f1);color:var(--primary,#6366f1);}'
        + '.drp-nav.ph{visibility:hidden;}'
        + '.drp-grid{display:grid;grid-template-columns:repeat(7,32px);gap:2px;}'
        + '.drp-wd{height:24px;display:flex;align-items:center;justify-content:center;'
        + 'font-size:11px;color:var(--text-tertiary,#9ca3af);}'
        + '.drp-day{height:30px;display:flex;align-items:center;justify-content:center;border-radius:8px;'
        + 'cursor:pointer;font-variant-numeric:tabular-nums;}'
        + '.drp-day:hover{background:var(--bg-hover,#f3f4f6);}'
        + '.drp-day.out{color:var(--text-tertiary,#9ca3af);}'
        + '.drp-day.inrange{background:rgba(99,102,241,.14);border-radius:0;}'
        + '.drp-day.edge{background:var(--primary,#6366f1);color:#fff;font-weight:600;}'
        + '.drp-day.edge:hover{background:var(--primary,#6366f1);}'
        + '.drp-foot{display:flex;align-items:center;justify-content:space-between;gap:10px;'
        + 'padding-top:8px;border-top:1px dashed var(--border,#e5e7eb);}'
        + '.drp-hint{color:var(--text-tertiary,#9ca3af);font-size:12px;}'
        + '.drp-acts{display:flex;gap:8px;}'
        + '.drp-btn{border:1px solid var(--border,#e5e7eb);background:var(--bg-surface,#fff);'
        + 'color:var(--text-secondary,#6b7280);padding:5px 12px;border-radius:8px;cursor:pointer;font-size:12.5px;}'
        + '.drp-btn.pri{background:var(--primary,#6366f1);border-color:var(--primary,#6366f1);color:#fff;}'
        + '.drp-btn:disabled{opacity:.5;cursor:not-allowed;}'
        + '@media (max-width:900px){.drp-cals .drp-cal:nth-child(2){display:none;}'
        + '.drp-cal{min-width:212px;}.drp-grid{grid-template-columns:repeat(7,30px);}}';

    function ensureStyle() {
        if (document.getElementById(STYLE_ID)) return;
        var s = document.createElement('style');
        s.id = STYLE_ID;
        s.textContent = CSS;
        document.head.appendChild(s);
    }

    // 当前用于高亮的区间：选择中优先，其次已应用区间
    function rangeOf(ctx) {
        if (ctx.pickStart) {
            if (ctx.pickEnd) return { from: ctx.pickStart, to: ctx.pickEnd };
            if (ctx.hover && ctx.hover >= ctx.pickStart) return { from: ctx.pickStart, to: ctx.hover };
            return { from: ctx.pickStart, to: null };
        }
        if (ctx.start && ctx.end) return { from: ctx.start, to: ctx.end };
        return null;
    }

    function buildCal(month, ctx) {
        var y = month.getFullYear(), mo = month.getMonth();
        var first = new Date(y, mo, 1);
        var lead = (first.getDay() + 6) % 7; // 周一为一周起点
        var gridStart = addDays(first, -lead);
        var sel = rangeOf(ctx);
        var html = '<div class="drp-grid">';
        WEEK_CN.forEach(function (w) { html += '<div class="drp-wd">' + w + '</div>'; });
        for (var i = 0; i < 42; i++) {
            var cur = addDays(gridStart, i);
            var cls = 'drp-day';
            if (cur.getMonth() !== mo) cls += ' out';
            if (sel && sel.from) {
                if (sameDay(cur, sel.from) || (sel.to && sameDay(cur, sel.to))) cls += ' edge';
                else if (sel.to && cur > sel.from && cur < sel.to) cls += ' inrange';
            }
            html += '<div class="' + cls + '" data-day="' + ymd(cur) + '">' + cur.getDate() + '</div>';
        }
        return html + '</div>';
    }

    function attach(opts) {
        opts = opts || {};
        var input = typeof opts.input === 'string' ? document.querySelector(opts.input) : opts.input;
        if (!input) return null;
        ensureStyle();

        var ctx = {
            start: null, end: null,          // 已应用区间
            pickStart: null, pickEnd: null,  // 选择中的区间
            hover: null,
            phase: 'start',
            view: monthStart(new Date())
        };

        var panel = document.createElement('div');
        panel.className = 'drp-panel';
        document.body.appendChild(panel);

        function rangeText() {
            if (ctx.start && ctx.end && !sameDay(ctx.start, ctx.end)) return ymd(ctx.start) + ' ~ ' + ymd(ctx.end);
            if (ctx.start) return ymd(ctx.start);
            return '';
        }

        function hintText() {
            if (ctx.pickStart && ctx.pickEnd) {
                return '已选 ' + mdCn(ctx.pickStart) + ' ~ ' + mdCn(ctx.pickEnd) + '（' + (daysBetween(ctx.pickStart, ctx.pickEnd) + 1) + ' 天）';
            }
            if (ctx.pickStart) return '已选 ' + mdCn(ctx.pickStart) + '，请选择结束日期';
            return '先点开始日期，再点结束日期';
        }

        function position() {
            var r = input.getBoundingClientRect();
            panel.style.top = (r.bottom + 6) + 'px';
            panel.style.left = r.left + 'px';
            var w = panel.offsetWidth || 560;
            var maxLeft = window.innerWidth - w - 12;
            if (r.left > maxLeft) panel.style.left = Math.max(12, maxLeft) + 'px';
            var h = panel.offsetHeight || 380;
            if (r.bottom + 6 + h > window.innerHeight && r.top - h - 6 > 0) {
                panel.style.top = (r.top - h - 6) + 'px';
            }
        }

        function render() {
            var m1 = ctx.view, m2 = addMonths(ctx.view, 1);
            panel.innerHTML = ''
                + '<div class="drp-quick">'
                + '<button type="button" data-q="today">今天</button>'
                + '<button type="button" data-q="yesterday">昨天</button>'
                + '<button type="button" data-q="d7">近 7 天</button>'
                + '<button type="button" data-q="d30">近 30 天</button>'
                + '<button type="button" data-q="month">本月</button>'
                + '<button type="button" data-q="lastmonth">上月</button>'
                + '</div>'
                + '<div class="drp-main">'
                + '<div class="drp-cals">'
                + '<div class="drp-cal">'
                + '<div class="drp-title"><button type="button" class="drp-nav" data-nav="-1" title="上一月">‹</button>'
                + '<b>' + m1.getFullYear() + '年' + (m1.getMonth() + 1) + '月</b>'
                + '<span class="drp-nav ph"></span></div>'
                + buildCal(m1, ctx)
                + '</div>'
                + '<div class="drp-cal">'
                + '<div class="drp-title"><span class="drp-nav ph"></span>'
                + '<b>' + m2.getFullYear() + '年' + (m2.getMonth() + 1) + '月</b>'
                + '<button type="button" class="drp-nav" data-nav="1" title="下一月">›</button></div>'
                + buildCal(m2, ctx)
                + '</div>'
                + '</div>'
                + '<div class="drp-foot"><span class="drp-hint">' + esc(hintText()) + '</span>'
                + '<span class="drp-acts">'
                + '<button type="button" class="drp-btn" data-act="clear">清除</button>'
                + '<button type="button" class="drp-btn pri" data-act="done"' + (ctx.pickStart ? '' : ' disabled') + '>确定</button>'
                + '</span></div>'
                + '</div>';
            position();
        }

        function emit() {
            input.value = rangeText();
            if (typeof opts.onChange === 'function') {
                opts.onChange(ctx.start ? ymd(ctx.start) : '', ctx.end ? ymd(ctx.end) : '');
            }
        }

        function apply() {
            if (!ctx.pickStart) return;
            ctx.start = ctx.pickStart;
            ctx.end = ctx.pickEnd || ctx.pickStart;
            close();
            emit();
        }

        function open() {
            // 以当前显示值（或外部同步进来的值）作为初始区间
            if (!ctx.start && input.value) {
                var parts = String(input.value).split('~');
                ctx.start = parseYmd(parts[0]);
                ctx.end = parts[1] ? parseYmd(parts[1]) : ctx.start;
            }
            if (!ctx.start && input.dataset.start) ctx.start = parseYmd(input.dataset.start);
            if (!ctx.end && input.dataset.end) ctx.end = parseYmd(input.dataset.end);
            ctx.pickStart = ctx.start;
            ctx.pickEnd = ctx.end;
            ctx.hover = null;
            ctx.phase = 'start';
            ctx.view = monthStart(ctx.start || new Date());
            panel.classList.add('show');
            render();
        }

        function close() {
            panel.classList.remove('show');
            ctx.pickStart = ctx.pickEnd = ctx.hover = null;
            ctx.phase = 'start';
        }

        function isOpen() { return panel.classList.contains('show'); }

        function quickRange(kind) {
            var t = midnight(new Date());
            if (kind === 'today') return { s: t, e: t };
            if (kind === 'yesterday') { var y = addDays(t, -1); return { s: y, e: y }; }
            if (kind === 'd7') return { s: addDays(t, -6), e: t };
            if (kind === 'd30') return { s: addDays(t, -29), e: t };
            if (kind === 'month') return { s: new Date(t.getFullYear(), t.getMonth(), 1), e: t };
            if (kind === 'lastmonth') {
                return { s: new Date(t.getFullYear(), t.getMonth() - 1, 1), e: new Date(t.getFullYear(), t.getMonth(), 0) };
            }
            return null;
        }

        panel.addEventListener('click', function (ev) {
            var el = ev.target.closest ? ev.target.closest('[data-day],[data-q],[data-nav],[data-act]') : null;
            if (!el) return;
            ev.stopPropagation();
            if (el.dataset.day) {
                var day = parseYmd(el.dataset.day);
                if (!day) return;
                if (ctx.phase === 'start' || !ctx.pickStart) {
                    ctx.pickStart = day; ctx.pickEnd = null; ctx.hover = null; ctx.phase = 'end';
                } else if (day < ctx.pickStart) {
                    ctx.pickStart = day; ctx.pickEnd = null; ctx.phase = 'end';
                } else {
                    ctx.pickEnd = day;
                    apply();
                    return;
                }
                render();
                return;
            }
            if (el.dataset.nav) {
                ctx.view = addMonths(ctx.view, parseInt(el.dataset.nav, 10));
                render();
                return;
            }
            if (el.dataset.q) {
                var r = quickRange(el.dataset.q);
                if (!r) return;
                ctx.pickStart = r.s; ctx.pickEnd = r.e;
                apply();
                return;
            }
            if (el.dataset.act === 'clear') {
                ctx.start = ctx.end = ctx.pickStart = ctx.pickEnd = null;
                input.value = '';
                close();
                emit();
                return;
            }
            if (el.dataset.act === 'done') apply();
        });

        // 悬停预览：选好开始日后，鼠标划过即预览区间（酒店式体验）
        panel.addEventListener('mouseover', function (ev) {
            var el = ev.target.closest ? ev.target.closest('[data-day]') : null;
            if (!el) return;
            var day = parseYmd(el.dataset.day);
            if (!day || ctx.phase !== 'end' || !ctx.pickStart || ctx.pickEnd) return;
            if (ctx.hover && sameDay(ctx.hover, day)) return;
            ctx.hover = day;
            render();
        });

        input.setAttribute('readonly', 'readonly');
        input.style.cursor = 'pointer';
        input.addEventListener('click', function () { if (isOpen()) close(); else open(); });
        input.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); if (isOpen()) close(); else open(); }
        });

        document.addEventListener('click', function (ev) {
            if (!isOpen()) return;
            if (panel.contains(ev.target) || ev.target === input) return;
            close();
        });
        document.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape' && isOpen()) close();
        });
        window.addEventListener('resize', function () { if (isOpen()) position(); });
        window.addEventListener('scroll', function () { if (isOpen()) position(); }, true);

        return {
            open: open,
            close: close,
            isOpen: isOpen,
            clear: function () {
                ctx.start = ctx.end = ctx.pickStart = ctx.pickEnd = null;
                input.value = '';
                close();
                emit();
            },
            setRange: function (start, end) {
                ctx.start = parseYmd(start);
                ctx.end = parseYmd(end || start);
                emit();
            },
            // 与隐藏字段保持同步显示（重置筛选时调用，不触发回调）
            sync: function (start, end) {
                ctx.start = parseYmd(start);
                ctx.end = parseYmd(end || start);
                input.value = rangeText();
            },
            text: rangeText
        };
    }

    global.DateRangePicker = { attach: attach };
})(window);
