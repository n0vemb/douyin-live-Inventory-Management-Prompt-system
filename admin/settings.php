<?php $pageTitle = '系统配置'; $currentPage = 'settings'; ?>
<?php require_once __DIR__ . '/../auth.php'; requireNonOperator(); ?>
<?php require_once __DIR__ . '/layout.php'; ?>
<?php
$isSuperAdmin = ($currentUser['role'] ?? '') === 'super_admin';
$stShopId = getShopId();
$stShopName = getShopName();
$stIsShop = !empty($stShopId);
?>

<style>
.config-section {
    display: grid;
    grid-template-columns: 1fr;
    gap: 20px;
}

.element-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.element-card {
    background: var(--bg-surface);
    border: 2px solid var(--border);
    border-radius: 12px;
    padding: 20px;
    cursor: pointer;
    transition: all 0.2s;
}

.element-card:hover {
    border-color: var(--primary);
    transform: translateY(-2px);
}

.element-card.selected {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.element-card.disabled {
    opacity: 0.5;
}

.element-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.element-card-title {
    font-weight: 600;
    font-size: 16px;
    color: var(--text);
    display: flex;
    align-items: center;
    gap: 8px;
}

.element-card-icon {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--primary-light);
    border-radius: 8px;
    color: var(--primary);
    font-size: 16px;
}

.element-card-preview {
    font-size: 13px;
    color: var(--text-secondary);
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid var(--border);
}

.element-card-preview span {
    display: inline-block;
    margin-right: 10px;
    color: var(--text-tertiary);
}

.config-panel {
    background: var(--bg-surface);
    border: 2px solid var(--border);
    border-radius: 12px;
    padding: 25px;
}

.config-panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--border);
}

.config-panel-title {
    font-size: 18px;
    font-weight: 600;
    color: var(--text);
}

.config-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
}

.config-item {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.config-item.full-width {
    grid-column: 1 / -1;
}

.fine-tune-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
    background: var(--bg-hover);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 10px;
    margin-bottom: 15px;
}

.fine-tune-btn {
    border: 1px solid var(--text-tertiary);
    background: transparent;
    border-radius: 8px;
    padding: 6px 10px;
    cursor: pointer;
    font-size: 13px;
    color: var(--text-secondary);
    transition: all 0.15s;
}
.fine-tune-btn:hover {
    border-color: var(--primary);
    color: var(--primary);
    background: var(--primary-light);
}

.fine-tune-btn:hover {
    border-color: var(--primary);
    color: var(--primary);
}

.config-label {
    font-size: 13px;
    color: var(--text-secondary);
    font-weight: 500;
}

.config-input {
    padding: 10px 12px;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.2s;
}

.config-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.toggle-switch {
    position: relative;
    width: 48px;
    height: 26px;
}

.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: var(--bg-active);
    transition: 0.3s;
    border-radius: 26px;
}

.toggle-slider:before {
    position: absolute;
    content: "";
    height: 20px;
    width: 20px;
    left: 3px;
    bottom: 3px;
    background-color: var(--text);
    transition: 0.3s;
    border-radius: 50%;
}

input:checked + .toggle-slider {
    background-color: var(--primary);
}

input:checked + .toggle-slider:before {
    transform: translateX(22px);
}

.section-hint {
    font-size: 13px;
    color: var(--text-secondary);
    background: var(--info-light);
    padding: 12px 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    border-left: 3px solid var(--primary);
}

.save-bar {
    position: sticky;
    bottom: 20px;
    background: var(--bg-surface);
    border-radius: 12px;
    padding: 15px 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: var(--shadow);
    margin-top: 20px;
}

.save-status {
    font-size: 14px;
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    gap: 8px;
}

.save-status.saving {
    color: var(--warning);
}

.save-status.saved {
    color: var(--success);
}

.condition-type-row {
    display: flex;
    gap: 10px;
    align-items: center;
    margin-bottom: 10px;
    padding: 10px;
    background: var(--bg-hover);
    border-radius: 8px;
}

.condition-type-row input[type="text"] {
    flex: 1;
    padding: 10px 12px;
    border: 1px solid var(--border);
    border-radius: 6px;
    font-size: 14px;
}

.condition-type-row input[type="color"] {
    width: 50px;
    height: 40px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
}
</style>

<div class="page-header">
    <h1><?= $isSuperAdmin ? '平台设置' : '店铺设置' ?></h1>
    <p><?= $isSuperAdmin ? '配置平台名称和Logo' : '配置店铺名称、Logo、SKU和直播页面布局' ?></p>
</div>

<div class="card">
    <h3 class="card-title"><?= $isSuperAdmin ? '平台名称' : '店铺名称' ?></h3>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label"><?= $isSuperAdmin ? '系统名称' : '店铺名称' ?></label>
            <input type="text" id="systemName" class="form-input" placeholder="<?= $isSuperAdmin ? '系统名称' : '输入店铺名称' ?>">
        </div>
        <div class="form-group">
            <label class="form-label">Logo</label>
            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <input type="file" id="logoFile" accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml" style="display:none;">
                <button class="btn btn-secondary btn-sm" onclick="document.getElementById('logoFile').click()">📁 上传</button>
                <input type="text" id="logoUrl" class="form-input" placeholder="或输入图片URL" style="flex:1; min-width:120px;">
                <button class="btn btn-secondary btn-sm" onclick="applyLogoUrl()">应用</button>
            </div>
            <div id="logoPreviewGroup" style="display:none; margin-top:8px;">
                <div style="display:flex; align-items:center; gap:10px; padding:8px 12px; background:var(--bg-hover); border-radius:6px; border:1px solid var(--border);">
                    <img id="logoPreview" style="max-height:32px; max-width:120px; object-fit:contain;">
                    <button class="btn btn-sm btn-danger" onclick="clearLogo()">清除</button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!$isSuperAdmin): ?>
<div class="card">
    <h3 class="card-title">SKU 管理</h3>
    <div id="conditionTypesContainer"></div>
    <button class="btn btn-secondary" onclick="addConditionType()">+ 添加 SKU</button>
</div>

