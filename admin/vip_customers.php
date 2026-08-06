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
                <th>VIP编号</th>
                <th>累计消费</th>
                <th>出现场次</th>
                <th>最近场次</th>
                <th>最近使用</th>
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

function renderCustomers() {
    const tbody = document.getElementById('customerList');
    document.getElementById('customerCount').textContent = allCustomers.length;

    if (!allCustomers.length) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:var(--text-tertiary);padding:40px;">暂无客户，直播记账时添加客户后自动出现</td></tr>';
        return;
    }

    tbody.innerHTML = allCustomers.map(c => {
        const initial = (c.nickname || c.vip_no || '?').charAt(0);
        const nick = c.nickname || '<span style="color:var(--text-tertiary);">(无昵称)</span>';
        return `
        <tr>
            <td>
                <div style="display:flex; align-items:center; gap:10px;">
                    <span class="customer-avatar">${escapeHtml(initial)}</span>
                    <span>${nick}</span>
                </div>
            </td>
            <td><span class="vip-badge">${escapeHtml(c.vip_no)}</span></td>
            <td style="font-weight:600; color:var(--danger);">¥${Number(c.total_spent || 0).toLocaleString('zh-CN', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
            <td>${c.session_count} 次</td>
            <td>${escapeHtml(c.last_session_name || ('#' + c.last_session_id))}</td>
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
