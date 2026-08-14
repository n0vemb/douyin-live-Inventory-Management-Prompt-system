<?php
$pageTitle = '待办事项';
$currentPage = 'todos';
require_once __DIR__ . '/layout.php';
?>
        <div class="page-title">待办事项</div>

        <div class="card" style="padding:14px; margin-bottom:14px;">
            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                <div class="todo-tabs" id="todoTabs">
                    <button type="button" class="todo-tab" data-f="all" onclick="setFilter('all')">全部<span class="ct" id="cAll">0</span></button>
                    <button type="button" class="todo-tab on" data-f="pending" onclick="setFilter('pending')">待完成<span class="ct" id="cPen">0</span></button>
                    <button type="button" class="todo-tab" data-f="done" onclick="setFilter('done')">已完成<span class="ct" id="cDone">0</span></button>
                </div>
                <div style="flex:1;"></div>
                <input class="form-input" id="todoQ" placeholder="搜索事项内容..." style="width:200px; max-width:100%;" oninput="onSearch()">
                <button class="btn btn-primary" onclick="toggleAdd()">加一条</button>
            </div>
        </div>

        <div id="storeHint" style="display:none; margin-bottom:12px;"></div>

        <div id="addWrap"></div>

        <div id="todoList" class="card" style="padding:0; overflow:hidden;"></div>

    </div>

    <style>
    .todo-tabs { display:flex; background:var(--bg-elevated); border:1px solid var(--border); border-radius:8px; padding:3px; gap:2px; }
    .todo-tab { border:none; background:transparent; color:var(--text-secondary); padding:7px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
    .todo-tab:hover { color:var(--text); }
    .todo-tab.on { background:var(--bg-active); color:var(--text); }
    .todo-tab .ct { font-size:11px; color:var(--text-tertiary); margin-left:5px; }
    .todo-tab.on .ct { color:var(--primary); }
    .todo-item { padding:14px 16px; border-bottom:1px solid var(--border); }
    .todo-item:last-child { border-bottom:none; }
    .todo-item.done { opacity:.72; }
    .todo-top { display:flex; align-items:flex-start; gap:10px; }
    .todo-prio { display:flex; align-items:center; gap:8px; flex-shrink:0; padding-top:2px; }
    .todo-content { flex:1; font-size:14px; line-height:1.6; word-break:break-word; min-width:0; }
    .todo-item.done .todo-content { color:var(--text-secondary); }
    .todo-badges { display:flex; align-items:center; gap:8px; flex-shrink:0; flex-wrap:wrap; justify-content:flex-end; }
    .todo-mention { color:var(--primary-hover); font-weight:700; background:rgba(94,92,230,.16); border-radius:4px; padding:0 3px; }
    .todo-bottom { display:flex; align-items:center; gap:15px; margin-top:10px; }
    .todo-info { flex:1; display:flex; align-items:center; gap:16px; flex-wrap:wrap; min-width:0; }
    .todo-meta { display:flex; gap:16px; flex-wrap:wrap; font-size:12px; color:var(--text-tertiary); }
    .todo-meta b { color:var(--text-secondary); font-weight:600; }
    .todo-acts { display:flex; gap:15px; flex-shrink:0; flex-wrap:wrap; }
    .todo-assignees { display:flex; align-items:center; gap:7px; flex-wrap:wrap; }
    .todo-assignees .al { font-size:12px; color:var(--text-tertiary); }
    .todo-atag { display:inline-flex; align-items:center; font-size:12px; font-weight:600; color:#fff; background:linear-gradient(135deg,var(--primary),#7c3aed); border-radius:20px; padding:2px 10px; }
    .todo-store-tag { font-size:11px; font-weight:600; color:var(--text-secondary); border:1px solid var(--border); border-radius:20px; padding:1px 8px; }
    .todo-complete-edit { margin-top:12px; background:var(--bg-elevated); border:1px dashed var(--border); border-radius:8px; padding:12px; }
    .todo-complete-edit label { font-size:12px; color:var(--text-tertiary); display:block; margin-bottom:6px; }
    .todo-detail-box { margin-top:10px; background:var(--bg-elevated); border-left:3px solid var(--ok, #34d399); border-radius:6px; padding:9px 11px; font-size:12.5px; color:var(--text-secondary); line-height:1.5; }
    .todo-detail-box .dh { font-size:11px; color:var(--text-tertiary); margin-bottom:3px; }
    .todo-empty { text-align:center; color:var(--text-tertiary); padding:46px 0; font-size:13.5px; }
    .todo-mention-pop { position:absolute; z-index:50; background:var(--bg-elevated); border:1px solid var(--border); border-radius:8px; box-shadow:0 12px 30px rgba(0,0,0,.45); overflow:hidden; margin-top:6px; min-width:190px; }
    .todo-mention-pop .mi { padding:8px 12px; font-size:13px; cursor:pointer; display:flex; align-items:center; gap:8px; color:var(--text); }
    .todo-mention-pop .mi:hover, .todo-mention-pop .mi.on { background:var(--bg-hover); }
    .todo-mention-pop .mi .av { width:22px; height:22px; border-radius:50%; background:linear-gradient(135deg,var(--primary),#7c3aed); color:#fff; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; }
    .todo-add-card { position:relative; }
    .todo-toast { position:fixed; bottom:24px; left:50%; transform:translateX(-50%); background:var(--text); color:var(--bg-body); padding:10px 18px; border-radius:10px; font-size:13px; opacity:0; transition:.25s; z-index:80; pointer-events:none; font-weight:600; }
    .todo-toast.show { opacity:1; }
    .store-hint-box { background:rgba(217,119,6,.12); border:1px solid rgba(217,119,6,.35); color:#fbbf24; border-radius:8px; padding:10px 14px; font-size:13px; }
    </style>

    <script>
    let items = [], members = [], currentStore = null;
    let filter = 'pending', q = '', adding = false, completeId = null;
    let assigneeSet = new Set();
    let mentionStart = -1, mentionList = [], mentionIdx = 0;

    function esc(s) {
        return (s == null ? '' : String(s)).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }
    function $(id) { return document.getElementById(id); }
    function toast(m) {
        let t = $('todoToast');
        if (!t) { t = document.createElement('div'); t.id = 'todoToast'; t.className = 'todo-toast'; document.body.appendChild(t); }
        t.textContent = m; t.classList.add('show');
        clearTimeout(t._t); t._t = setTimeout(() => t.classList.remove('show'), 1800);
    }

    async function load() {
        try {
            const res = await fetch('../api/todo_list.php');
            const data = await res.json();
            if (!data.success) { toast(data.error || '加载失败'); return; }
            items = data.items || [];
            members = data.members || [];
            currentStore = data.current_store || null;
            // 全平台视角提示（超管未选店）
            const hint = $('storeHint');
            if (currentStore) {
                hint.style.display = 'none'; hint.innerHTML = '';
            } else {
                hint.style.display = 'block';
                hint.innerHTML = '<div class="store-hint-box">当前为全平台视角，新增 / 完成 / 删除操作前请先在右上角切换到具体店铺</div>';
            }
            render();
        } catch (err) { console.error(err); toast('加载失败'); }
    }

    function setFilter(f) {
        filter = f;
        document.querySelectorAll('#todoTabs .todo-tab').forEach(b => b.classList.toggle('on', b.dataset.f === f));
        render();
    }
    function onSearch() { q = $('todoQ').value.trim(); render(); }

    function toggleAdd() {
        if (!currentStore) { toast('请先切换到具体店铺'); return; }
        adding = !adding; completeId = null; render();
    }

    function visible() {
        const kw = q.toLowerCase();
        return items.filter(it =>
            (filter === 'all' || it.status === filter) &&
            (!kw || it.content.toLowerCase().includes(kw))
        );
    }

    function renderContent(text) {
        let h = esc(text);
        [...members].sort((a, b) => b.name.length - a.name.length).forEach(m => {
            h = h.split('@' + m.name).join('<span class="todo-mention">@' + esc(m.name) + '</span>');
        });
        return h;
    }

    function assigneeNames(ids) {
        return (ids || []).map(id => {
            const m = members.find(x => x.id === id);
            return m ? m.name : null;
        }).filter(Boolean);
    }

    function render() {
        // 计数
        $('cAll').textContent = items.length;
        $('cPen').textContent = items.filter(i => i.status === 'pending').length;
        $('cDone').textContent = items.filter(i => i.status === 'done').length;

        // 加一条表单
        $('addWrap').innerHTML = adding ? `
            <div class="card todo-add-card" style="padding:14px; margin-bottom:14px;">
                <textarea class="form-input" id="addContent" rows="2" placeholder="填写事项，输入 @ 可指定执行人，例如：@王磊 负责贴标..." style="resize:vertical;"></textarea>
                <div class="todo-mention-pop" id="mentionPop" style="display:none;"></div>
                <div style="display:flex; align-items:center; gap:15px; margin-top:12px;">
                    <select class="form-input" id="addPriority" style="width:auto;">
                        <option value="normal">优先级：普通</option>
                        <option value="urgent">优先级：紧急</option>
                    </select>
                    <div style="flex:1;"></div>
                    <button class="btn btn-secondary" onclick="toggleAdd()">取消</button>
                    <button class="btn btn-primary" onclick="addItem()">提交</button>
                </div>
            </div>` : '';
        if (adding) {
            const ta = $('addContent');
            ta.addEventListener('input', onMentionInput);
            ta.addEventListener('keydown', onMentionKey);
            ta.focus();
        }

        const list = visible();
        if (!list.length) {
            $('todoList').innerHTML = `<div class="todo-empty">暂无事项</div>`;
            return;
        }

        $('todoList').innerHTML = list.map(it => {
            const pBadge = it.priority === 'urgent'
                ? '<span class="badge badge-danger">紧急</span>'
                : '<span class="badge badge-info">普通</span>';
            const sBadge = it.status === 'done'
                ? '<span class="badge badge-success">已完成</span>'
                : '<span class="badge badge-warning">待完成</span>';
            const storeTag = (!currentStore && it.store_name) ? `<span class="todo-store-tag">${esc(it.store_name)}</span>` : '';
            const asgNames = assigneeNames(it.assignees);
            const asgHtml = asgNames.length
                ? `<div class="todo-assignees"><span class="al">执行人</span>${asgNames.map(n => `<span class="todo-atag">@${esc(n)}</span>`).join('')}</div>`
                : '';
            let meta = `<div class="todo-meta"><span>发起人 <b>${esc(it.creator_name || '')}</b></span><span>创建 <b>${esc(it.created_at)}</b></span>`;
            if (it.status === 'done') {
                meta += `<span>完成人 <b>${esc(it.completor_name || '')}</b></span><span>完成 <b>${esc(it.completed_at || '')}</b></span>`;
            }
            meta += `</div>`;
            let detail = '';
            if (it.status === 'done' && it.completion_detail) {
                detail = `<div class="todo-detail-box"><div class="dh">完成详情</div>${esc(it.completion_detail)}</div>`;
            }
            let acts = '';
            if (it.status === 'pending') {
                acts = `<div class="todo-acts"><button class="btn btn-primary btn-sm" onclick="startComplete(${it.id})">完成</button><button class="btn btn-danger btn-sm" onclick="del(${it.id})">删除</button></div>`;
            } else {
                acts = `<div class="todo-acts"><button class="btn btn-secondary btn-sm" onclick="reopen(${it.id})">重新打开</button><button class="btn btn-danger btn-sm" onclick="del(${it.id})">删除</button></div>`;
            }
            const edit = (completeId === it.id) ? `
                <div class="todo-complete-edit">
                    <label>完成详情（必填）</label>
                    <textarea class="form-input" id="cdetail" rows="2" placeholder="说明完成情况，例如：已核对无误 / 已转交某某处理..." style="resize:vertical;"></textarea>
                    <div style="display:flex; gap:15px; margin-top:9px; justify-content:flex-end;">
                        <button class="btn btn-secondary btn-sm" onclick="completeId=null;render()">取消</button>
                        <button class="btn btn-primary btn-sm" onclick="confirmComplete()">确认完成</button>
                    </div>
                </div>` : '';
            return `<div class="todo-item ${it.status === 'done' ? 'done' : ''}">
                <div class="todo-top">
                    <div class="todo-prio">${pBadge}</div>
                    <div class="todo-content">${renderContent(it.content)}</div>
                    <div class="todo-badges">${storeTag}${sBadge}</div>
                </div>
                <div class="todo-bottom">
                    <div class="todo-info">${asgHtml}${meta}</div>
                    <div class="todo-acts">${acts}</div>
                </div>
                ${detail}${edit}
            </div>`;
        }).join('');
    }

    // ===== 新增 =====
    async function addItem() {
        if (!currentStore) { toast('请先切换到具体店铺'); return; }
        const content = $('addContent').value.trim();
        const priority = $('addPriority').value;
        if (!content) { toast('请填写事项内容'); return; }
        try {
            const res = await fetch('../api/todo_add.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ content, priority, assignee_ids: Array.from(assigneeSet) })
            });
            const data = await res.json();
            if (!data.success) { toast(data.error || '添加失败'); return; }
            assigneeSet.clear(); adding = false; completeId = null;
            await load(); toast('已添加');
        } catch (err) { toast('添加失败'); }
    }

    // ===== 完成 =====
    function startComplete(id) { completeId = id; render(); }
    async function confirmComplete() {
        const detail = $('cdetail').value.trim();
        if (!detail) { toast('请填写完成详情'); return; }
        try {
            const res = await fetch('../api/todo_complete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: completeId, completion_detail: detail })
            });
            const data = await res.json();
            if (!data.success) { toast(data.error || '操作失败'); return; }
            completeId = null;
            await load(); toast('已完成');
        } catch (err) { toast('操作失败'); }
    }

    // ===== 重新打开 =====
    async function reopen(id) {
        try {
            const res = await fetch('../api/todo_reopen.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            });
            const data = await res.json();
            if (!data.success) { toast(data.error || '操作失败'); return; }
            await load(); toast('已重新打开');
        } catch (err) { toast('操作失败'); }
    }

    // ===== 删除（硬删，需确认） =====
    async function del(id) {
        if (!confirm('确定删除该待办？删除后不可恢复')) return;
        try {
            const res = await fetch('../api/todo_delete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            });
            const data = await res.json();
            if (!data.success) { toast(data.error || '操作失败'); return; }
            if (completeId === id) completeId = null;
            await load(); toast('已删除');
        } catch (err) { toast('操作失败'); }
    }

    // ===== @ 指定执行人 =====
    function onMentionInput() {
        const ta = $('addContent'); if (!ta) return;
        const pos = ta.selectionStart, before = ta.value.slice(0, pos);
        const m = before.match(/@([^\s@]*)$/);
        if (!m) { hideMention(); return; }
        const frag = m[1];
        const matches = members.filter(x => x.name.includes(frag) && x.name !== frag);
        if (!matches.length) { hideMention(); return; }
        mentionStart = m.index; mentionList = matches; mentionIdx = 0;
        showMention();
    }
    function showMention() {
        const pop = $('mentionPop'); if (!pop) return;
        pop.innerHTML = mentionList.map((x, i) =>
            `<div class="mi ${i === mentionIdx ? 'on' : ''}" onmousedown="event.preventDefault();pickMention(${x.id})"><span class="av">${esc(x.name[0])}</span>${esc(x.name)}</div>`
        ).join('');
        pop.style.display = 'block';
    }
    function hideMention() {
        const pop = $('mentionPop'); if (pop) pop.style.display = 'none';
        mentionStart = -1; mentionList = [];
    }
    function pickMention(id) {
        const ta = $('addContent'); if (!ta) return;
        const m = members.find(x => x.id === id); if (!m) return;
        const pos = ta.selectionStart, before = ta.value.slice(0, mentionStart), after = ta.value.slice(pos);
        ta.value = before + '@' + m.name + ' ' + after;
        assigneeSet.add(id);
        const np = before.length + m.name.length + 2;
        ta.setSelectionRange(np, np); ta.focus(); hideMention();
    }
    function onMentionKey(e) {
        const pop = $('mentionPop'); if (!pop || pop.style.display === 'none') return;
        if (e.key === 'Escape') { e.preventDefault(); hideMention(); }
        else if (e.key === 'Enter') { e.preventDefault(); if (mentionList[mentionIdx]) pickMention(mentionList[mentionIdx].id); }
        else if (e.key === 'ArrowDown') { e.preventDefault(); mentionIdx = (mentionIdx + 1) % mentionList.length; showMention(); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); mentionIdx = (mentionIdx - 1 + mentionList.length) % mentionList.length; showMention(); }
    }
    document.addEventListener('click', e => {
        const pop = $('mentionPop'); if (!pop || pop.style.display === 'none') return;
        if (e.target.closest('.todo-mention-pop') || e.target.id === 'addContent') return;
        hideMention();
    });

    load();
    </script>
</body>
</html>
