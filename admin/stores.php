<?php $pageTitle = '店铺管理'; $currentPage = 'stores'; ?>
<?php require_once __DIR__ . '/layout.php'; ?>

<div class="page-header">
    <h1>🏪 店铺管理</h1>
    <p>查看和管理所有注册店铺</p>
</div>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>店铺名称</th>
                <th>条码前缀</th>
                <th>创建时间</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody id="storesList">
            <tr><td colspan="5" style="text-align:center;color:var(--text-tertiary);padding:40px;">加载中...</td></tr>
        </tbody>
    </table>
</div>

<script>
async function loadStores() {
    try {
        const res = await fetch('../api/list_stores.php');
        const data = await res.json();
        if (!data.success) {
            document.getElementById('storesList').innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--danger);padding:40px;">加载失败</td></tr>';
            return;
        }
        const stores = data.data.stores || [];
        const tbody = document.getElementById('storesList');
        if (!stores.length) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--text-tertiary);padding:40px;">暂无店铺</td></tr>';
            return;
        }
        tbody.innerHTML = stores.map(s => `
            <tr>
                <td>${s.id}</td>
                <td><strong>${s.name}</strong></td>
                <td><code style="background:var(--bg-hover);padding:4px 8px;border-radius:4px;">${s.barcode_prefix}</code></td>
                <td>${s.created_at || '-'}</td>
                <td>
                    <button class="btn btn-sm btn-outline" onclick="deleteStore(${s.id})" style="color:var(--danger);border-color:var(--danger);">删除</button>
                </td>
            </tr>
        `).join('');
    } catch (e) {
        console.error(e);
    }
}

async function deleteStore(storeId) {
    if (!confirm('确定要删除这个店铺吗？\n该操作会删除店铺所有数据！')) return;
    try {
        const res = await fetch('../api/delete_store.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({store_id: storeId})
        });
        const data = await res.json();
        if (data.success) {
            alert('删除成功');
            loadStores();
        } else {
            alert(data.error || '删除失败');
        }
    } catch (e) {
        alert('删除失败');
    }
}

loadStores();
</script>
</body>
</html>
