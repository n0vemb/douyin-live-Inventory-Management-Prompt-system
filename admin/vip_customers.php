<?php $pageTitle = '客户管理'; $currentPage = 'vip_customers'; ?>
<?php require_once __DIR__ . '/layout.php'; ?>

<style>
.customer-avatar { width:34px; height:34px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:14px; font-weight:600; color:#fff; flex-shrink:0; background:linear-gradient(135deg, #667eea, #764ba2); }
.vip-badge { display:inline-block; padding:2px 8px; border-radius:4px; font-size:12px; font-weight:500; background:rgba(245,158,11,0.15); color:#d97706; }
.customer-toolbar { display:flex; gap:12px; align-items:center; flex-wrap:wrap; margin-bottom:20px; }
.customer-search { flex:1; min-width:200px; max-width:320px; }
.action-btn { padding:4px 10px; font-size:12px; border-radius:6px; border:1px solid var(--border); background:transparent; cursor:pointer; color:var(--text-secondary); transition:all .15s; }
.action-btn:hover { border-color:var(--primary); color:var(--primary); background:var(--primary-light); }
.action-btn.danger:hover { border-color:var(--danger); color:var(--danger); background:rgba(239,68,68,0.08); }
</style>

<div class="page-title">客户管理</div>

<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:10px;">
        <div class="customer-toolbar" style="margin-bottom:0; flex:1;">
            <input type="text" class="form-input customer-search" id="searchInput" placeholder="搜索昵称、VIP编号..." oninput="debouncedLoad()">
        </div>
        <div style="font-size:13px; color:var(--text-tertiary);">共 <span id="customerCount">0</span> 个客户</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>客户</th>
                <th class="sortable" data-sort="vip_no" onclick="setSort('vip_no')" style="cursor:pointer; user-select:none;">VIP编号<span class="sort-arrow"> ▲</span></th>
                <th class="sortable" data-sort="total_spent" onclick="setSort('total_spent')" style="cursor:pointer; user-select:none;">累计消费<span class="sort-arrow"></span></th>
                <th class="sortable" data-sort="session_count" onclick="setSort('session_count')" style="cursor:pointer; user-select:none;">出现场次<span class="sort-arrow"></span></th>
                <th>最近场次</th>
                <th class="sortable" data-sort="last_used_at" onclick="setSort('last_used_at')" style="cursor:pointer; user-select:none;">最近使用<span class="sort-arrow"></span></th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody id="customerList">
            <tr><td colspan="7" style="text-align:center;color:var(--text-tertiary);padding:40px;">加载中...</td></tr>
        </tbody>
    </table>
</div>

<!-- 编辑弹窗 -->
<div class="modal" id="editCustomerModal">
    <div class="modal-content" style="max-width:420px;">
        <div class="modal-header">
            <h3 class="modal-title">编辑客户</h3>
            <button class="modal-close" onclick="closeEditModal()">&times;</button>
        </div>
        <form onsubmit="saveEdit(event)">
            <input type="hidden" id="editOldVipNo">
            <div class="form-group">
                <label class="form-label">昵称</label>
                <input type="text" class="form-input" id="editNickname" required>
            </div>
            <div class="form-group">
                <label class="form-label">VIP编号</label>
                <input type="text" class="form-input" id="editVipNo" required>
            </div>
            <div style="display:flex; gap:10px; margin-top:20px;">
                <button type="submit" class="btn btn-primary" style="flex:1;">保存</button>
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">取消</button>
            </div>
        </form>
    </div>
</div>

<!-- 删除确认弹窗 -->
<div class="modal" id="deleteModal">
    <div class="modal-content" style="max-width:400px;">
        <div class="modal-header">
            <h3 class="modal-title">删除客户</h3>
            <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
        </div>
        <div style="padding:10px 0; color:var(--text-secondary); font-size:14px;">
            确定删除客户 <strong id="deleteCustomerName" style="color:var(--text);"></strong>（VIP编号 <strong id="deleteVipNo" style="color:var(--text);"></strong>）？<br>
            <span style="color:var(--danger); font-size:13px;">将删除该VIP编号的全部历史记录，且不可恢复。</span>
        </div>
        <div style="display:flex; gap:10px; margin-top:20px;">
            <button class="btn btn-danger" style="flex:1;" onclick="confirmDelete()">删除</button>
            <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">取消</button>
        </div>
    </div>
</div>

<script>
let allCustomers = [];
let deleteVipNo = '';
let searchTimer = null;

function debouncedLoad() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(loadCustomers, 300);
}

async function loadCustomers() {
    const keyword = document.getElementById('searchInput').value.trim();
    let url = '../api/list_vip_customers.php';
    if (keyword) url += '?keyword=' + encodeURIComponent(keyword);
    try {
        const res = await fetch(url);
        const data = await res.json();
        if (!data.success) throw new Error(data.error);
        allCustomers = data.data.customers || [];
        renderCustomers();
    } catch (e) {
        document.getElementById('customerList').innerHTML =
            '<tr><td colspan="7" style="text-align:center;color:var(--danger);padding:40px;">加载失败</td></tr>';
    }
}

let sortField = 'vip_no';    // 当前排序字段
let sortDir = 1;             // 1升序 -1降序

function sortCustomers() {
    const list = [...allCustomers];
    list.sort((a, b) => {
        let va, vb;
        if (sortField === 'vip_no') {
            va = parseInt(a.vip_no) || 0; vb = parseInt(b.vip_no) || 0;
        } else if (sortField === 'total_spent') {
            va = Number(a.total_spent || 0); vb = Number(b.total_spent || 0);
        } else if (sortField === 'session_count') {
            va = Number(a.session_count || 0); vb = Number(b.session_count || 0);
        } else if (sortField === 'last_used_at') {
            va = a.last_used_at || ''; vb = b.last_used_at || '';
            return sortDir * String(va).localeCompare(String(vb));
        } else {
            va = a[sortField] || ''; vb = b[sortField] || '';
            return sortDir * String(va).localeCompare(String(vb));
        }
        return sortDir * (va - vb);
    });
    return list;
}

function setSort(field) {
    if (sortField === field) { sortDir = -sortDir; }
    else { sortField = field; sortDir = 1; }
    // 更新表头箭头指示
    document.querySelectorAll('th.sortable').forEach(th => {
        const arrow = th.querySelector('.sort-arrow');
        if (arrow) arrow.textContent = '';
    });
    const th = document.querySelector(`th[data-sort="${field}"]`);
    if (th) {
        let arrow = th.querySelector('.sort-arrow');
        if (!arrow) { arrow = document.createElement('span'); arrow.className = 'sort-arrow'; th.appendChild(arrow); }
        arrow.textContent = sortDir === 1 ? ' ▲' : ' ▼';
    }
    renderCustomers();
}

// VIP消费分档：0-300蓝 #3B82F6 / 301-1000紫 #8B5CF6 / 1001-3000玫红 #F43F5E / 3000+橙金 #F59E0B
function vipTierStyle(totalSpent) {
    const t = Number(totalSpent || 0);
    if (t > 3000) return { bg: 'rgba(245,158,11,0.2)', color: '#F59E0B', border: '1px solid rgba(245,158,11,0.55)' };
    if (t > 1000) return { bg: 'rgba(244,63,94,0.18)', color: '#F43F5E', border: '1px solid rgba(244,63,94,0.5)' };
    if (t > 300) return { bg: 'rgba(139,92,246,0.18)', color: '#8B5CF6', border: '1px solid rgba(139,92,246,0.5)' };
    return { bg: 'rgba(59,130,246,0.16)', color: '#3B82F6', border: '1px solid rgba(59,130,246,0.45)' };
}

function renderCustomers() {
    const tbody = document.getElementById('customerList');
    document.getElementById('customerCount').textContent = allCustomers.length;

    if (!allCustomers.length) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:var(--text-tertiary);padding:40px;">暂无客户</td></tr>';
        return;
    }

    tbody.innerHTML = sortCustomers().map(c => {
        const initial = (c.nickname || c.vip_no || '?').charAt(0);
        const nick = c.nickname || '<span style="color:var(--text-tertiary);">(无昵称)</span>';
        const tier = vipTierStyle(c.total_spent);
        const sessionText = c.session_count > 0
            ? `${escapeHtml(c.last_session_name || ('#' + c.last_session_id))}`
            : '<span style="color:var(--text-tertiary);">从未消费</span>';
        return `
        <tr>
            <td>
                <div style="display:flex; align-items:center; gap:10px;">
                    <span class="customer-avatar">${escapeHtml(initial)}</span>
                    <span>${nick}</span>
                </div>
            </td>
            <td><span class="vip-badge" style="background:${tier.bg}; color:${tier.color}; border:${tier.border};">${escapeHtml(c.vip_no)}</span></td>
            <td style="font-weight:600; color:var(--danger);">¥${Number(c.total_spent || 0).toLocaleString('zh-CN', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
            <td>${c.session_count} 次</td>
            <td>${sessionText}</td>
            <td style="font-size:13px; color:var(--text-tertiary);">${c.last_used_at || '-'}</td>
            <td>
                <div style="display:flex; gap:6px;">
                    <button class="action-btn" onclick="openEditModal('${escapeAttr(c.vip_no)}', '${escapeAttr(c.nickname)}')">编辑</button>
                    <button class="action-btn danger" onclick="openDeleteModal('${escapeAttr(c.vip_no)}', '${escapeAttr(c.nickname)}')">删除</button>
                </div>
            </td>
        </tr>`;
    }).join('');
}

