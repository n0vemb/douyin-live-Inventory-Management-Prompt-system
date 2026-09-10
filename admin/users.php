<?php $pageTitle = '用户管理'; $currentPage = 'users'; ?>
<?php require_once __DIR__ . '/layout.php'; ?>
<?php
$isSuperAdmin = ($currentUser['role'] === 'super_admin');
$isGroupAdmin = ($currentUser['role'] === 'group_admin');
$isStoreAdmin = ($currentUser['role'] === 'store_admin');
$canManageUsers = $isSuperAdmin || $isGroupAdmin || $isStoreAdmin;
$myStoreId = $currentUser['store_id'] ?? null;
$myShopId = $currentUser['shop_id'] ?? null;
$defaultStoreId = $currentUser['view_store_id'] ?? $currentUser['store_id'] ?? null;
?>

<style>
.user-role-badge { display:inline-block; padding:2px 8px; border-radius:4px; font-size:12px; font-weight:500; }
.role-super { background:rgba(239,68,68,0.15); color:#ef4444; }
.role-group { background:rgba(139,92,246,0.15); color:#a78bfa; }
.role-store { background:rgba(102,126,234,0.15); color:#667eea; }
.role-operator { background:rgba(16,185,129,0.15); color:#10b981; }
.role-deputy { background:rgba(99,102,241,0.15); color:#818cf8; }
.role-warehouse { background:rgba(245,158,11,0.15); color:#f59e0b; }
.users-toolbar { display:flex; gap:12px; align-items:center; flex-wrap:wrap; margin-bottom:20px; }
.users-search { flex:1; min-width:200px; max-width:320px; }
.users-filter { display:flex; gap:6px; flex-wrap:wrap; }
.filter-tag { padding:4px 12px; border-radius:20px; font-size:12px; cursor:pointer; border:1px solid var(--border); background:var(--bg-surface); color:var(--text-secondary); transition:all .15s; }
.filter-tag:hover { border-color:var(--primary); color:var(--primary); }
.filter-tag.active { background:var(--primary); color:#fff; border-color:var(--primary); }
.user-avatar { width:28px; height:28px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:600; color:#fff; flex-shrink:0; }
.action-btn { padding:4px 10px; font-size:12px; border-radius:6px; border:1px solid var(--border); background:transparent; cursor:pointer; color:var(--text-secondary); transition:all .15s; }
.action-btn:hover { border-color:var(--primary); color:var(--primary); background:var(--primary-light); }
.action-btn.danger:hover { border-color:var(--danger); color:var(--danger); background:rgba(239,68,68,0.08); }
.users-count { font-size:13px; color:var(--text-tertiary); }
</style>

<div class="page-header">
    <h1>👥 用户管理</h1>
    <p>按 平台 / 集团 / 店 三级管理账号</p>
</div>

<div class="card">
    <div class="users-toolbar">
        <input type="text" class="form-input users-search" id="searchInput" placeholder="搜索用户名、显示名..." oninput="applyFilter()">
        <div class="users-filter">
            <span class="filter-tag active" data-filter="all" onclick="setFilter(this, 'all')">全部</span>
            <?php if ($isSuperAdmin): ?><span class="filter-tag" data-filter="super_admin" onclick="setFilter(this, 'super_admin')">超管</span><?php endif; ?>
            <?php if ($isSuperAdmin || $isGroupAdmin): ?><span class="filter-tag" data-filter="group_admin" onclick="setFilter(this, 'group_admin')">集团管理员</span><?php endif; ?>
            <?php if ($canManageUsers && !$isStoreAdmin): ?><span class="filter-tag" data-filter="store_admin" onclick="setFilter(this, 'store_admin')">店管</span><?php endif; ?>
            <span class="filter-tag" data-filter="deputy_store_admin" onclick="setFilter(this, 'deputy_store_admin')">副店长</span>
            <span class="filter-tag" data-filter="operator" onclick="setFilter(this, 'operator')">运营</span>
            <span class="filter-tag" data-filter="warehouse" onclick="setFilter(this, 'warehouse')">仓库</span>
            <span class="filter-tag" data-filter="active" onclick="setFilter(this, 'active')">启用</span>
            <span class="filter-tag" data-filter="disabled" onclick="setFilter(this, 'disabled')">禁用</span>
        </div>
        <button class="btn btn-primary btn-sm" onclick="openCreateModal()">+ 添加用户</button>
    </div>
    <table>
        <thead>
            <tr>
                <th style="width:50px;">ID</th>
                <th>用户</th>
                <th>角色</th>
                <th>所属集团 / 店</th>
                <th>状态</th>
                <th>最后登录</th>
                <th style="width:160px;">操作</th>
            </tr>
        </thead>
        <tbody id="usersList">
            <tr><td colspan="7" style="text-align:center;color:var(--text-tertiary);padding:40px;">加载中...</td></tr>
        </tbody>
    </table>
    <div style="margin-top:12px; text-align:right;" class="users-count" id="usersCount"></div>
</div>

<!-- 创建用户弹窗 -->
<div class="modal" id="createUserModal">
    <div class="modal-content" style="max-width:420px;">
        <div class="modal-header">
            <h3 class="modal-title">添加新用户</h3>
            <button class="modal-close" onclick="closeCreateModal()">&times;</button>
        </div>
        <form onsubmit="createUser(event)">
            <div class="form-group">
                <label class="form-label">用户名</label>
                <input type="text" class="form-input" id="newUsername" required>
            </div>
            <div class="form-group">
                <label class="form-label">显示名称</label>
                <input type="text" class="form-input" id="newDisplayName" placeholder="选填">
            </div>
            <div class="form-group">
                <label class="form-label">密码 <span style="color:var(--text-tertiary);font-weight:400;">至少6位</span></label>
                <input type="password" class="form-input" id="newPassword" required minlength="6">
            </div>
            <div class="form-group">
                <label class="form-label">角色</label>
                <select class="form-input" id="newRole" onchange="toggleCreateStoreSelect()">
                    <?php if ($canManageUsers && !$isStoreAdmin): ?><option value="store_admin">店管</option><?php endif; ?>
                    <?php if ($isSuperAdmin): ?><option value="group_admin">集团管理员</option><?php endif; ?>
                    <option value="operator">运营</option>
                    <option value="deputy_store_admin">副店长</option>
                    <?php if ($isSuperAdmin || $isGroupAdmin): ?><option value="warehouse">仓库</option><?php endif; ?>
                    <?php if ($isSuperAdmin): ?><option value="super_admin">超级管理员</option><?php endif; ?>
                </select>
            </div>
            <?php if ($isSuperAdmin): ?>
            <div class="form-group" id="createStoreSelectGroup">
                <label class="form-label">所属集团</label>
                <select class="form-input" id="newStoreId" onchange="reloadShopsForStore('newStoreId', 'newShopId')"></select>
            </div>
            <?php else: ?>
            <input type="hidden" id="newStoreId" value="<?= (int)$myStoreId ?>">
            <?php endif; ?>
            <?php if ($isSuperAdmin || $isGroupAdmin): ?>
            <div class="form-group" id="createShopSelectGroup" style="display:none;">
                <label class="form-label">所属店</label>
                <select class="form-input" id="newShopId"></select>
            </div>
            <?php else: ?>
            <input type="hidden" id="newShopId" value="<?= (int)$myShopId ?>">
            <?php endif; ?>
            <div style="display:flex; gap:10px; margin-top:20px;">
                <button type="submit" class="btn btn-primary" style="flex:1;">创建</button>
                <button type="button" class="btn btn-secondary" onclick="closeCreateModal()">取消</button>
            </div>
            <?php if ($isStoreAdmin): ?>
            <div style="margin-top:12px; font-size:12px; color:var(--text-tertiary); line-height:1.6;">
                店管账号只能为本店分配账号；如需给集团下其它店建账号，请用「集团管理员」或平台超管登录操作。
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- 编辑用户弹窗 -->
<div class="modal" id="editUserModal">
    <div class="modal-content" style="max-width:420px;">
        <div class="modal-header">
            <h3 class="modal-title">编辑用户</h3>
            <button class="modal-close" onclick="closeEditModal()">&times;</button>
        </div>
        <form onsubmit="editUser(event)">
            <input type="hidden" id="editUserId">
            <div class="form-group">
                <label class="form-label">用户名</label>
                <input type="text" class="form-input" id="editUsername" required>
            </div>
            <div class="form-group">
                <label class="form-label">显示名称</label>
                <input type="text" class="form-input" id="editDisplayName" placeholder="选填">
            </div>
            <div class="form-group">
                <label class="form-label">新密码 <span style="color:var(--text-tertiary);font-weight:400;">留空不修改，至少6位</span></label>
                <input type="password" class="form-input" id="editPassword" minlength="6" placeholder="留空则不修改密码">
            </div>
            <div class="form-group">
                <label class="form-label">角色</label>
                <select class="form-input" id="editRole" onchange="toggleEditStoreSelect()">
                    <?php if ($canManageUsers && !$isStoreAdmin): ?><option value="store_admin">店管</option><?php endif; ?>
                    <?php if ($isSuperAdmin): ?><option value="group_admin">集团管理员</option><?php endif; ?>
                    <option value="operator">运营</option>
                    <option value="deputy_store_admin">副店长</option>
                    <?php if ($isSuperAdmin || $isGroupAdmin): ?><option value="warehouse">仓库</option><?php endif; ?>
                    <?php if ($isSuperAdmin): ?><option value="super_admin">超级管理员</option><?php endif; ?>
                </select>
            </div>
            <?php if ($isSuperAdmin): ?>
            <div class="form-group" id="editStoreSelectGroup">
                <label class="form-label">所属集团</label>
                <select class="form-input" id="editStoreId" onchange="reloadShopsForStore('editStoreId', 'editShopId')"></select>
            </div>
            <?php else: ?>
            <input type="hidden" id="editStoreId" value="<?= (int)$myStoreId ?>">
            <?php endif; ?>
            <?php if ($isSuperAdmin || $isGroupAdmin): ?>
            <div class="form-group" id="editShopSelectGroup" style="display:none;">
                <label class="form-label">所属店</label>
                <select class="form-input" id="editShopId"></select>
            </div>
            <?php else: ?>
            <input type="hidden" id="editShopId" value="<?= (int)$myShopId ?>">
            <?php endif; ?>
            <div style="display:flex; gap:10px; margin-top:20px;">
                <button type="submit" class="btn btn-primary" style="flex:1;">保存</button>
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">取消</button>
            </div>
            <?php if ($isStoreAdmin): ?>
            <div style="margin-top:12px; font-size:12px; color:var(--text-tertiary); line-height:1.6;">
                店管账号只能为本店分配账号；如需给集团下其它店建账号，请用「集团管理员」或平台超管登录操作。
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- 删除确认弹窗 -->
<div class="modal" id="deleteUserModal">
    <div class="modal-content" style="max-width:380px;">
        <div class="modal-header">
            <h3 class="modal-title">确认删除</h3>
            <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
        </div>
        <div style="padding:10px 0;">
            <p style="margin-bottom:8px;">确定要删除用户 <strong id="deleteUserName"></strong>？</p>
            <p style="font-size:13px; color:var(--text-tertiary);">此操作不可撤销。</p>
        </div>
        <input type="hidden" id="deleteUserId">
        <div style="display:flex; gap:10px; margin-top:16px;">
            <button class="btn btn-danger" style="flex:1;" onclick="confirmDelete()">删除</button>
            <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">取消</button>
        </div>
    </div>
</div>

<script>
const IS_SUPER_ADMIN = <?= $isSuperAdmin ? 'true' : 'false' ?>;
const IS_GROUP_ADMIN = <?= $isGroupAdmin ? 'true' : 'false' ?>;
const IS_STORE_ADMIN = <?= $isStoreAdmin ? 'true' : 'false' ?>;
const MY_STORE_ID = <?= $myStoreId ? (int)$myStoreId : 'null' ?>;
const MY_SHOP_ID = <?= $myShopId ? (int)$myShopId : 'null' ?>;
const DEFAULT_STORE_ID = <?= $defaultStoreId ? (int)$defaultStoreId : 'null' ?>;
const SHOP_ROLES = ['store_admin', 'operator', 'deputy_store_admin'];
let allUsers = [];
let currentFilter = 'all';
let currentSearch = '';

function roleLabel(r) {
    return {super_admin:'超管', group_admin:'集团管理员', store_admin:'店管', deputy_store_admin:'副店长', operator:'运营', warehouse:'仓库'}[r] || r;
}
function roleClass(r) {
    return {super_admin:'role-super', group_admin:'role-group', store_admin:'role-store', deputy_store_admin:'role-deputy', operator:'role-operator', warehouse:'role-warehouse'}[r] || 'role-store';
}

async function loadUsers() {
    try {
        const res = await fetch('../api/list_users.php');
        const data = await res.json();
        if (!data.success) throw new Error(data.error);
        allUsers = data.data.users || [];
        applyFilter();
    } catch (e) {
        document.getElementById('usersList').innerHTML =
            '<tr><td colspan="7" style="text-align:center;color:var(--danger);padding:40px;">加载失败</td></tr>';
    }
}

function applyFilter() {
    currentSearch = document.getElementById('searchInput').value.toLowerCase();
    let filtered = allUsers;

    // 搜索过滤
    if (currentSearch) {
        filtered = filtered.filter(u =>
            u.username.toLowerCase().includes(currentSearch) ||
            (u.display_name || '').toLowerCase().includes(currentSearch)
        );
    }

    // 筛选标签过滤
    if (currentFilter === 'store_admin') filtered = filtered.filter(u => u.role === 'store_admin');
    else if (currentFilter === 'group_admin') filtered = filtered.filter(u => u.role === 'group_admin');
    else if (currentFilter === 'operator') filtered = filtered.filter(u => u.role === 'operator');
    else if (currentFilter === 'deputy_store_admin') filtered = filtered.filter(u => u.role === 'deputy_store_admin');
    else if (currentFilter === 'warehouse') filtered = filtered.filter(u => u.role === 'warehouse');
    else if (currentFilter === 'super_admin') filtered = filtered.filter(u => u.role === 'super_admin');
    else if (currentFilter === 'active') filtered = filtered.filter(u => u.is_active);
    else if (currentFilter === 'disabled') filtered = filtered.filter(u => !u.is_active);

    renderUsers(filtered);
}

function setFilter(el, filter) {
    document.querySelectorAll('.filter-tag').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
    currentFilter = filter;
    applyFilter();
}

function renderUsers(users) {
    const tbody = document.getElementById('usersList');
    document.getElementById('usersCount').textContent = `共 ${users.length} 个用户`;

    if (!users.length) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:var(--text-tertiary);padding:40px;">暂无匹配用户</td></tr>';
        return;
    }

    tbody.innerHTML = users.map(u => {
        const initial = (u.display_name || u.username).charAt(0).toUpperCase();
        const avatarColors = ['#667eea','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899','#06b6d4','#f97316'];
        const avatarColor = avatarColors[u.id % avatarColors.length];

        return `<tr>
            <td>${u.id}</td>
            <td>
                <div style="display:flex; align-items:center; gap:10px;">
                    <div class="user-avatar" style="background:${avatarColor};">${initial}</div>
                    <div>
                        <div style="font-weight:500;">${u.username}</div>
                        <div style="font-size:12px;color:var(--text-tertiary);">${u.display_name || '-'}</div>
                    </div>
                </div>
            </td>
            <td><span class="user-role-badge ${roleClass(u.role)}">${roleLabel(u.role)}</span></td>
            <td>${u.store_name || '-'}${u.shop_name ? ' · ' + u.shop_name : ''}</td>
            <td>${u.is_active
                ? '<span style="display:inline-flex;align-items:center;gap:4px;color:var(--success);"><span style="width:6px;height:6px;border-radius:50%;background:var(--success);display:inline-block;"></span>启用</span>'
                : '<span style="display:inline-flex;align-items:center;gap:4px;color:var(--text-tertiary);"><span style="width:6px;height:6px;border-radius:50%;background:var(--text-tertiary);display:inline-block;"></span>禁用</span>'}</td>
            <td style="font-size:13px;color:var(--text-tertiary);">${u.last_login_at || '从未登录'}</td>
            <td>
                <div style="display:flex; gap:6px;">
                    <button class="action-btn" onclick="openEditModal(${u.id})">编辑</button>
                    <button class="action-btn" onclick="resetPassword(${u.id})" style="color:var(--warning);">重置密码</button>
                    <button class="action-btn ${u.is_active ? '' : ''}" onclick="toggleUser(${u.id}, ${u.is_active ? 1 : 0})" style="${u.is_active ? 'color:var(--warning);' : 'color:var(--success);'}">${u.is_active ? '禁用' : '启用'}</button>
                    <button class="action-btn danger" onclick="openDeleteModal(${u.id})">删除</button>
                </div>
            </td>
        </tr>`;
    }).join('');
}

// ── 创建用户 ──
async function loadStores(selectId) {
    try {
        const res = await fetch('../api/list_stores.php');
        const data = await res.json();
        const stores = (data.success ? data.data.stores : []) || [];
        const select = document.getElementById(selectId);
        select.innerHTML = stores.map(s => `<option value="${s.id}">${s.name}（${s.barcode_prefix}）</option>`).join('');
    } catch(e) {}
}

async function loadShops(selectId, storeId) {
    try {
        const q = storeId ? '?store_id=' + storeId : '';
        const res = await fetch('../api/list_shops.php' + q);
        const data = await res.json();
        const shops = (data.success ? data.data.shops : []) || [];
        const select = document.getElementById(selectId);
        select.innerHTML = shops.map(s => `<option value="${s.id}">${s.name}</option>`).join('');
        return shops;
    } catch(e) { return []; }
}

function needsStoreSelect(role) {
    return IS_SUPER_ADMIN && role !== 'super_admin';
}

function needsShopSelect(role) {
    return (IS_SUPER_ADMIN || IS_GROUP_ADMIN || IS_STORE_ADMIN) && SHOP_ROLES.includes(role);
}

// 超管切换“所属集团”时，重新加载该集团的店铺列表
function reloadShopsForStore(storeSelId, shopSelId) {
    const el = document.getElementById(storeSelId);
    const sid = el ? (parseInt(el.value) || null) : null;
    loadShops(shopSelId, sid);
}

function currentStoreForShops() {
    if (IS_SUPER_ADMIN) {
        const v = parseInt(document.getElementById('newStoreId')?.value || '');
        return v || null;
    }
    return MY_STORE_ID;
}

function toggleCreateStoreSelect() {
    const r = document.getElementById('newRole').value;
    if (IS_SUPER_ADMIN) {
        document.getElementById('createStoreSelectGroup').style.display = needsStoreSelect(r) ? 'block' : 'none';
    }
    const shopGroup = document.getElementById('createShopSelectGroup');
    if (shopGroup && (IS_SUPER_ADMIN || IS_GROUP_ADMIN)) {
        shopGroup.style.display = needsShopSelect(r) ? 'block' : 'none';
        if (needsShopSelect(r)) {
            const sid = IS_SUPER_ADMIN ? (parseInt(document.getElementById('newStoreId').value) || null) : MY_STORE_ID;
            loadShops('newShopId', sid);
        }
    }
}

function openCreateModal() {
    document.getElementById('newUsername').value = '';
    document.getElementById('newDisplayName').value = '';
    document.getElementById('newPassword').value = '';
    document.getElementById('newRole').value = 'operator';
    if (IS_SUPER_ADMIN) {
        loadStores('newStoreId').then(() => {
            const sel = document.getElementById('newStoreId');
            if (DEFAULT_STORE_ID && [...sel.options].some(o => o.value === String(DEFAULT_STORE_ID))) {
                sel.value = String(DEFAULT_STORE_ID);
            }
            toggleCreateStoreSelect();
        });
    } else if (IS_GROUP_ADMIN) {
        loadShops('newShopId', MY_STORE_ID).then(() => toggleCreateStoreSelect());
    } else {
        toggleCreateStoreSelect();
    }
    document.getElementById('createUserModal').classList.add('show');
}

function closeCreateModal() {
    document.getElementById('createUserModal').classList.remove('show');
}

async function createUser(e) {
    e.preventDefault();
    const role = document.getElementById('newRole').value;
    const data = {
        username: document.getElementById('newUsername').value,
        display_name: document.getElementById('newDisplayName').value,
        password: document.getElementById('newPassword').value,
        role: role,
        store_id: IS_SUPER_ADMIN ? (parseInt(document.getElementById('newStoreId').value) || null) : MY_STORE_ID,
        shop_id: needsShopSelect(role) ? (parseInt(document.getElementById('newShopId').value) || null) : null
    };
    try {
        const res = await fetch('../api/create_user.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        const result = await res.json();
        if (result.success) {
            closeCreateModal();
            loadUsers();
            showToast('用户已创建', 'success');
        } else {
            showToast(result.error || '创建失败', 'error');
        }
    } catch(e) {
        showToast('创建失败', 'error');
    }
}

// ── 编辑用户 ──
function toggleEditStoreSelect() {
    const r = document.getElementById('editRole').value;
    if (IS_SUPER_ADMIN) {
        document.getElementById('editStoreSelectGroup').style.display = needsStoreSelect(r) ? 'block' : 'none';
        if (needsShopSelect(r)) {
            const sid = parseInt(document.getElementById('editStoreId').value) || null;
            loadShops('editShopId', sid);
        }
    }
    const shopGroup = document.getElementById('editShopSelectGroup');
    if (shopGroup && (IS_SUPER_ADMIN || IS_GROUP_ADMIN)) {
        shopGroup.style.display = needsShopSelect(r) ? 'block' : 'none';
        if (IS_GROUP_ADMIN && needsShopSelect(r)) {
            loadShops('editShopId', MY_STORE_ID);
        }
    }
}

function openEditModal(userId) {
    const u = allUsers.find(x => x.id === userId);
    if (!u) return;

    document.getElementById('editUserId').value = u.id;
    document.getElementById('editUsername').value = u.username;
    document.getElementById('editDisplayName').value = u.display_name || '';
    document.getElementById('editPassword').value = '';
    document.getElementById('editRole').value = u.role;

    if (IS_SUPER_ADMIN) {
        loadStores('editStoreId').then(() => {
            if (u.store_id) document.getElementById('editStoreId').value = u.store_id;
            toggleEditStoreSelect();
            const sid = parseInt(document.getElementById('editStoreId').value) || null;
            if (sid && needsShopSelect(u.role)) {
                loadShops('editShopId', sid).then(() => {
                    if (u.shop_id) document.getElementById('editShopId').value = u.shop_id;
                });
            }
        });
    } else if (IS_GROUP_ADMIN) {
        document.getElementById('editStoreId').value = MY_STORE_ID;
        loadShops('editShopId', MY_STORE_ID).then(() => {
            if (u.shop_id) document.getElementById('editShopId').value = u.shop_id;
            toggleEditStoreSelect();
        });
    } else {
        document.getElementById('editStoreId').value = MY_STORE_ID;
    }

    document.getElementById('editUserModal').classList.add('show');
}

function closeEditModal() {
    document.getElementById('editUserModal').classList.remove('show');
}

async function editUser(e) {
    e.preventDefault();
    const userId = parseInt(document.getElementById('editUserId').value);
    const role = document.getElementById('editRole').value;
    const data = {
        user_id: userId,
        username: document.getElementById('editUsername').value,
        display_name: document.getElementById('editDisplayName').value,
        role: role,
        store_id: IS_SUPER_ADMIN ? (parseInt(document.getElementById('editStoreId').value) || null) : MY_STORE_ID,
        shop_id: needsShopSelect(role) ? (parseInt(document.getElementById('editShopId').value) || null) : null
    };
    const password = document.getElementById('editPassword').value;
    if (password) data.password = password;

    try {
        const res = await fetch('../api/update_user.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        const result = await res.json();
        if (result.success) {
            closeEditModal();
            loadUsers();
            showToast('用户已更新', 'success');
        } else {
            showToast(result.error || '更新失败', 'error');
        }
    } catch(e) {
        showToast('更新失败', 'error');
    }
}

// ── 启用/禁用 ──
async function toggleUser(userId, isActive) {
    const u = allUsers.find(x => x.id === userId);
    const action = isActive ? '禁用' : '启用';
    if (!confirm(`确定${action}用户「${u.username}」？`)) return;
    try {
        const res = await fetch('../api/update_user.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({user_id: userId, is_active: isActive ? 0 : 1})
        });
        const result = await res.json();
        if (result.success) {
            loadUsers();
            showToast(`用户已${action}`, 'success');
        } else {
            showToast(result.error || '操作失败', 'error');
        }
    } catch(e) {
        showToast('操作失败', 'error');
    }
}

// ── 重置密码 ──
async function resetPassword(userId) {
    const u = allUsers.find(x => x.id === userId);
    if (!u) return;
    if (!confirm(`确定将用户「${u.username}」的密码重置为 123456？`)) return;
    try {
        const res = await fetch('../api/reset_password.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({user_id: userId})
        });
        const result = await res.json();
        if (result.success) {
            showToast(result.message || '密码已重置为 123456', 'success');
        } else {
            showToast(result.error || '重置失败', 'error');
        }
    } catch(e) {
        showToast('重置失败', 'error');
    }
}

// ── 删除用户 ──
function openDeleteModal(userId) {
    const u = allUsers.find(x => x.id === userId);
    if (!u) return;
    document.getElementById('deleteUserId').value = u.id;
    document.getElementById('deleteUserName').textContent = u.username;
    document.getElementById('deleteUserModal').classList.add('show');
}

function closeDeleteModal() {
    document.getElementById('deleteUserModal').classList.remove('show');
}

async function confirmDelete() {
    const userId = parseInt(document.getElementById('deleteUserId').value);
    try {
        const res = await fetch('../api/delete_user.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({user_id: userId})
        });
        const result = await res.json();
        if (result.success) {
            closeDeleteModal();
            loadUsers();
            showToast('用户已删除', 'success');
        } else {
            showToast(result.error || '删除失败', 'error');
        }
    } catch(e) {
        showToast('删除失败', 'error');
    }
}

// ── Toast ──
function showToast(message, type) {
    const existing = document.querySelector('.toast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.style.cssText = `
        position:fixed; bottom:30px; left:50%; transform:translateX(-50%);
        padding:12px 24px; border-radius:10px; font-size:14px; z-index:9999;
        background:${type === 'success' ? 'var(--success)' : 'var(--danger)'}; color:#fff;
        box-shadow:0 4px 20px rgba(0,0,0,0.2); transition:opacity .3s;
    `;
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 300); }, 2000);
}

loadUsers();
</script>
</body>
</html>
