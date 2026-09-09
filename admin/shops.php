<?php
$pageTitle = '店管理';
$currentPage = 'shops';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/layout.php';

$isSuper = $currentUser['role'] === 'super_admin';
$isGroup = $currentUser['role'] === 'group_admin';
if (!$isSuper && !$isGroup) {
    echo '<div class="card" style="padding:40px;text-align:center;color:var(--text-tertiary);">无权限访问店管理</div></body></html>';
    exit;
}

$storeId = null;
$storeName = '';
if ($isSuper) {
    $storeId = $currentUser['view_store_id'] ?? null;
    $storeName = $currentUser['view_store_name'] ?? '';
} else {
    $storeId = $currentUser['store_id'] ?? null;
    $storeName = $currentUser['store_name'] ?? '';
}
?>
<div class="page-header">
    <h1>🏬 店管理</h1>
    <p><?= $isSuper ? '为集团（客户）创建和管理 A店/B店' : '管理本集团下的店铺' ?></p>
</div>

<?php if ($isSuper && !$storeId): ?>
<div class="card" style="padding:28px; text-align:center; color:var(--text-tertiary);">
    <div style="font-size:15px; margin-bottom:14px;">请先选择集团：</div>
    <div style="display:flex; gap:10px; justify-content:center; align-items:center;">
        <select id="pickStore" class="form-input" style="width:220px;"></select>
        <button class="btn btn-primary" onclick="enterStore()">进入该集团</button>
    </div>
</div>
<?php else: ?>
<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
        <h3 style="font-size:16px; color:var(--text);"><?= htmlspecialchars($storeName) ?> 下的店铺</h3>
        <button class="btn btn-success" onclick="openCreateModal()">+ 新建店</button>
    </div>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>店名</th>
                <th>收银台码</th>
                <th>备注</th>
                <th>创建时间</th>
                <th style="width:190px;">操作</th>
            </tr>
        </thead>
        <tbody id="shopsList">
            <tr><td colspan="6" style="text-align:center;color:var(--text-tertiary);padding:40px;">加载中...</td></tr>
        </tbody>
    </table>
</div>

<!-- 新建店 -->
<div class="modal" id="createModal">
    <div class="modal-content" style="max-width:400px;">
        <div class="modal-header">
            <h3 class="modal-title">新建店铺</h3>
            <button class="modal-close" onclick="closeModal('createModal')">&times;</button>
        </div>
        <form onsubmit="createShop(event)">
            <div class="form-group">
                <label class="form-label">店名</label>
                <input type="text" class="form-input" id="shopName" required placeholder="例如：A店 / B店">
            </div>
            <div class="form-group">
                <label class="form-label">备注</label>
                <input type="text" class="form-input" id="shopRemark" placeholder="选填，如：总部直播 / B地区直播">
            </div>
            <div style="display:flex; gap:10px; margin-top:20px;">
                <button type="submit" class="btn btn-primary" style="flex:1;">创建</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('createModal')">取消</button>
            </div>
        </form>
    </div>
</div>

<!-- 编辑店 -->
<div class="modal" id="editModal">
    <div class="modal-content" style="max-width:400px;">
        <div class="modal-header">
            <h3 class="modal-title">编辑店铺</h3>
            <button class="modal-close" onclick="closeModal('editModal')">&times;</button>
        </div>
        <form onsubmit="updateShop(event)">
            <input type="hidden" id="editShopId">
            <div class="form-group">
                <label class="form-label">店名</label>
                <input type="text" class="form-input" id="editShopName" required>
            </div>
            <div class="form-group">
                <label class="form-label">备注</label>
                <input type="text" class="form-input" id="editShopRemark" placeholder="选填">
            </div>
            <div style="display:flex; gap:10px; margin-top:20px;">
                <button type="submit" class="btn btn-primary" style="flex:1;">保存</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">取消</button>
            </div>
        </form>
    </div>
</div>

