<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>批量生成拼音首字母</title>
<script src="assets/pinyin-pro.min.js"></script>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    background: #f5f5f7;
    padding: 30px;
    color: #1d1d1f;
}
h1 { font-size: 24px; margin-bottom: 20px; }
.toolbar { margin-bottom: 20px; display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
.btn {
    padding: 8px 20px; border: none; border-radius: 8px; font-size: 14px;
    cursor: pointer; font-weight: 500; transition: all 0.2s;
}
.btn-primary { background: #5e5ce6; color: #fff; }
.btn-primary:hover { background: #4b49cc; }
.btn-primary:disabled { background: #aaa; cursor: not-allowed; }
.btn-success { background: #34d399; color: #fff; }
.btn-success:hover { background: #2bbf8a; }
.btn-danger { background: #f87171; color: #fff; }
.btn-danger:hover { background: #e06060; }
.stats { font-size: 14px; color: #666; }
table {
    width: 100%; border-collapse: collapse; background: #fff;
    border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}
th, td { padding: 10px 14px; text-align: left; border-bottom: 1px solid #eee; font-size: 14px; }
th { background: #fafafa; font-weight: 600; color: #555; }
tr:hover { background: #fafaff; }
tr.done { background: #f0fdf4; }
tr.done td { color: #999; }
.name-cell { max-width: 260px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.initials-cell { font-family: Monaco, monospace; font-size: 13px; }
.status-cell { font-size: 12px; }
.status-ok { color: #34d399; }
.status-pending { color: #f59e0b; }
.status-error { color: #f87171; }
.progress-bar {
    height: 4px; background: #e5e5e5; border-radius: 2px; margin-bottom: 16px; overflow: hidden;
}
.progress-bar .fill {
    height: 100%; background: #5e5ce6; border-radius: 2px; transition: width 0.3s; width: 0%;
}
.check-all { display: flex; align-items: center; gap: 6px; font-size: 14px; }
.check-all input { width: 16px; height: 16px; }
</style>
</head>
<body>

<h1>批量生成拼音首字母</h1>
<p style="color:#666; margin-bottom:16px;">自动根据商品名称生成拼音首字母（如"小王子"→"xwz"），供直播页面快速搜索使用。</p>

<div class="progress-bar"><div class="fill" id="progressFill"></div></div>

<div class="toolbar">
    <button class="btn btn-primary" id="generateBtn" disabled>⚡ 生成选中项的拼音</button>
    <button class="btn btn-success" id="saveBtn" disabled>💾 保存到数据库</button>
    <span class="stats" id="stats">加载中...</span>
</div>

<div class="check-all">
    <input type="checkbox" id="checkAll" checked>
    <label for="checkAll">全选/取消</label>
</div>

<table>
<thead>
<tr>
    <th style="width:30px;"></th>
    <th style="width:60px;">ID</th>
    <th>商品名称</th>
    <th style="width:160px;">拼音首字母</th>
    <th style="width:80px;">状态</th>
</tr>
</thead>
<tbody id="productList"></tbody>
</table>

<script>
const { pinyin } = window.pinyinPro;
let products = [];
let generatedData = {}; // { productId: 'initials' }

async function loadProducts() {
    try {
        const res = await fetch('../api/list_products.php');
        const data = await res.json();
        if (data.success && data.data && data.data.products) {
            products = data.data.products;
            renderTable();
            document.getElementById('stats').textContent =
                `共 ${products.length} 个商品`;
            document.getElementById('generateBtn').disabled = false;
        } else {
            document.getElementById('stats').textContent = '加载失败：' + (data.error || '未知错误');
            // 尝试直接加载
            loadProductsFallback();
        }
    } catch (e) {
        console.error(e);
        document.getElementById('stats').textContent = '加载失败，尝试备用方式...';
        loadProductsFallback();
    }
}

async function loadProductsFallback() {
    try {
        const res = await fetch('../api/list_products.php?limit=5000');
        const data = await res.json();
        if (data.success && data.data && data.data.products) {
            products = data.data.products;
            renderTable();
            document.getElementById('stats').textContent =
                `共 ${products.length} 个商品`;
            document.getElementById('generateBtn').disabled = false;
        }
    } catch (e) {
        document.getElementById('stats').textContent = '加载失败，请确保服务已启动';
    }
}

function getInitials(name) {
    if (!name) return '';
    try {
        // 取每个字拼音的首字母（零声母字也能拿到 a/o/e 等）
        return pinyin(name, { toneType: 'none' })
            .split(/\s+/)
            .map(s => s[0] || '')
            .join('')
            .replace(/zh/g, 'z')
            .replace(/ch/g, 'c')
            .replace(/sh/g, 's');
    } catch (e) {
        return '';
    }
}

function renderTable() {
    const tbody = document.getElementById('productList');
    tbody.innerHTML = '';
    products.forEach(p => {
        const tr = document.createElement('tr');
        const hasInitials = p.pinyin_initials && p.pinyin_initials.trim() !== '';
        if (hasInitials) tr.classList.add('done');

        tr.innerHTML = `
            <td><input type="checkbox" class="product-check" data-id="${p.id}" ${hasInitials ? '' : 'checked'}></td>
            <td>${p.id}</td>
            <td class="name-cell" title="${escapeHtml(p.name)}">${escapeHtml(p.name)}</td>
            <td class="initials-cell" id="initials-${p.id}">${hasInitials ? escapeHtml(p.pinyin_initials) : '<span style="color:#ccc;">待生成</span>'}</td>
            <td class="status-cell" id="status-${p.id}">
                ${hasInitials ? '<span class="status-ok">✓ 已有</span>' : '<span class="status-pending">⏳ 待生成</span>'}
            </td>
        `;
        tbody.appendChild(tr);
    });
    updateProgress();
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

document.getElementById('generateBtn').addEventListener('click', function() {
    const checked = document.querySelectorAll('.product-check:checked');
    if (checked.length === 0) return alert('请先选择要生成的商品');

    generatedData = {};
    let count = 0;
    checked.forEach(cb => {
        const id = parseInt(cb.dataset.id);
        const product = products.find(p => p.id === id);
        if (!product) return;
        const initials = getInitials(product.name);
        generatedData[id] = initials;
        document.getElementById(`initials-${id}`).textContent = initials || '（无）';
        document.getElementById(`status-${id}`).innerHTML =
            initials ? '<span class="status-ok">✓ 已生成</span>' : '<span class="status-error">⚠ 生成失败</span>';
        count++;
    });

    document.getElementById('stats').textContent =
        `已生成 ${count} 个商品的拼音首字母`;
    document.getElementById('saveBtn').disabled = false;
    updateProgress();
});

document.getElementById('saveBtn').addEventListener('click', async function() {
    const ids = Object.keys(generatedData);
    if (ids.length === 0) return alert('请先生成拼音首字母');

    this.disabled = true;
    this.textContent = '保存中...';
    let success = 0, fail = 0;

    for (const id of ids) {
        try {
            const res = await fetch('../api/update_pinyin_initials.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    product_id: parseInt(id),
                    pinyin_initials: generatedData[id]
                })
            });
            const data = await res.json();
            if (data.success) {
                success++;
                document.getElementById(`status-${id}`).innerHTML = '<span class="status-ok">✓ 已保存</span>';
                document.querySelector(`.product-check[data-id="${id}"]`).disabled = true;
            } else {
                fail++;
                document.getElementById(`status-${id}`).innerHTML = `<span class="status-error">✗ 保存失败</span>`;
            }
        } catch (e) {
            fail++;
            document.getElementById(`status-${id}`).innerHTML = `<span class="status-error">✗ 网络错误</span>`;
        }
    }

    this.textContent = '💾 保存到数据库';
    document.getElementById('stats').textContent =
        `保存完成：成功 ${success} 个，失败 ${fail} 个`;
    updateProgress();

    if (fail === 0) {
        this.disabled = true;
    }
});

document.getElementById('checkAll').addEventListener('change', function() {
    document.querySelectorAll('.product-check').forEach(cb => {
        if (!cb.disabled) cb.checked = this.checked;
    });
});

document.getElementById('productList').addEventListener('change', function(e) {
    if (e.target.classList.contains('product-check')) {
        // 取消全选如果有未选的
        const all = document.querySelectorAll('.product-check:not(:disabled)');
        const checked = document.querySelectorAll('.product-check:checked:not(:disabled)');
        document.getElementById('checkAll').checked = all.length === checked.length;
    }
});

function updateProgress() {
    const all = document.querySelectorAll('.product-check').length;
    const done = document.querySelectorAll('.product-check:disabled').length;
    const pct = all > 0 ? (done / all * 100) : 0;
    document.getElementById('progressFill').style.width = pct + '%';
}

loadProducts();
</script>
</body>
</html>