<div class="card">
    <h3 class="card-title">财务设置</h3>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label">快递费 (元/单，含在售价中)</label>
            <input type="number" id="shippingFee" class="form-input" step="0.01" min="0" placeholder="3.00"
                onchange="tempSettings.shipping_fee = parseFloat(this.value) || 0; markChanged();">
        </div>
        <div class="form-group">
            <label class="form-label">实际快递成本 (元/单)</label>
            <input type="number" id="actualShippingFee" class="form-input" step="0.01" min="0" placeholder="3.00"
                onchange="tempSettings.actual_shipping_fee = parseFloat(this.value) || 0; markChanged();">
            <span style="font-size:11px; color:var(--text-tertiary);">利润公式按此成本计算，与售价中含的快递费解耦</span>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label">平台抽成率</label>
            <input type="number" id="platformFeeRate" class="form-input" step="0.0001" min="0" max="1" placeholder="0.05"
                onchange="tempSettings.platform_fee_rate = parseFloat(this.value) || 0; markChanged();">
            <span style="font-size:11px; color:var(--text-tertiary);">例如 0.05 表示 5%</span>
        </div>
    </div>
</div>

<div class="card">
    <h3 class="card-title">线下收银台<?= $stIsShop ? '（当前店铺：' . htmlspecialchars($stShopName) . '）' : '' ?></h3>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label">收银台开关</label>
            <label style="display:flex; align-items:center; gap:8px; cursor:pointer; margin-top:6px;">
                <input type="checkbox" id="posEnabled" style="width:18px; height:18px;"
                    onchange="tempSettings.pos_enabled = this.checked ? 1 : 0; markChanged();">
                <span style="font-size:13.5px; color:var(--text-secondary);">开启线下收银台（关闭后访问收银台提示"暂未开启"）</span>
            </label>
        </div>
        <div class="form-group">
            <label class="form-label">隐藏价格</label>
            <label style="display:flex; align-items:center; gap:8px; cursor:pointer; margin-top:6px;">
                <input type="checkbox" id="posHidePrice" style="width:18px; height:18px;"
                    onchange="tempSettings.pos_hide_price = this.checked ? 1 : 0; markChanged();">
                <span style="font-size:13.5px; color:var(--text-secondary);">开启后收银台前端所有价格显示 0.00</span>
            </label>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group" style="flex:1">
            <label class="form-label">顶部滚动广告词（一行一条，可多条轮播）</label>
            <textarea id="posAdLines" class="form-input" rows="3" placeholder="例：&#10;本店满 100 元可参与幸运转盘抽奖&#10;关注店铺进群，每周上新"
                style="resize:vertical; font-size:13.5px; line-height:1.5;"
                oninput="tempSettings.pos_ad_lines = this.value; markChanged();"></textarea>
            <span style="font-size:11px; color:var(--text-tertiary);">显示在收银台顶部栏，自动横向滚动；留空则不显示</span>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label">静止进入屏保（秒）</label>
            <input type="number" id="posSsSec" class="form-input" step="1" min="5" placeholder="30"
                onchange="tempSettings.pos_screensaver_sec = parseInt(this.value) || 30; markChanged();">
            <span style="font-size:11px; color:var(--text-tertiary);">顾客触摸屏静止 N 秒后自动进入屏保，0 或未设图片则不启用</span>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group" style="flex:1">
            <label class="form-label">屏保图片（竖屏建议 9:16，撑满整个屏幕）</label>
            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <input type="file" id="ssImgFile" accept="image/*" style="display:none;">
                <button class="btn btn-secondary btn-sm" onclick="document.getElementById('ssImgFile').click()">上传</button>
                <input type="text" id="ssImgUrl" class="form-input" placeholder="或输入图片URL" style="flex:1; min-width:120px;"
                    onchange="tempSettings.pos_screensaver_img = this.value; markChanged();">
                <button class="btn btn-secondary btn-sm" onclick="tempSettings.pos_screensaver_img=''; document.getElementById('ssImgUrl').value=''; document.getElementById('ssImgPreview').style.display='none'; markChanged();">清除</button>
                <img id="ssImgPreview" style="max-height:48px; border-radius:6px; display:none;">
            </div>
            <span style="font-size:11px; color:var(--text-tertiary);">设置了图片后，进入收银台页面自动进入屏保，点击图片进入收银台</span>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label">加价比例（售价 = 最高在库进价 × 比例）</label>
            <input type="number" id="offlineRatio" class="form-input" step="0.01" min="0" placeholder="1.80"
                onchange="tempSettings.offline_price_ratio = (this.value === '' || isNaN(parseFloat(this.value))) ? 1.8 : parseFloat(this.value); markChanged();">
            <span style="font-size:11px; color:var(--text-tertiary);">仅服务端用于算价，收银台页面不显示；<b>填 0 则收银台价格全部显示 0.00（隐藏价格）</b></span>
        </div>
        <div class="form-group">
            <label class="form-label">店员模式密码 <span id="staffPwdState" style="font-size:11px;color:var(--text-tertiary)"></span></label>
            <input type="password" id="offlineStaffPwd" class="form-input" placeholder="留空则不修改" autocomplete="new-password"
                onchange="if(this.value) tempSettings.offline_staff_pwd = this.value; markChanged();">
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label">微信收款码</label>
            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <input type="file" id="qrWxFile" accept="image/*" style="display:none;">
                <button class="btn btn-secondary btn-sm" onclick="document.getElementById('qrWxFile').click()">上传</button>
                <input type="text" id="qrWxUrl" class="form-input" placeholder="或输入图片URL" style="flex:1; min-width:120px;"
                    onchange="tempSettings.offline_pay_qr_wx = this.value; markChanged();">
                <img id="qrWxPreview" style="max-height:36px; border-radius:6px; display:none;">
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">支付宝收款码</label>
            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <input type="file" id="qrAliFile" accept="image/*" style="display:none;">
                <button class="btn btn-secondary btn-sm" onclick="document.getElementById('qrAliFile').click()">上传</button>
                <input type="text" id="qrAliUrl" class="form-input" placeholder="或输入图片URL" style="flex:1; min-width:120px;"
                    onchange="tempSettings.offline_pay_qr_ali = this.value; markChanged();">
                <img id="qrAliPreview" style="max-height:36px; border-radius:6px; display:none;">
            </div>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group" style="flex:1">
            <label class="form-label">店内收银台码（店员设备用，有店员能力）</label>
            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-bottom:10px;">
                <input type="text" id="posCode" class="form-input" readonly
                    style="width:150px; background:var(--bg-hover); font-size:18px; letter-spacing:3px; text-align:center;">
                <button class="btn btn-secondary btn-sm" onclick="copyPosCode()">复制码</button>
            </div>
            <label class="form-label">收银台访问链接（店内一体机/平板，把码显示给顾客扫）</label>
            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <input type="text" id="posLink" class="form-input" readonly style="flex:1; min-width:220px; background:var(--bg-hover);">
                <button class="btn btn-secondary btn-sm" onclick="copyPosLink()">复制</button>
                <button class="btn btn-secondary btn-sm" onclick="resetPosToken()">重置链接</button>
            </div>
            <span style="font-size:11px; color:var(--text-tertiary);"><?= $stIsShop ? '重置后本店 8 位码与链接立即更新，旧码失效。' : '集团视角重置的是默认店旧链接；各店 8 位码请到「店管理」查看/重置。' ?></span>
            <label class="form-label" style="margin-top:14px;">顾客自助码（发顾客 / 印二维码；只有下单付款等自助能力）</label>
            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-bottom:10px;">
                <input type="text" id="posCustCode" class="form-input" readonly
                    style="width:150px; background:var(--bg-hover); font-size:18px; letter-spacing:3px; text-align:center;">
                <button class="btn btn-secondary btn-sm" onclick="copyPosCustCode()">复制码</button>
                <button class="btn btn-secondary btn-sm" onclick="resetPosCustomerCode()">重置自助码</button>
            </div>
            <label class="form-label">顾客自助下单链接</label>
            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <input type="text" id="posCustLink" class="form-input" readonly style="flex:1; min-width:220px; background:var(--bg-hover);">
                <button class="btn btn-secondary btn-sm" onclick="copyPosCustLink()">复制</button>
            </div>
            <span style="font-size:11px; color:var(--text-tertiary);">身份由「用的是哪个码」决定：拿顾客码进来的人只能自助下单付款（没有「人工确认已收款」等店员能力），改链接参数也提不了权，所以可以放心发给顾客。顾客自己拿着手机时，请用这条链接。</span>
        </div>
    </div>