<!-- POS 设置（本店独立） -->
<div class="modal" id="posModal">
    <div class="modal-content" style="max-width:560px;">
        <div class="modal-header">
            <h3 class="modal-title">收银台设置（本店独立）</h3>
            <button class="modal-close" onclick="closeModal('posModal')">&times;</button>
        </div>
        <form onsubmit="savePosSettings(event)">
            <input type="hidden" id="posShopId">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div class="form-group">
                    <label class="form-label">启用收银台</label>
                    <input type="checkbox" id="posEnabled" style="width:18px;height:18px;accent-color:var(--primary);">
                </div>
                <div class="form-group">
                    <label class="form-label">隐藏价格</label>
                    <input type="checkbox" id="posHidePrice" style="width:18px;height:18px;accent-color:var(--primary);">
                </div>
                <div class="form-group">
                    <label class="form-label">加价比例（如 1.8）</label>
                    <input type="number" class="form-input" id="posRatio" step="0.01" min="0.01">
                </div>
                <div class="form-group">
                    <label class="form-label">屏保秒数</label>
                    <input type="number" class="form-input" id="posSsSec" min="5">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">屏保图片 <button type="button" class="btn btn-sm btn-outline" onclick="document.getElementById('posSsFile').click()">上传</button></label>
                <input type="file" id="posSsFile" accept="image/*" style="display:none;" data-poskey="pos_screensaver_img" data-prev="posSsPrev" data-url="posSsUrl">
                <input type="text" class="form-input" id="posSsUrl" placeholder="图片URL（上传或粘贴）">
                <img id="posSsPrev" style="max-height:70px;margin-top:6px;display:none;border-radius:6px;">
            </div>
            <div class="form-group">
                <label class="form-label">微信收款码 <button type="button" class="btn btn-sm btn-outline" onclick="document.getElementById('posQrWxFile').click()">上传</button></label>
                <input type="file" id="posQrWxFile" accept="image/*" style="display:none;" data-poskey="offline_pay_qr_wx" data-prev="posQrWxPrev" data-url="posQrWxUrl">
                <input type="text" class="form-input" id="posQrWxUrl" placeholder="收款码URL">
                <img id="posQrWxPrev" style="max-height:60px;margin-top:6px;display:none;">
            </div>
            <div class="form-group">
                <label class="form-label">支付宝收款码 <button type="button" class="btn btn-sm btn-outline" onclick="document.getElementById('posQrAliFile').click()">上传</button></label>
                <input type="file" id="posQrAliFile" accept="image/*" style="display:none;" data-poskey="offline_pay_qr_ali" data-prev="posQrAliPrev" data-url="posQrAliUrl">
                <input type="text" class="form-input" id="posQrAliUrl" placeholder="收款码URL">
                <img id="posQrAliPrev" style="max-height:60px;margin-top:6px;display:none;">
            </div>
            <div class="form-group">
                <label class="form-label">店员模式密码 <span id="posPwdState" style="font-size:12px;color:var(--text-tertiary);margin-left:8px;"></span></label>
                <input type="password" class="form-input" id="posPwd" placeholder="留空 = 不修改">
            </div>
            <div style="display:flex; gap:10px; margin-top:16px;">
                <button type="submit" class="btn btn-primary" style="flex:1;">保存</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('posModal')">取消</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
const IS_SUPER = <?= $isSuper ? 'true' : 'false' ?>;
const STORE_ID = <?= $storeId ? (int)$storeId : 'null' ?>;