function escapeHtml(s) {
    return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}
function escapeAttr(s) {
    return escapeHtml(s).replace(/'/g, '\\\'');
}

// ── 编辑 ──
function openEditModal(vipNo, nickname) {
    document.getElementById('editOldVipNo').value = vipNo;
    document.getElementById('editVipNo').value = vipNo;
    document.getElementById('editNickname').value = nickname || '';
    document.getElementById('editCustomerModal').classList.add('show');
}
function closeEditModal() { document.getElementById('editCustomerModal').classList.remove('show'); }

async function saveEdit(e) {
    e.preventDefault();
    const oldVipNo = document.getElementById('editOldVipNo').value;
    const newVipNo = document.getElementById('editVipNo').value.trim();
    const nickname = document.getElementById('editNickname').value.trim();

    if (!newVipNo) { showToast('请输入VIP编号', 'error'); return; }
    if (!nickname) { showToast('请输入昵称', 'error'); return; }

    const data = { old_vip_no: oldVipNo, nickname: nickname };
    if (newVipNo !== oldVipNo) data.new_vip_no = newVipNo;

    try {
        const res = await fetch('../api/update_vip_customer.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await res.json();
        if (result.success) {
            closeEditModal();
            loadCustomers();
            showToast('客户已更新', 'success');
        } else {
            showToast(result.error || '更新失败', 'error');
        }
    } catch (err) {
        showToast('更新失败', 'error');
    }
}

// ── 删除 ──
function openDeleteModal(vipNo, nickname) {
    deleteVipNo = vipNo;
    document.getElementById('deleteCustomerName').textContent = nickname || '(无昵称)';
    document.getElementById('deleteVipNo').textContent = vipNo;
    document.getElementById('deleteModal').classList.add('show');
}
function closeDeleteModal() { document.getElementById('deleteModal').classList.remove('show'); }

async function confirmDelete() {
    if (!deleteVipNo) return;
    try {
        const res = await fetch('../api/delete_vip_customer.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ vip_no: deleteVipNo })
        });
        const result = await res.json();
        if (result.success) {
            closeDeleteModal();
            loadCustomers();
            showToast('客户已删除', 'success');
        } else {
            showToast(result.error || '删除失败', 'error');
        }
    } catch (err) {
        showToast('删除失败', 'error');
    }
}

// ── Toast（页面自带，参照全站风格）──
let toastTimer = null;
function showToast(msg, type) {
    let t = document.getElementById('pageToast');
    if (!t) {
        t = document.createElement('div');
        t.id = 'pageToast';
        t.style.cssText = 'position:fixed;top:20px;left:50%;transform:translateX(-50%);z-index:99999;padding:10px 20px;border-radius:8px;font-size:14px;color:#fff;box-shadow:0 4px 12px rgba(0,0,0,0.2);transition:opacity .3s;max-width:80%;';
        document.body.appendChild(t);
    }
    t.style.background = type === 'error' ? 'rgba(239,68,68,0.95)' : 'rgba(16,185,129,0.95)';
    t.textContent = msg;
    t.style.opacity = '1';
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { t.style.opacity = '0'; }, 2500);
}

loadCustomers();
</script>