</div>

<div class="card">
    <h3 class="card-title">直播页面配置</h3>
    <div class="section-hint">
        💡 在浏览器中同时打开直播页面，调整配置后会实时更新！
    </div>
    
    <div class="element-list" id="elementList"></div>
    
    <div class="config-panel" id="configPanel" style="display: none;">
        <div class="config-panel-header">
            <div class="config-panel-title" id="configPanelTitle">元素配置</div>
        </div>

        <div class="fine-tune-bar">
            <span class="config-label">微调步长</span>
            <select id="nudgeStep" class="config-input" style="width:90px;" onchange="onNudgeStepChange()">
                <option value="1">1px</option>
                <option value="5" selected>5px</option>
                <option value="10">10px</option>
                <option value="20">20px</option>
            </select>
            <button type="button" class="fine-tune-btn" onclick="nudgeElement('up')">↑ 上移</button>
            <button type="button" class="fine-tune-btn" onclick="nudgeElement('down')">↓ 下移</button>
            <button type="button" class="fine-tune-btn" onclick="nudgeElement('left')">← 左移</button>
            <button type="button" class="fine-tune-btn" onclick="nudgeElement('right')">→ 右移</button>
            <button type="button" class="fine-tune-btn" onclick="resizeElement('wider')">↔ 加宽</button>
            <button type="button" class="fine-tune-btn" onclick="resizeElement('narrower')">↔ 变窄</button>
            <button type="button" class="fine-tune-btn" onclick="resizeElement('taller')">↕ 增高</button>
            <button type="button" class="fine-tune-btn" onclick="resizeElement('shorter')">↕ 降低</button>
        </div>
        
        <div class="config-grid">
            <div class="config-item">
                <div class="config-label">显示</div>
                <label class="toggle-switch">
                    <input type="checkbox" id="configEnabled" onchange="updateElementConfig()">
                    <span class="toggle-slider"></span>
                </label>
            </div>
            <div class="config-item">
                <label class="config-label">左 (px)</label>
                <input type="number" class="config-input" id="configLeft" oninput="updateElementConfig()" min="0">
            </div>
            <div class="config-item">
                <label class="config-label">上 (px)</label>
                <input type="number" class="config-input" id="configTop" oninput="updateElementConfig()" min="0">
            </div>
            <div class="config-item">
                <label class="config-label">宽度 (px)</label>
                <input type="number" class="config-input" id="configWidth" oninput="updateElementConfig()" min="10">
            </div>
            <div class="config-item">
                <label class="config-label">高度 (px)</label>
                <input type="number" class="config-input" id="configHeight" oninput="updateElementConfig()" min="10">
            </div>
            <div class="config-item" id="configFontSizeWrapper">
                <label class="config-label">字号</label>
                <input type="text" class="config-input" id="configFontSize" oninput="updateElementConfig()" placeholder="72px">
            </div>
            <div class="config-item">
                <label class="config-label">层级</label>
                <input type="number" class="config-input" id="configZIndex" oninput="updateElementConfig()" min="1" value="1">
            </div>
            <div class="config-item" id="configItemSpacingWrapper" style="display: none;">
                <label class="config-label">价格项间距 (px)</label>
                <input type="number" class="config-input" id="configItemSpacing" oninput="updateElementConfig()" min="0">
            </div>
            <div class="config-item" id="configColorWrapper" style="display: none;">
                <label class="config-label">文字颜色</label>
                <input type="color" class="config-input" id="configColor" onchange="updateElementConfig()" style="height:42px; padding:4px;">
            </div>
            <div class="config-item" id="configStatusFontSizeWrapper" style="display: none;">
                <label class="config-label">状态字号</label>
                <input type="text" class="config-input" id="configStatusFontSize" oninput="updateElementConfig()" placeholder="28px">
            </div>
            <div class="config-item" id="configStatusColorWrapper" style="display: none;">
                <label class="config-label">状态颜色</label>
                <input type="color" class="config-input" id="configStatusColor" onchange="updateElementConfig()" style="height:42px; padding:4px;">
            </div>
            <div class="config-item" id="configPriceFontSizeWrapper" style="display: none;">
                <label class="config-label">价格字号</label>
                <input type="text" class="config-input" id="configPriceFontSize" oninput="updateElementConfig()" placeholder="46px">
            </div>
            <div class="config-item" id="configPriceColorWrapper" style="display: none;">
                <label class="config-label">价格颜色</label>
                <input type="color" class="config-input" id="configPriceColor" onchange="updateElementConfig()" style="height:42px; padding:4px;">
            </div>
            <div class="config-item" id="configPriceOffsetWrapper" style="display: none;">
                <label class="config-label">价格偏移 (px)</label>
                <input type="number" class="config-input" id="configPriceOffset" oninput="updateElementConfig()" min="-500" max="500" value="0">
            </div>
            <div class="config-item" id="configStockOffsetWrapper" style="display: none;">
                <label class="config-label">库存偏移 (px)</label>
                <input type="number" class="config-input" id="configStockOffset" oninput="updateElementConfig()" min="-500" max="500" value="0">
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<div class="card">
    <h3 class="card-title">收款方式与易支付配置</h3>
    <div class="section-hint" style="margin-bottom:10px">
        默认「静态收款码」：用「线下收银台」里上传的微信/支付宝收款码，顾客扫完自己点「已付款」。<br>
        改选「易支付」：收银台按订单金额动态生成收款码，收到平台回调才确认收款（顾客无需再点）。<br>
        一个店铺一套易支付商户信息，本店所有门店共用；商户密钥只用于服务端 MD5 签名，保存后不再回显。
    </div>
    <div class="form-row">
        <div class="form-group" style="flex:1">
            <label class="form-label">收款方式</label>
            <label style="display:flex; align-items:center; gap:8px; cursor:pointer; margin-top:6px;">
                <input type="radio" name="posPayMode" value="static" style="width:16px; height:16px;"
                    onchange="tempSettings.pos_pay_mode = 'static'; markChanged();">
                <span style="font-size:13.5px; color:var(--text-secondary);">静态收款码（顾客扫完自己点「已付款」）</span>
            </label>
            <label style="display:flex; align-items:center; gap:8px; cursor:pointer; margin-top:8px;">
                <input type="radio" name="posPayMode" value="epay" style="width:16px; height:16px;"
                    onchange="tempSettings.pos_pay_mode = 'epay'; markChanged();">
                <span style="font-size:13.5px; color:var(--text-secondary);">易支付（动态收款码，收到付款自动确认收款）</span>
            </label>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label">接口地址</label>
            <input type="text" id="epayApiUrl" class="form-input" placeholder="https://www.ezfpy.cn"
                onchange="tempSettings.epay_api_url = this.value.trim(); markChanged();">
        </div>
        <div class="form-group">
            <label class="form-label">商户ID</label>
            <input type="text" id="epayPid" class="form-input" placeholder="例如 1746"
                onchange="tempSettings.epay_pid = this.value.trim(); markChanged();">
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label">商户密钥 <span id="epayKeyState" style="font-size:11px;color:var(--text-tertiary)"></span></label>
            <input type="password" id="epayMchKey" class="form-input" placeholder="留空则不修改" autocomplete="new-password"
                onchange="if(this.value) tempSettings.epay_mch_key = this.value; markChanged();">
            <span style="font-size:11px; color:var(--text-tertiary);">平台后台的「商户密钥」，不是软件通讯密钥</span>
        </div>
        <div class="form-group">
            <label class="form-label">签名方式</label>
            <input type="text" class="form-input" value="MD5" readonly style="background:var(--bg-hover)">
        </div>
    </div>
    <div class="form-row">
        <div class="form-group" style="flex:1">
            <label class="form-label">异步回调地址（下单时自动随单提交，仅作核对）</label>
            <input type="text" id="epayNotifyUrl" class="form-input" readonly style="background:var(--bg-hover)">
        </div>
    </div>