async function loadShops() {
    try {
        const url = '../api/list_shops.php' + (IS_SUPER && STORE_ID ? '?store_id=' + STORE_ID : '');
        const res = await fetch(url);
        const data = await res.json();
        if (!data.success) throw new Error(data.error);
        const shops = data.data.shops || [];
        window._shopsData = shops;
        const tbody = document.getElementById('shopsList');
        if (!shops.length) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--text-tertiary);padding:40px;">该集团下暂无店铺，先新建 A店/B店</td></tr>';
            return;
        }
        tbody.innerHTML = shops.map(s => `
            <tr>
                <td>${s.id}</td>
                <td><strong>${s.name}</strong></td>
                <td>
                    <code style="font-size:15px; letter-spacing:2px; background:var(--bg-hover); padding:3px 8px; border-radius:4px;">${s.pos_code || '待生成'}</code>
                    <button class="btn btn-sm btn-outline" onclick="copyPosLink(${s.id}, '${s.pos_code || ''}')">复制</button>
                    <button class="btn btn-sm btn-outline" onclick="resetPosCode(${s.id}, '${s.name.replace(/'/g, "\\'")}')" style="color:var(--warning);border-color:var(--warning);">重置</button>
                </td>
                <td>${s.remark || '-'}</td>
                <td>${s.created_at || '-'}</td>
                <td>
                    <button class="btn btn-sm btn-outline" onclick="openPosSettings(${s.id})">POS设置</button>
                    <button class="btn btn-sm btn-outline" onclick="openEditModal(${s.id})">编辑</button>
                    <button class="btn btn-sm btn-outline" style="color:var(--danger);border-color:var(--danger);" onclick="deleteShop(${s.id}, '${s.name.replace(/'/g, "\\'")}')">删除</button>
                </td>
            </tr>
        `).join('');
    } catch (e) {
        document.getElementById('shopsList').innerHTML =
            '<tr><td colspan="6" style="text-align:center;color:var(--danger);padding:40px;">加载失败：' + e.message + '</td></tr>';
    }
}

function copyPosLink(shopId, code) {
    if (!code) { alert('收银台码尚未生成，请先重置'); return; }
    const url = location.origin + '/admin/pos.php?c=' + code;
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(() => alert('收银台链接已复制：' + url)).catch(() => prompt('复制收银台链接：', url));
    } else {
        prompt('复制收银台链接：', url);
    }
}

async function resetPosCode(shopId, shopName) {
    if (!confirm(`确定重置「${shopName}」的收银台码？旧码立即失效，需要把新码发给该店。`)) return;
    try {
        const res = await fetch('../api/reset_pos_code.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({shop_id: shopId})
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.error || '重置失败');
        loadShops();
        alert(data.message);
    } catch (err) {
        alert(err.message);
    }
}

function closeModal(id) { document.getElementById(id).classList.remove('show'); }
function showModal(id) { document.getElementById(id).classList.add('show'); }

function openCreateModal() {
    document.getElementById('shopName').value = '';
    document.getElementById('shopRemark').value = '';
    showModal('createModal');
}

async function createShop(e) {
    e.preventDefault();
    try {
        const res = await fetch('../api/create_shop.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                store_id: IS_SUPER ? STORE_ID : null,
                name: document.getElementById('shopName').value.trim(),
                remark: document.getElementById('shopRemark').value.trim()
            })
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.error || '创建失败');
        closeModal('createModal');
        loadShops();
        alert(data.message);
    } catch (err) {
        alert(err.message);
    }
}

async function openEditModal(shopId) {
    try {
        const res = await fetch('../api/list_shops.php');
        const data = await res.json();
        const s = (data.data?.shops || []).find(x => x.id === shopId);
        if (!s) throw new Error('店铺不存在');
        document.getElementById('editShopId').value = s.id;
        document.getElementById('editShopName').value = s.name;
        document.getElementById('editShopRemark').value = s.remark || '';
        showModal('editModal');
    } catch (err) {
        alert(err.message);
    }
}

async function updateShop(e) {
    e.preventDefault();
    try {
        const res = await fetch('../api/update_shop.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                shop_id: parseInt(document.getElementById('editShopId').value),
                name: document.getElementById('editShopName').value.trim(),
                remark: document.getElementById('editShopRemark').value.trim()
            })
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.error || '保存失败');
        closeModal('editModal');
        loadShops();
        alert(data.message);
    } catch (err) {
        alert(err.message);
    }
}

async function deleteShop(shopId, shopName) {
    if (!confirm(`确定删除店铺「${shopName}」？仅允许删除没有任何账号和业务数据的空店。`)) return;
    try {
        const res = await fetch('../api/delete_shop.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({shop_id: shopId})
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.error || '删除失败');
        loadShops();
        alert(data.message);
    } catch (err) {
        alert(err.message);
    }
}

async function enterStore() {
    const storeId = parseInt(document.getElementById('pickStore').value) || 0;
    if (!storeId) { alert('请先选择集团'); return; }
    try {
        const res = await fetch('../api/switch_store.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({store_id: storeId})
        });
        const data = await res.json();
        if (data.success) window.location.reload();
        else alert(data.error || '切换失败');
    } catch (e) { alert('切换失败'); }
}