</div>

<div class="save-bar">
    <div class="save-status" id="saveStatus">
        <span>•</span> 未保存修改
    </div>
    <div style="display: flex; gap: 10px;">
        <button class="btn btn-secondary" onclick="resetToSaved()">重置</button>
        <button class="btn btn-primary" onclick="saveSettings()">保存配置</button>
    </div>
</div>

<script>
console.log('Settings page JavaScript loaded');
const elementLabels = {
    'productName': '商品名称',
    'productSeries': '商品系列',
    'commonName': '常用名',
    'suggestedPrice': '参考价',
    'purchasePrice': '进货价',
    'productDescription': '产品介绍',
    'image': '商品图片',
    'condition': '价格列表'
};

const elementIcons = {
    'productName': '🏷️',
    'productSeries': '📦',
    'commonName': '📝',
    'suggestedPrice': '💰',
    'purchasePrice': '💵',
    'productDescription': '📄',
    'image': '🖼️',
    'condition': '📊'
};

const defaultSettings = {
    system_name: '🎪 泡泡玛特进销存',
    condition_types: [
        {key: 'sealed', name: '原盒未拆', color: '#10b981'},
        {key: 'opened', name: '拆盒无瑕', color: '#3b82f6'},
        {key: 'boxless', name: '无盒无瑕', color: '#f59e0b'},
        {key: 'flawed', name: '微瑕', color: '#ef4444'}
    ],
    live_display: {
        elements: [
            {type: 'productName', enabled: true, left: 60, top: 60, width: 900, height: 80, fontSize: '72px', zIndex: 2},
            {type: 'productSeries', enabled: true, left: 60, top: 150, width: 600, height: 60, fontSize: '48px', zIndex: 2},
            {type: 'commonName', enabled: true, left: 60, top: 220, width: 600, height: 80, fontSize: '42px', zIndex: 2},
            {type: 'suggestedPrice', enabled: true, left: 60, top: 310, width: 500, height: 100, fontSize: '72px', zIndex: 2, color: '#e8e8ed'},
            {type: 'purchasePrice', enabled: true, left: 60, top: 420, width: 500, height: 60, fontSize: '28px', zIndex: 2, color: '#9d9daf'},
            {type: 'productDescription', enabled: true, left: 60, top: 430, width: 800, height: 80, fontSize: '32px', zIndex: 2},
            {type: 'image', enabled: true, left: 60, top: 540, width: 600, height: 600, fontSize: '0px', zIndex: 1},
            {type: 'condition', enabled: true, left: 750, top: 450, width: 1100, height: 600, fontSize: '40px', zIndex: 1, itemSpacing: 30, statusFontSize: '28px', statusColor: '#9d9daf', priceFontSize: '46px', priceColor: '#34d399', priceOffsetX: 0, stockOffsetX: 0}
        ]
    },
    shipping_fee: 3.00,
    actual_shipping_fee: 3.00,
    platform_fee_rate: 0.05,
    offline_price_ratio: 1.80,
    offline_staff_pwd_set: false,
    offline_pay_qr_wx: '',
    offline_pay_qr_ali: '',
    pos_enabled: 1,
    pos_screensaver_img: '',
    pos_screensaver_sec: 30,
    pos_hide_price: 0,
    pos_ad_lines: '',
    pos_pay_mode: 'static',
    epay_api_url: 'https://www.ezfpy.cn',
    epay_pid: '',
    epay_sign_type: 'MD5',
    epay_mch_key_set: false,
    epay_mch_key_mask: '',
    pos_customer_code: '',
    pos_token: ''
};

let savedSettings = JSON.parse(JSON.stringify(defaultSettings));
let tempSettings = JSON.parse(JSON.stringify(defaultSettings));
let selectedElementIndex = null;
let nudgeStep = 5;

async function loadSettings() {
    try {
        const res = await fetch('../api/get_settings.php');
        const data = await res.json();
        console.log('loadSettings - response:', data);
        if (data.success && data.settings) {
            savedSettings = JSON.parse(JSON.stringify(data.settings));
            tempSettings = JSON.parse(JSON.stringify(data.settings));

            // 如果 live_display 缺失或 elements 为空，从默认值继承
            if (!tempSettings.live_display || !tempSettings.live_display.elements || tempSettings.live_display.elements.length === 0) {
                if (!tempSettings.live_display) tempSettings.live_display = {};
                tempSettings.live_display.elements = JSON.parse(JSON.stringify(defaultSettings.live_display.elements));
            } else {
                // 确保有 productSeries 元素
                const hasProductSeries = tempSettings.live_display.elements.some(e => e.type === 'productSeries');
                if (!hasProductSeries) {
                    const productNameIndex = tempSettings.live_display.elements.findIndex(e => e.type === 'productName');
                    const productName = tempSettings.live_display.elements[productNameIndex];
                    if (productName) {
                        const productSeries = {
                            type: 'productSeries',
                            enabled: true,
                            left: productName.left,
                            top: productName.top + productName.height + 10,
                            width: 600,
                            height: 60,
                            fontSize: '48px',
                            zIndex: 2
                        };
                        tempSettings.live_display.elements.splice(productNameIndex + 1, 0, productSeries);
                    }
                }

                // 确保有 purchasePrice 元素
                const hasPurchasePrice = tempSettings.live_display.elements.some(e => e.type === 'purchasePrice');
                if (!hasPurchasePrice) {
                    const suggestedIndex = tempSettings.live_display.elements.findIndex(e => e.type === 'suggestedPrice');
                    if (suggestedIndex !== -1) {
                        const sp = tempSettings.live_display.elements[suggestedIndex];
                        const purchasePrice = {
                            type: 'purchasePrice',
                            enabled: true,
                            left: sp.left,
                            top: sp.top + sp.height + 10,
                            width: sp.width,
                            height: 60,
                            fontSize: '28px',
                            zIndex: 2,
                            color: '#9d9daf'
                        };
                        tempSettings.live_display.elements.splice(suggestedIndex + 1, 0, purchasePrice);
                    }
                }
            }

            console.log('loadSettings - loaded settings:', tempSettings);
        }
    } catch(e) {
        console.log('loadSettings error:', e);
    }
    
    applySettings();
}

function applySettings() {
    const isStoreAdmin = <?= $isSuperAdmin ? 'false' : 'true' ?>;
    document.getElementById('systemName').value = isStoreAdmin ? (tempSettings.store_name || '') : (tempSettings.system_name || '');
    if (tempSettings.logo_path) {
        showLogoPreview(tempSettings.logo_path);
    } else {
        document.getElementById('logoPreviewGroup').style.display = 'none';
    }
    // 财务设置
    const sfEl = document.getElementById('shippingFee');
    const asfEl = document.getElementById('actualShippingFee');
    const pfrEl = document.getElementById('platformFeeRate');
    if (sfEl) sfEl.value = parseFloat(tempSettings.shipping_fee ?? 3).toFixed(2);
    if (asfEl) asfEl.value = parseFloat(tempSettings.actual_shipping_fee ?? 3).toFixed(2);
    if (pfrEl) pfrEl.value = parseFloat(tempSettings.platform_fee_rate ?? 0.05).toFixed(4);
    // 线下收银台
    const peEl = document.getElementById('posEnabled');
    if (peEl) peEl.checked = (tempSettings.pos_enabled ?? 1) == 1;
    const hpEl = document.getElementById('posHidePrice');
    if (hpEl) hpEl.checked = (tempSettings.pos_hide_price ?? 0) == 1;
    const adEl = document.getElementById('posAdLines');
    if (adEl) adEl.value = tempSettings.pos_ad_lines || '';
    // 收款方式 + 易支付配置
    const payMode = tempSettings.pos_pay_mode === 'epay' ? 'epay' : 'static';
    document.querySelectorAll('input[name="posPayMode"]').forEach(r => { r.checked = (r.value === payMode); });
    const eauEl = document.getElementById('epayApiUrl');
    if (eauEl) eauEl.value = tempSettings.epay_api_url || 'https://www.ezfpy.cn';
    const epidEl = document.getElementById('epayPid');
    if (epidEl) epidEl.value = tempSettings.epay_pid || '';
    const ekeyEl = document.getElementById('epayMchKey');
    if (ekeyEl) ekeyEl.value = '';
    const ekeyState = document.getElementById('epayKeyState');
    if (ekeyState) {
        ekeyState.textContent = tempSettings.epay_mch_key_set
            ? '（已设置 ' + (tempSettings.epay_mch_key_mask || '') + '）'
            : '（未设置）';
    }
    const enotifyEl = document.getElementById('epayNotifyUrl');
    if (enotifyEl) enotifyEl.value = location.origin + '/api/epay_notify.php';
    const ssEl = document.getElementById('posSsSec');
    if (ssEl) ssEl.value = tempSettings.pos_screensaver_sec ?? 30;
    const ssUrlEl = document.getElementById('ssImgUrl');
    if (ssUrlEl) ssUrlEl.value = tempSettings.pos_screensaver_img || '';
    const ssPrev = document.getElementById('ssImgPreview');
    if (ssPrev && tempSettings.pos_screensaver_img) { ssPrev.src = assetUrl(tempSettings.pos_screensaver_img); ssPrev.style.display = ''; }
    const orEl = document.getElementById('offlineRatio');
    if (orEl) orEl.value = parseFloat(tempSettings.offline_price_ratio ?? 1.8).toFixed(2);
    const pwdState = document.getElementById('staffPwdState');
    if (pwdState) pwdState.textContent = tempSettings.offline_staff_pwd_set ? '（已设置）' : '（未设置）';
    const wxEl = document.getElementById('qrWxUrl');
    if (wxEl) wxEl.value = tempSettings.offline_pay_qr_wx || '';
    const wxPrev = document.getElementById('qrWxPreview');
    if (wxPrev && tempSettings.offline_pay_qr_wx) { wxPrev.src = assetUrl(tempSettings.offline_pay_qr_wx); wxPrev.style.display = ''; }
    const aliEl = document.getElementById('qrAliUrl');
    if (aliEl) aliEl.value = tempSettings.offline_pay_qr_ali || '';
    const aliPrev = document.getElementById('qrAliPreview');
    if (aliPrev && tempSettings.offline_pay_qr_ali) { aliPrev.src = assetUrl(tempSettings.offline_pay_qr_ali); aliPrev.style.display = ''; }
    const plEl = document.getElementById('posLink');
    if (plEl) {
        if (tempSettings.pos_code) {
            plEl.value = location.origin + '/admin/pos.php?c=' + tempSettings.pos_code;
        } else if (tempSettings.pos_token) {
            plEl.value = location.origin + '/admin/pos.php?t=' + tempSettings.pos_token;
        }
    }
    const pcEl = document.getElementById('posCode');
    if (pcEl) {
        pcEl.value = tempSettings.pos_code || '';
    }
    const pclEl = document.getElementById('posCustLink');
    const pccEl = document.getElementById('posCustCode');
    if (pccEl) {
        pccEl.value = tempSettings.pos_customer_code || '';
    }
    if (pclEl) {
        if (tempSettings.pos_customer_code) {
            pclEl.value = location.origin + '/admin/pos.php?c=' + tempSettings.pos_customer_code;
            pclEl.placeholder = '';
        } else {
            // 不再回退到 ?t=token&as=customer：顾客删掉参数就能拿到店员能力，
            // 顾客链接必须由独立的自助码生成（到「店管理」按店复制）。
            pclEl.value = '';
            pclEl.placeholder = '请到「店管理」按店铺复制顾客自助链接';
        }
    }
    renderConditionTypes();
    renderElementList();
    updateSaveStatus(false);
}