function posAssetUrl(p) {
    if (!p) return '';
    if (/^https?:/i.test(p)) return p;
    return location.origin + '/' + String(p).replace(/^\.\.\//, '');
}

function showPrev(prevId, urlId) {
    const prev = document.getElementById(prevId);
    const val = document.getElementById(urlId).value;
    if (prev && val) { prev.src = posAssetUrl(val); prev.style.display = ''; }
}

function openPosSettings(shopId) {
    const s = (window._shopsData || []).find(x => x.id === shopId);
    if (!s) { alert('店铺数据未加载'); return; }
    document.getElementById('posShopId').value = s.id;
    document.getElementById('posEnabled').checked = (s.pos_enabled === null || s.pos_enabled === undefined ? 1 : s.pos_enabled) == 1;
    document.getElementById('posHidePrice').checked = (s.pos_hide_price || 0) == 1;
    document.getElementById('posRatio').value = (s.offline_price_ratio !== null && s.offline_price_ratio !== undefined) ? parseFloat(s.offline_price_ratio) : '';
    document.getElementById('posSsSec').value = s.pos_screensaver_sec || 30;
    document.getElementById('posSsUrl').value = s.pos_screensaver_img || '';
    document.getElementById('posQrWxUrl').value = s.offline_pay_qr_wx || '';
    document.getElementById('posQrAliUrl').value = s.offline_pay_qr_ali || '';
    document.getElementById('posPwd').value = '';
    document.getElementById('posPwdState').textContent = s.offline_staff_pwd_set ? '（已设置）' : '（未设置）';
    showPrev('posSsPrev', 'posSsUrl');
    showPrev('posQrWxPrev', 'posQrWxUrl');
    showPrev('posQrAliPrev', 'posQrAliUrl');
    showModal('posModal');
}

document.querySelectorAll('#posModal input[type=file]').forEach(inp => {
    inp.addEventListener('change', function () {
        if (!this.files || !this.files[0]) return;
        const prev = document.getElementById(this.dataset.prev);
        const urlEl = document.getElementById(this.dataset.url);
        const fd = new FormData();
        fd.append('image', this.files[0]);
        fetch('../api/upload_image.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (!data.success) { alert('上传失败: ' + (data.error || '未知错误')); return; }
                const url = data.data.url;
                if (urlEl) urlEl.value = url;
                if (prev) { prev.src = posAssetUrl(url); prev.style.display = ''; }
            })
            .catch(() => alert('上传失败'));
    });
});

async function savePosSettings(e) {
    e.preventDefault();
    const shopId = parseInt(document.getElementById('posShopId').value);
    const body = {
        shop_id: shopId,
        offline_price_ratio: document.getElementById('posRatio').value,
        offline_pay_qr_wx: document.getElementById('posQrWxUrl').value.trim(),
        offline_pay_qr_ali: document.getElementById('posQrAliUrl').value.trim(),
        pos_screensaver_img: document.getElementById('posSsUrl').value.trim(),
        pos_screensaver_sec: parseInt(document.getElementById('posSsSec').value) || 30,
        pos_enabled: document.getElementById('posEnabled').checked ? 1 : 0,
        pos_hide_price: document.getElementById('posHidePrice').checked ? 1 : 0
    };
    const pwd = document.getElementById('posPwd').value;
    if (pwd) body.offline_staff_pwd = pwd;
    try {
        const res = await fetch('../api/save_shop_pos_settings.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(body)
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.error || '保存失败');
        closeModal('posModal');
        loadShops();
        alert(data.message);
    } catch (err) {
        alert(err.message);
    }
}

if (IS_SUPER && !STORE_ID) {
    fetch('../api/list_stores.php').then(r => r.json()).then(data => {
        const sel = document.getElementById('pickStore');
        sel.innerHTML = '<option value="">请选择集团</option>' + (data.data?.stores || []).map(s =>
            `<option value="${s.id}">${s.name}</option>`).join('');
    });
}

if (document.getElementById('shopsList')) loadShops();
</script>
</body>
</html>