function renderConditionTypes() {
    const container = document.getElementById('conditionTypesContainer');
    if (!container) return;
    container.innerHTML = '';
    
    (tempSettings.condition_types || []).forEach((condition, index) => {
        const div = document.createElement('div');
        div.className = 'condition-type-row';
        div.innerHTML = `
            <input type="text" class="form-input" placeholder="状态名称" value="${condition.name}" onchange="updateConditionType(${index}, 'name', this.value)">
            <input type="color" value="${condition.color}" onchange="updateConditionType(${index}, 'color', this.value)" style="width:80px; height:42px;">
            <button class="btn btn-secondary" onclick="deleteConditionType(${index})">删除</button>
        `;
        container.appendChild(div);
    });
}

function addConditionType() {
    const newKey = 'custom_' + Date.now();
    if (!tempSettings.condition_types) tempSettings.condition_types = [];
    tempSettings.condition_types.push({key: newKey, name: '新状态', color: '#667eea'});
    renderConditionTypes();
    updateSaveStatus(true);
}

function updateConditionType(index, field, value) {
    if (tempSettings.condition_types && tempSettings.condition_types[index]) {
        tempSettings.condition_types[index][field] = value;
        updateSaveStatus(true);
    }
}

function deleteConditionType(index) {
    if (tempSettings.condition_types) {
        tempSettings.condition_types.splice(index, 1);
        renderConditionTypes();
        updateSaveStatus(true);
    }
}

function renderElementList() {
    const container = document.getElementById('elementList');
    if (!container) return;
    container.innerHTML = '';
    
    let elements = tempSettings.live_display.elements;
    if (!elements || elements.length === 0) {
        elements = defaultSettings.live_display.elements;
    }

    elements.forEach((item, index) => {
        console.log(`renderElementList - rendering item ${index}:`, item.type, item);
        
        const card = document.createElement('div');
        card.className = 'element-card' + 
            (selectedElementIndex === index ? ' selected' : '') +
            (!item.enabled ? ' disabled' : '');
        card.dataset.index = index;
        
        card.innerHTML = `
            <div class="element-card-header">
                <div class="element-card-title">
                    <span class="element-card-icon">${elementIcons[item.type] || '📦'}</span>
                    ${elementLabels[item.type] || item.type}
                </div>
                <label class="toggle-switch" onclick="event.stopPropagation()">
                    <input type="checkbox" ${item.enabled ? 'checked' : ''} onchange="toggleElementEnabled(${index})">
                    <span class="toggle-slider"></span>
                </label>
            </div>
            <div class="element-card-preview">
                <span>左: ${item.left}</span>
                <span>上: ${item.top}</span>
                <span>${item.width}×${item.height}</span>
            </div>
        `;
        container.appendChild(card);
    });
    
    console.log('renderElementList - completed rendering, container children:', container.children.length);
}

const elList = document.getElementById('elementList');
if (elList) {
elList.addEventListener('click', function(e) {
    try {
        const card = e.target.closest('.element-card');
        if (!card) return;
        if (e.target.closest('.toggle-switch')) return;
        
        const index = parseInt(card.dataset.index);
        if (!isNaN(index)) {
            selectElement(index);
        }
    } catch (e) {
        console.error('elementList click error:', e);
    }
});
}

function selectElement(index) {
    try {
        selectedElementIndex = index;
        const elements = tempSettings.live_display.elements || defaultSettings.live_display.elements;
        const item = elements[index];

        const panel = document.getElementById('configPanel');
        if (!panel) return;
        panel.style.display = 'block';
        
        document.getElementById('configPanelTitle').textContent = elementLabels[item.type] || item.type;
        document.getElementById('configEnabled').checked = item.enabled;
        document.getElementById('configLeft').value = item.left;
        document.getElementById('configTop').value = item.top;
        document.getElementById('configWidth').value = item.width;
        document.getElementById('configHeight').value = item.height;
        document.getElementById('configFontSize').value = item.fontSize || '';
        document.getElementById('configZIndex').value = item.zIndex || 1;
        
        document.getElementById('configFontSizeWrapper').style.display = 
            (item.type === 'image') ? 'none' : 'flex';
        
        document.getElementById('configItemSpacingWrapper').style.display =
            (item.type === 'condition') ? 'flex' : 'none';
        document.getElementById('configItemSpacing').value = item.itemSpacing || 30;

        document.getElementById('configColorWrapper').style.display =
            (item.type === 'suggestedPrice' || item.type === 'purchasePrice') ? 'flex' : 'none';
        document.getElementById('configColor').value = item.color || '#e8e8ed';

        const isCondition = item.type === 'condition';
        document.getElementById('configStatusFontSizeWrapper').style.display = isCondition ? 'flex' : 'none';
        document.getElementById('configStatusColorWrapper').style.display = isCondition ? 'flex' : 'none';
        document.getElementById('configPriceFontSizeWrapper').style.display = isCondition ? 'flex' : 'none';
        document.getElementById('configPriceColorWrapper').style.display = isCondition ? 'flex' : 'none';

        if (isCondition) {
            document.getElementById('configStatusFontSize').value = item.statusFontSize || '28px';
            document.getElementById('configStatusColor').value = item.statusColor || '#9d9daf';
            document.getElementById('configPriceFontSize').value = item.priceFontSize || '46px';
            document.getElementById('configPriceColor').value = item.priceColor || '#34d399';
        }

        document.getElementById('configPriceOffsetWrapper').style.display = isCondition ? 'flex' : 'none';
        document.getElementById('configStockOffsetWrapper').style.display = isCondition ? 'flex' : 'none';

        if (isCondition) {
            document.getElementById('configPriceOffset').value = item.priceOffsetX || 0;
            document.getElementById('configStockOffset').value = item.stockOffsetX || 0;
        }

        renderElementList();
    } catch (e) {
        console.error('selectElement error:', e);
    }
}

function toggleElementEnabled(index) {
    const elements = tempSettings.live_display.elements || defaultSettings.live_display.elements;
    elements[index].enabled = !elements[index].enabled;
    renderElementList();
    updateElementConfig();
    updateSaveStatus(true);
    
    if (selectedElementIndex === index) {
        document.getElementById('configEnabled').checked = elements[index].enabled;
    }
}

function updateElementConfig() {
    if (selectedElementIndex === null) return;
    
    const elements = tempSettings.live_display.elements || defaultSettings.live_display.elements;
    const item = elements[selectedElementIndex];
    
    item.enabled = document.getElementById('configEnabled').checked;
    item.left = parseInt(document.getElementById('configLeft').value) || 0;
    item.top = parseInt(document.getElementById('configTop').value) || 0;
    item.width = parseInt(document.getElementById('configWidth').value) || 100;
    item.height = parseInt(document.getElementById('configHeight').value) || 50;
    item.fontSize = document.getElementById('configFontSize').value;
    item.zIndex = parseInt(document.getElementById('configZIndex').value) || 1;
    
    if (item.type === 'condition') {
        item.itemSpacing = parseInt(document.getElementById('configItemSpacing').value) || 30;
    }

    if (item.type === 'suggestedPrice' || item.type === 'purchasePrice') {
        item.color = document.getElementById('configColor').value;
    }

    if (item.type === 'condition') {
        item.statusFontSize = document.getElementById('configStatusFontSize').value;
        item.statusColor = document.getElementById('configStatusColor').value;
        item.priceFontSize = document.getElementById('configPriceFontSize').value;
        item.priceColor = document.getElementById('configPriceColor').value;
        item.priceOffsetX = parseInt(document.getElementById('configPriceOffset').value) || 0;
        item.stockOffsetX = parseInt(document.getElementById('configStockOffset').value) || 0;
    }

    // Save to localStorage for live page to pick up
    localStorage.setItem('ppmart_temp_config', JSON.stringify(tempSettings));
    
    renderElementList();
    updateSaveStatus(true);
}

function updateConfigPanelValues(item) {
    document.getElementById('configEnabled').checked = item.enabled;
    document.getElementById('configLeft').value = item.left;
    document.getElementById('configTop').value = item.top;
    document.getElementById('configWidth').value = item.width;
    document.getElementById('configHeight').value = item.height;
    document.getElementById('configFontSize').value = item.fontSize || '';
    document.getElementById('configZIndex').value = item.zIndex || 1;
    document.getElementById('configItemSpacing').value = item.itemSpacing || 30;
    document.getElementById('configColor').value = item.color || '#e8e8ed';
    document.getElementById('configStatusFontSize').value = item.statusFontSize || '28px';
    document.getElementById('configStatusColor').value = item.statusColor || '#9d9daf';
    document.getElementById('configPriceFontSize').value = item.priceFontSize || '46px';
    document.getElementById('configPriceColor').value = item.priceColor || '#34d399';
    document.getElementById('configPriceOffset').value = item.priceOffsetX || 0;
    document.getElementById('configStockOffset').value = item.stockOffsetX || 0;
}

function onNudgeStepChange() {
    nudgeStep = parseInt(document.getElementById('nudgeStep').value) || 5;
}

function nudgeElement(direction) {
    if (selectedElementIndex === null) return;
    const elements = tempSettings.live_display.elements || defaultSettings.live_display.elements;
    const item = elements[selectedElementIndex];
    const step = nudgeStep;

    if (direction === 'up') item.top = Math.max(0, (item.top || 0) - step);
    if (direction === 'down') item.top = Math.max(0, (item.top || 0) + step);
    if (direction === 'left') item.left = Math.max(0, (item.left || 0) - step);
    if (direction === 'right') item.left = Math.max(0, (item.left || 0) + step);

    updateConfigPanelValues(item);
    localStorage.setItem('ppmart_temp_config', JSON.stringify(tempSettings));
    renderElementList();
    updateSaveStatus(true);
}

function resizeElement(mode) {
    if (selectedElementIndex === null) return;
    const elements = tempSettings.live_display.elements || defaultSettings.live_display.elements;
    const item = elements[selectedElementIndex];
    const step = nudgeStep;

    if (mode === 'wider') item.width = Math.max(10, (item.width || 0) + step);
    if (mode === 'narrower') item.width = Math.max(10, (item.width || 0) - step);
    if (mode === 'taller') item.height = Math.max(10, (item.height || 0) + step);
    if (mode === 'shorter') item.height = Math.max(10, (item.height || 0) - step);

    updateConfigPanelValues(item);
    localStorage.setItem('ppmart_temp_config', JSON.stringify(tempSettings));
    renderElementList();
    updateSaveStatus(true);
}

function updateSaveStatus(hasChanges) {
    const status = document.getElementById('saveStatus');
    if (hasChanges) {
        status.innerHTML = '<span style="color:var(--warning);">•</span> 有未保存的修改';
        status.className = 'save-status';
    } else {
        status.innerHTML = '<span style="color:var(--success);">✓</span> 已保存';
        status.className = 'save-status saved';
    }
}

// 兼容旧写法：表单项变更后标记「有未保存的修改」
function markChanged() {
    updateSaveStatus(true);
}

function resetToSaved() {
    tempSettings = JSON.parse(JSON.stringify(savedSettings));
    selectedElementIndex = null;
    document.getElementById('configPanel').style.display = 'none';
    localStorage.removeItem('ppmart_temp_config');
    applySettings();
}

async function saveSettings() {
    try {
        const isStoreAdmin = <?= $isSuperAdmin ? 'false' : 'true' ?>;
        if (isStoreAdmin) {
            tempSettings.store_name = document.getElementById('systemName').value;
        } else {
            tempSettings.system_name = document.getElementById('systemName').value;
        }

        const saveBtn = document.querySelector('.save-bar .btn.btn-primary');
        saveBtn.textContent = '保存中...';
        saveBtn.disabled = true;
        
        const res = await fetch('../api/save_settings.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({settings: tempSettings})
        });
        
        const data = await res.json();
        
        if (data.success) {
            savedSettings = JSON.parse(JSON.stringify(tempSettings));
            localStorage.removeItem('ppmart_temp_config');
            updateSaveStatus(false);
            
            saveBtn.textContent = '已保存!';
            setTimeout(() => {
                saveBtn.textContent = '保存配置';
                saveBtn.disabled = false;
            }, 1500);
        } else {
            alert('保存失败: ' + data.error);
            saveBtn.textContent = '保存配置';
            saveBtn.disabled = false;
        }
    } catch(e) {
        alert('保存失败');
        console.error(e);
    }
}

// ── Logo 处理 ──
document.getElementById('logoFile').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;
    const formData = new FormData();
    formData.append('image', file);
    formData.append('type', 'logo');

    fetch('../api/upload_image.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            tempSettings.logo_path = data.data.url;
            showLogoPreview(tempSettings.logo_path);
            updateSaveStatus(true);
        } else {
            alert('上传失败: ' + (data.error || '未知错误'));
        }
    })
    .catch(err => {
        alert('上传失败: ' + err.message);
    });
});

function applyLogoUrl() {
    const url = document.getElementById('logoUrl').value.trim();
    if (!url) return;
    tempSettings.logo_path = url;
    showLogoPreview(url);
    updateSaveStatus(true);
    document.getElementById('logoUrl').value = '';
}

function clearLogo() {
    delete tempSettings.logo_path;
    document.getElementById('logoPreviewGroup').style.display = 'none';
    document.getElementById('logoPreview').src = '';
    document.getElementById('logoFile').value = '';
    updateSaveStatus(true);
}

// ── 线下收银台：收款码上传 ──
// 图片URL补全：相对 uploads 路径 → 完整URL（admin页面需 ../ 前缀）
function assetUrl(path) {
    if (!path) return '';
    if (/^https?:\/\//i.test(path)) return path;
    return location.origin + '/' + path.replace(/^\.\.\//, '');
}
function bindQrUpload(inputId, key, previewId, urlId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    input.addEventListener('change', function () {
        if (!this.files || !this.files[0]) return;
        const fd = new FormData();
        fd.append('image', this.files[0]);
        fetch('../api/upload_image.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    tempSettings[key] = data.data.url;
                    const prev = document.getElementById(previewId);
                    if (prev) { prev.src = assetUrl(data.data.url); prev.style.display = ''; }
                    const urlEl = document.getElementById(urlId || (previewId === 'qrWxPreview' ? 'qrWxUrl' : 'qrAliUrl'));
                    if (urlEl) urlEl.value = data.data.url;
                    updateSaveStatus(true);
                } else {
                    alert('上传失败: ' + (data.error || '未知错误'));
                }
            })
            .catch(err => alert('上传失败: ' + err.message));
    });
}
bindQrUpload('qrWxFile', 'offline_pay_qr_wx', 'qrWxPreview');
bindQrUpload('qrAliFile', 'offline_pay_qr_ali', 'qrAliPreview');
bindQrUpload('ssImgFile', 'pos_screensaver_img', 'ssImgPreview', 'ssImgUrl');

function copyPosLink() {
    const el = document.getElementById('posLink');
    if (!el || !el.value) { alert('请先保存配置生成链接'); return; }
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(el.value).then(() => alert('收银台链接已复制'));
    } else {
        el.select();
        document.execCommand('copy');
        alert('收银台链接已复制');
    }
}

function copyPosCode() {
    const el = document.getElementById('posCode');
    if (!el || !el.value) { alert('8 位数字码未生成，请刷新或联系集团管理员'); return; }
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(el.value).then(() => alert('8 位数字码已复制：' + el.value));
    } else {
        el.select();
        document.execCommand('copy');
        alert('8 位数字码已复制');
    }
}

function copyPosCustLink() {
    const el = document.getElementById('posCustLink');
    if (!el || !el.value) { alert('请先保存配置生成链接'); return; }
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(el.value).then(() => alert('顾客自助链接已复制'));
    } else {
        el.select();
        document.execCommand('copy');
        alert('顾客自助链接已复制');
    }
}

function copyPosCustCode() {
    const el = document.getElementById('posCustCode');
    if (!el || !el.value) { alert('请先保存配置生成自助码'); return; }
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(el.value).then(() => alert('顾客自助码已复制：' + el.value));
    } else {
        el.select();
        document.execCommand('copy');
        alert('顾客自助码已复制');
    }
}

function resetPosCustomerCode() {
    if (!confirm('重置后旧的顾客自助码与链接立即失效，确定重置？')) return;
    tempSettings.offline_reset_customer_code = true;
    saveSettings().then(() => {
        tempSettings.offline_reset_customer_code = false;
        loadSettings();
    });
}

function resetPosToken() {
    if (!confirm('重置后旧链接立即失效，确定重置？')) return;
    tempSettings.offline_reset_token = true;
    saveSettings().then(() => {
        tempSettings.offline_reset_token = false;
        loadSettings();
    });
}

function showLogoPreview(path) {
    const img = document.getElementById('logoPreview');
    if (!path) {
        img.src = '';
        document.getElementById('logoPreviewGroup').style.display = 'none';
        return;
    }
    let url = path;
    if (!/^(https?:|data:|\/)/i.test(url)) {
        url = '../' + url;
    }
    img.src = url;
    img.onerror = function() { this.style.display = 'none'; };
    img.onload = function() { this.style.display = ''; };
    document.getElementById('logoPreviewGroup').style.display = 'block';
}

document.getElementById('systemName').addEventListener('input', () => {
    const isStoreAdmin = <?= $isSuperAdmin ? 'false' : 'true' ?>;
    if (isStoreAdmin) {
        tempSettings.store_name = document.getElementById('systemName').value;
    } else {
        tempSettings.system_name = document.getElementById('systemName').value;
    }
    updateSaveStatus(true);
});

document.addEventListener('keydown', (e) => {
    if (selectedElementIndex === null) return;
    const activeEl = document.activeElement;
    if (activeEl && (activeEl.tagName === 'INPUT' || activeEl.tagName === 'TEXTAREA' || activeEl.tagName === 'SELECT')) return;

    const step = e.shiftKey ? nudgeStep * 5 : nudgeStep;
    if (e.key === 'ArrowUp' || e.key === 'ArrowDown' || e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
        e.preventDefault();
        const prev = nudgeStep;
        nudgeStep = step;
        if (e.key === 'ArrowUp') nudgeElement('up');
        if (e.key === 'ArrowDown') nudgeElement('down');
        if (e.key === 'ArrowLeft') nudgeElement('left');
        if (e.key === 'ArrowRight') nudgeElement('right');
        nudgeStep = prev;
    }
});

loadSettings();
    </script>
</body>
</html>
