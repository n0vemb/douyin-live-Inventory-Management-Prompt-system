<?php
$pageTitle = '标签打印';
$currentPage = 'purchase_logs';
require_once __DIR__ . '/layout.php';
?>
        <div class="page-title">🏷️ 标签打印</div>

        <div class="card">
            <div style="display:flex; gap:15px; flex-wrap:wrap; margin-bottom:20px;">
                <div class="form-group" style="flex:1; min-width:200px;">
                    <label class="form-label">开始日期</label>
                    <input type="date" class="form-input" id="startDate">
                </div>
                <div class="form-group" style="flex:1; min-width:200px;">
                    <label class="form-label">结束日期</label>
                    <input type="date" class="form-input" id="endDate">
                </div>
                <div class="form-group" style="flex:1; min-width:200px;">
                    <label class="form-label">商品名称/条码</label>
                    <input type="text" class="form-input" id="searchKeyword" placeholder="搜索商品...">
                </div>
                <div class="form-group" style="flex:1; min-width:150px;">
                    <label class="form-label">状态类型</label>
                    <select class="form-input" id="conditionType">
                        <option value="">全部状态</option>
                    </select>
                </div>
                <div class="form-group" style="min-width:120px;">
                    <label class="form-label">操作</label>
                    <div style="display:flex; gap:8px;">
                        <button class="btn btn-primary" onclick="searchPurchaseLogs()">查询</button>
                        <button class="btn btn-secondary" onclick="resetFilters()">重置</button>
                    </div>
                </div>
            </div>

            <div style="margin-bottom:15px; display:flex; justify-content:space-between; align-items:center;">
                <div id="selectedCount" style="color:var(--text-secondary); font-size:14px;"></div>
                <div id="printButtonContainer"></div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th style="width:40px;"><input type="checkbox" id="selectAll" onchange="toggleSelectAll()"></th>
                        <th>入库时间</th>
                        <th>批次号</th>
                        <th>商品条码</th>
                        <th>商品名称</th>
                        <th>状态</th>
                        <th>数量</th>
                        <th>售价</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody id="purchaseLogList"></tbody>
            </table>

            <div class="pagination" id="pagination"></div>
        </div>

        <!-- 打印设置面板 -->
        <div class="modal" id="printModal">
            <div class="modal-content"><!-- 打印设置 -->
                <div class="modal-header">
                    <h3 class="modal-title">🖨️ 批量打印标签</h3>
                    <button class="modal-close" onclick="closeModal('printModal')">&times;</button>
                </div>
                
                <div style="padding:15px 0;">
                    <div style="background:var(--bg-hover); padding:12px; border-radius:8px; margin-bottom:15px;">
                        <div style="font-size:13px; color:var(--text-secondary); margin-bottom:8px;">
                            已选择 <strong id="selectedPrintCount">0</strong> 条记录，共 <strong id="totalLabels">0</strong> 个标签
                        </div>
                        <div id="selectedItemsSummary" style="max-height:100px; overflow-y:auto; font-size:12px;"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">选择模板</label>
                        <select class="form-input" id="labelTemplate">
                            <option value="">选择模板...</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-top:12px;">
                        <label class="form-label">打印机（留空使用系统默认）</label>
                        <input type="text" class="form-input" id="printerName" placeholder="例如: Brother_QL_820NWB" style="font-size:13px;">
                    </div>

                    <div style="display:flex; gap:10px; margin-top:20px;">
                        <button type="button" class="btn btn-secondary" onclick="openEditor()" style="flex:1;">✏️ 编辑模板</button>
                        <button type="button" class="btn btn-primary" onclick="previewLabels()" style="flex:1;">👁️ 预览</button>
                        <button type="button" class="btn btn-success" onclick="directPrint()" style="flex:1;">🖨️ 直打</button>
                    </div>
                    <div style="display:flex; gap:10px; margin-top:8px;">
                        <button type="button" class="btn btn-secondary" onclick="printLabels()" style="flex:0.5; font-size:12px;">🖨️ 浏览器打印</button>
                    </div>
                    <div style="margin-top:12px; padding:10px; background:#fff3cd; border-radius:6px; font-size:12px; color:#856404; line-height:1.6;">
                        ⚠️ 打印前请在浏览器打印对话框中设置：<br>
                        <strong>纸张尺寸</strong> = 匹配标签纸（如 60×40mm 或自定义）、
                        <strong>边距</strong> = 无、
                        <strong>缩放</strong> = 100、
                        <strong>页眉页脚</strong> = 关闭
                    </div>
                </div>
            </div>
        </div>

        <!-- 可视化编辑器模态框 -->
        <div class="modal" id="editorModal">
            <div class="modal-content modal-full" style="max-height:95vh;"><!-- 编辑器 -->
                <div class="modal-header">
                    <h3 class="modal-title">✏️ 标签可视化编辑器</h3>
                    <button class="modal-close" onclick="closeEditor()">&times;</button>
                </div>
                
                <div style="display:flex; gap:15px; max-height:calc(95vh - 80px); margin-top:15px;">
                    <!-- 左侧工具栏 -->
                    <div style="width:200px; flex-shrink:0; border-right:1px solid var(--border); padding-right:15px;">
                        <div style="margin-bottom:15px;">
                            <div style="font-weight:bold; margin-bottom:8px; font-size:12px;">📐 画布设置</div>
                            <div class="form-group">
                                <label class="form-label" style="font-size:10px;">宽度 (mm)</label>
                                <input type="number" class="form-input" id="canvasWidth" value="60" min="20" max="150" onchange="updateFabricCanvas()">
                            </div>
                            <div class="form-group">
                                <label class="form-label" style="font-size:10px;">高度 (mm)</label>
                                <input type="number" class="form-input" id="canvasHeight" value="40" min="10" max="150" onchange="updateFabricCanvas()">
                            </div>
                            <div class="form-group">
                                <label class="form-label" style="font-size:10px;">打印浓度</label>
                                <select class="form-input" id="printDensity">
                                    <option value="light">淡</option>
                                    <option value="normal" selected>正常</option>
                                    <option value="dark">浓</option>
                                </select>
                            </div>
                        </div>

                        <div style="border-top:1px solid var(--border); padding-top:12px;">
                            <div style="font-weight:bold; margin-bottom:8px; font-size:12px;">➕ 添加元素</div>
                            <button class="btn btn-sm btn-primary" onclick="addFabricElement('barcode')" style="margin-bottom:5px; width:100%;">📊 条码</button>
                            <button class="btn btn-sm btn-primary" onclick="addFabricElement('barcodeText')" style="margin-bottom:5px; width:100%;">🔢 数字</button>
                            <button class="btn btn-sm btn-primary" onclick="addFabricElement('name')" style="margin-bottom:5px; width:100%;">🏷️ 名称</button>
                            <button class="btn btn-sm btn-primary" onclick="addFabricElement('price')" style="margin-bottom:5px; width:100%;">💰 价格</button>
                            <button class="btn btn-sm btn-primary" onclick="addFabricElement('condition')" style="margin-bottom:5px; width:100%;">📋 状态</button>
                        </div>

                        <div style="margin-top:15px; padding-top:12px; border-top:1px solid var(--border);">
                            <div style="font-weight:bold; margin-bottom:8px; font-size:12px;">🗑️ 操作</div>
                            <button class="btn btn-sm btn-danger" onclick="deleteFabricSelected()" style="width:100%;">删除选中</button>
                        </div>

                        <div style="margin-top:15px; padding-top:12px; border-top:1px solid var(--border);">
                            <div style="font-weight:bold; margin-bottom:8px; font-size:12px;">📋 画布信息</div>
                            <div style="font-size:11px; color:var(--text-secondary);">
                                尺寸: <span id="fabricSizeLabel">60×40mm</span><br>
                                元素: <span id="fabricElementCount">0</span>
                            </div>
                        </div>
                    </div>

                    <!-- 中间画布区域 -->
                    <div style="flex:1; display:flex; flex-direction:column; overflow:hidden; min-width:0;">
                        <div id="fabricCanvasWrapper" style="flex:1; background:var(--bg-hover); border-radius:6px; overflow:auto; padding:20px; display:flex; justify-content:center; align-items:center;">
                            <canvas id="fabricCanvas"></canvas>
                        </div>

                        <div style="margin-top:10px; display:flex; gap:10px;">
                            <button type="button" class="btn btn-secondary" onclick="resetFabricCanvas()">🔄 重置</button>
                            <button type="button" class="btn btn-primary" onclick="saveFabricTemplate()" style="flex:1;">💾 保存模板</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 预览模态框 -->
        <div class="modal" id="previewModal">
            <div class="modal-content modal-full" style="max-height:90vh;"><!-- 预览 -->
                <div class="modal-header">
                    <h3 class="modal-title">👁️ 标签预览</h3>
                    <button class="modal-close" onclick="closeModal('previewModal')">&times;</button>
                </div>
                <div id="previewContent" style="overflow:auto; max-height:70vh; background:var(--bg-surface); padding:20px; border-radius:8px;"></div>
                <div style="display:flex; gap:10px; margin-top:15px;">
                    <button type="button" class="btn btn-success" onclick="printLabelsDirect()" style="flex:1;">🖨️ 打印</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('previewModal')">关闭</button>
                </div>
            </div>
        </div>
    </div>

    <style>
    .element-tool {
        padding:5px 8px;
        background:var(--bg-hover);
        border:1px solid var(--border);
        border-radius:4px;
        cursor:grab;
        font-size:11px;
        transition: all 0.2s;
    }
    .element-tool:hover {
        background:var(--info-light);
        border-color:var(--primary);
    }
    .element-tool:active {
        cursor: grabbing;
    }
    </style>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script>
    let currentPage = 1;
    let totalPages = 1;
    let selectedItems = [];
    let allRecords = [];
    let conditionTypes = [];
    let conditionNameMap = {};
    let conditionClassMap = {};
    let allConditionTypes = ['sealed', 'opened', 'boxless', 'flawed'];
    let labelTemplates = [];
    
    let fabricCanvas = null;
    let fabricScale = 3;
    const MM_TO_PX = 3.78;
    
    const sampleItem = {
        barcode: '6901234567892',  // 有效的 EAN13 条码（校验位正确）
        productName: '示例商品',
        price: 51.00,
        conditionType: 'sealed'
    };

    function formatDate(dateStr) {
        if (!dateStr) return '-';
        const date = new Date(dateStr);
        return date.toLocaleString('zh-CN', { 
            year: 'numeric', month: '2-digit', day: '2-digit',
            hour: '2-digit', minute: '2-digit'
        });
    }

    function getConditionName(key) {
        return conditionNameMap[key] || key;
    }

    function getConditionClass(key) {
        return conditionClassMap[key] || '';
    }

    function getDefaultContent(type, item = sampleItem) {
        switch(type) {
            case 'barcode': return item.barcode;
            case 'barcodeText': return item.barcode;
            case 'name': return item.productName;
            case 'price': return '¥' + item.price.toFixed(2);
            case 'condition': 
                return allConditionTypes.map(c => 
                    `[${c === item.conditionType ? '☑' : '□'}] ${getConditionName(c)}`
                ).join(' ');
            default: return type;
        }
    }

    function initFabricCanvas() {
        if (fabricCanvas) {
            fabricCanvas.dispose();
        }
        
        const widthMm = parseInt(document.getElementById('canvasWidth').value) || 60;
        const heightMm = parseInt(document.getElementById('canvasHeight').value) || 40;
        
        const canvasWidth = widthMm * MM_TO_PX * fabricScale;
        const canvasHeight = heightMm * MM_TO_PX * fabricScale;
        
        fabricCanvas = new fabric.Canvas('fabricCanvas', {
            width: canvasWidth,
            height: canvasHeight,
            backgroundColor: '#ffffff',
            selection: true
        });
        
        fabricCanvas.on('object:modified', function() {
            updateFabricElementCount();
        });
        
        fabricCanvas.on('object:added', function() {
            updateFabricElementCount();
        });
        
        fabricCanvas.on('object:removed', function() {
            updateFabricElementCount();
        });
        
        document.getElementById('fabricSizeLabel').textContent = widthMm + '×' + heightMm + 'mm';
        updateFabricElementCount();
    }

    function updateFabricCanvas() {
        const widthMm = parseInt(document.getElementById('canvasWidth').value) || 60;
        const heightMm = parseInt(document.getElementById('canvasHeight').value) || 40;
        
        if (fabricCanvas) {
            fabricCanvas.setDimensions({
                width: widthMm * MM_TO_PX * fabricScale,
                height: heightMm * MM_TO_PX * fabricScale
            });
        }
        
        document.getElementById('fabricSizeLabel').textContent = widthMm + '×' + heightMm + 'mm';
    }

    function updateFabricElementCount() {
        if (fabricCanvas) {
            document.getElementById('fabricElementCount').textContent = fabricCanvas.getObjects().length;
        }
    }

    function getDefaultFabricContent(type) {
        switch(type) {
            case 'barcode': return sampleItem.barcode;
            case 'barcodeText': return sampleItem.barcode;
            case 'name': return sampleItem.productName;
            case 'price': return '¥' + sampleItem.price.toFixed(2);
            case 'condition': 
                return allConditionTypes.map(c => 
                    `[${c === sampleItem.conditionType ? '☑' : '□'}] ${getConditionName(c)}`
                ).join(' ');
            default: return type;
        }
    }

    function addFabricElement(type) {
        if (!fabricCanvas) return;

        const widthMm = parseInt(document.getElementById('canvasWidth').value) || 60;
        const heightMm = parseInt(document.getElementById('canvasHeight').value) || 40;

        // 默认尺寸（mm）
        const defaultSizes = {
            barcode: { width: 50, height: 15, fontSize: 5 },
            barcodeText: { width: 50, height: 4, fontSize: 2.5 },
            name: { width: 50, height: 4, fontSize: 3 },
            price: { width: 50, height: 5, fontSize: 4 },
            condition: { width: 50, height: 4, fontSize: 2 }
        };

        const size = defaultSizes[type] || { width: 25, height: 5, fontSize: 3 };

        // 居中位置
        const leftMm = (widthMm - size.width) / 2;
        const topMm = type === 'barcode' ? 2 : (heightMm - size.height) / 2;

        // 转换为画布像素
        const leftPx = leftMm * MM_TO_PX * fabricScale;
        const topPx = topMm * MM_TO_PX * fabricScale;
        const fontSizePx = size.fontSize * MM_TO_PX * fabricScale;

        if (type === 'barcode') {
            const element = new fabric.Text('|||||||||||', {
                left: leftPx,
                top: topPx,
                fontSize: fontSizePx,
                fontFamily: 'monospace',
                fontWeight: 'bold',
                fill: '#000000',
                originX: 'left',
                originY: 'top'
            });

            element.set('dataType', type);
            element.set('elementType', 'barcode');
            fabricCanvas.add(element);
        } else {
            const fontWeight = (type === 'price' || type === 'name') ? 'bold' : 'normal';
            const color = type === 'price' ? '#e53e3e' : '#000000';

            const element = new fabric.Text(getDefaultFabricContent(type), {
                left: leftPx,
                top: topPx,
                fontSize: fontSizePx,
                fontFamily: 'Arial',
                fontWeight: fontWeight,
                fill: color,
                originX: 'left',
                originY: 'top'
            });

            element.set('dataType', type);
            element.set('elementType', type);
            fabricCanvas.add(element);
        }

        fabricCanvas.setActiveObject(fabricCanvas.getObjects()[fabricCanvas.getObjects().length - 1]);
        fabricCanvas.renderAll();
    }

    function deleteFabricSelected() {
        if (!fabricCanvas) return;
        const activeObjects = fabricCanvas.getActiveObjects();
        if (activeObjects.length) {
            activeObjects.forEach(function(object) {
                fabricCanvas.remove(object);
            });
            fabricCanvas.discardActiveObject();
            fabricCanvas.renderAll();
        }
    }

    function resetFabricCanvas() {
        if (!fabricCanvas) return;
        if (confirm('确定要重置画布吗？')) {
            fabricCanvas.clear();
            fabricCanvas.backgroundColor = '#ffffff';
            fabricCanvas.renderAll();
            updateFabricElementCount();
        }
    }

    async function saveFabricTemplate() {
        if (!fabricCanvas) return;

        const defaultName = currentTemplateIndex !== '' && labelTemplates[currentTemplateIndex]?.name ? labelTemplates[currentTemplateIndex].name : '新模板';
        const name = prompt('请输入模板名称：', defaultName);
        if (!name) return;

        const widthMm = parseInt(document.getElementById('canvasWidth').value) || 60;
        const heightMm = parseInt(document.getElementById('canvasHeight').value) || 40;

        const elements = fabricCanvas.getObjects().map(obj => {
            const xMm = obj.left / fabricScale / MM_TO_PX;
            const yMm = obj.top / fabricScale / MM_TO_PX;
            const widthMmVal = obj.getScaledWidth() / fabricScale / MM_TO_PX;
            const heightMmVal = obj.getScaledHeight() / fabricScale / MM_TO_PX;
            const effectiveFontSizeMm = (obj.fontSize * (obj.scaleY || 1)) / fabricScale / MM_TO_PX;

            return {
                type: obj.get('dataType') || obj.get('elementType') || 'text',
                x: parseFloat(xMm.toFixed(1)),
                y: parseFloat(yMm.toFixed(1)),
                width: parseFloat(widthMmVal.toFixed(1)),
                height: parseFloat(heightMmVal.toFixed(1)),
                fontSize: parseFloat(effectiveFontSizeMm.toFixed(1)),
                fontWeight: obj.fontWeight || 'normal',
                color: obj.fill || '#000000',
                align: obj.textAlign || 'center'
            };
        });

        const template = {
            name: name,
            canvasWidth: widthMm,
            canvasHeight: heightMm,
            paperType: 'continuous',
            density: document.getElementById('printDensity').value,
            elements: elements
        };

        try {
            const res = await fetch('../api/save_label_template.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name: name, config: template })
            });
            const data = await res.json();

            if (data.success) {
                await loadTemplates();
                const newIndex = labelTemplates.findIndex(t => t.name === name);
                if (newIndex !== -1) {
                    currentTemplateIndex = newIndex;
                    document.getElementById('labelTemplate').value = newIndex;
                }
                alert('模板保存成功！');
                closeEditor();
            } else {
                alert('保存失败: ' + (data.error || '未知错误'));
            }
        } catch (err) {
            console.error('保存模板失败:', err);
            alert('保存失败，请重试');
        }
    }

    function loadTemplates() {
        fetch('../api/get_label_templates.php')
            .then(res => res.json())
            .then(data => {
                if (data.success && data.templates && data.templates.length > 0) {
                    labelTemplates = data.templates;
                } else {
                    labelTemplates = [
                        {
                            name: '热敏60mm',
                            canvasWidth: 60,
                            canvasHeight: 40,
                            paperType: 'continuous',
                            density: 'normal',
                            elements: [
                                { type: 'barcode', x: 5, y: 2, width: 50, height: 15, fontSize: 4 },
                                { type: 'barcodeText', x: 5, y: 19, width: 50, height: 4, fontSize: 2.5, align: 'center' },
                                { type: 'name', x: 5, y: 25, width: 50, height: 5, fontSize: 3, fontWeight: 'bold', align: 'center' },
                                { type: 'price', x: 5, y: 32, width: 50, height: 6, fontSize: 4, fontWeight: 'bold', color: '#e53e3e', align: 'center' }
                            ]
                        },
                        {
                            name: '小标签40mm',
                            canvasWidth: 40,
                            canvasHeight: 30,
                            paperType: 'continuous',
                            density: 'normal',
                            elements: [
                                { type: 'barcode', x: 3, y: 2, width: 34, height: 12, fontSize: 3 },
                                { type: 'barcodeText', x: 3, y: 15, width: 34, height: 3, fontSize: 2, align: 'center' },
                                { type: 'name', x: 3, y: 20, width: 34, height: 4, fontSize: 2.5, fontWeight: 'bold', align: 'center' },
                                { type: 'price', x: 3, y: 25, width: 34, height: 4, fontSize: 3, fontWeight: 'bold', color: '#e53e3e', align: 'center' }
                            ]
                        }
                    ];
                }

                const select = document.getElementById('labelTemplate');
                select.innerHTML = '<option value="">选择模板...</option>';
                labelTemplates.forEach((template, index) => {
                    const opt = document.createElement('option');
                    opt.value = index;
                    opt.textContent = template.name;
                    select.appendChild(opt);
                });

                // 恢复上次使用的模板，如果没有则默认第一个
                const savedIndex = localStorage.getItem('ppmart_last_template');
                if (savedIndex !== null && labelTemplates[savedIndex]) {
                    select.value = savedIndex;
                    currentTemplateIndex = savedIndex;
                } else if (labelTemplates.length > 0) {
                    select.value = '0';
                    currentTemplateIndex = '0';
                    localStorage.setItem('ppmart_last_template', '0');
                }
            })
            .catch(err => {
                console.error('加载模板失败:', err);
                labelTemplates = [];
            });
    }

    function openEditor() {
        // 检查是否有选择的模板
        const templateSelect = document.getElementById('labelTemplate');
        const selectedValue = templateSelect.value;
        
        if (selectedValue !== '') {
            // 如果有选择的模板，加载它
            currentTemplateIndex = selectedValue;
            document.getElementById('editorModal').classList.add('show');
            loadTemplateToEditor(selectedValue);
        } else {
            // 否则重置并打开空编辑器
            currentTemplateIndex = '';
            document.getElementById('labelTemplate').value = '';
            document.getElementById('editorModal').classList.add('show');
            initFabricCanvas();
        }
    }

    function closeEditor() {
        document.getElementById('editorModal').classList.remove('show');
    }

    function loadTemplateToEditor(index) {
        const template = labelTemplates[index];
        if (!template) return;

        document.getElementById('canvasWidth').value = template.canvasWidth;
        document.getElementById('canvasHeight').value = template.canvasHeight;
        document.getElementById('printDensity').value = template.density || 'normal';

        initFabricCanvas();

        // 所有尺寸从 mm 转换为画布像素
        // fontSize 已经是等效值（保存时考虑了缩放），直接使用即可
        template.elements.forEach(el => {
            const leftPx = el.x * MM_TO_PX * fabricScale;
            const topPx = el.y * MM_TO_PX * fabricScale;
            const fontSizePx = el.fontSize * MM_TO_PX * fabricScale;

            if (el.type === 'barcode') {
                const barcodeEl = new fabric.Text('|||||||||||', {
                    left: leftPx,
                    top: topPx,
                    fontSize: fontSizePx,
                    fontFamily: 'monospace',
                    fontWeight: el.fontWeight || 'bold',
                    fill: '#000000',
                    originX: 'left',
                    originY: 'top'
                });

                barcodeEl.set('dataType', 'barcode');
                barcodeEl.set('elementType', 'barcode');
                fabricCanvas.add(barcodeEl);
            } else {
                const text = new fabric.Text(getDefaultFabricContent(el.type), {
                    left: leftPx,
                    top: topPx,
                    fontSize: fontSizePx,
                    fontFamily: 'Arial',
                    fontWeight: el.fontWeight || 'normal',
                    fill: el.color || '#000000',
                    originX: 'left',
                    originY: 'top',
                    textAlign: el.align || 'left'
                });

                text.set('dataType', el.type);
                text.set('elementType', el.type);
                fabricCanvas.add(text);
            }
        });

        fabricCanvas.renderAll();
        updateFabricElementCount();
    }

    async function loadSystemSettings() {
        try {
            const res = await fetch('../api/get_settings.php');
            const data = await res.json();
            if (data.success) {
                const settings = data.settings || data.data;
                if (settings.condition_types) {
                    conditionTypes = settings.condition_types;
                    allConditionTypes = conditionTypes.map(c => c.key);
                    conditionTypes.forEach(c => {
                        conditionNameMap[c.key] = c.name;
                        conditionClassMap[c.key] = 'condition-' + c.key;
                    });
                    
                    const select = document.getElementById('conditionType');
                    select.innerHTML = '<option value="">全部状态</option>';
                    conditionTypes.forEach(c => {
                        const opt = document.createElement('option');
                        opt.value = c.key;
                        opt.textContent = c.name;
                        select.appendChild(opt);
                    });
                }
            }
        } catch (err) {
            console.error(err);
        }
    }

    async function searchPurchaseLogs(page = 1) {
        currentPage = page;
        const startDate = document.getElementById('startDate').value;
        const endDate = document.getElementById('endDate').value;
        const keyword = document.getElementById('searchKeyword').value;
        const conditionType = document.getElementById('conditionType').value;

        try {
            const res = await fetch('../api/get_purchase_logs.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    start_date: startDate,
                    end_date: endDate,
                    keyword: keyword,
                    condition_type: conditionType,
                    page: page,
                    page_size: 50
                })
            });

            const data = await res.json();
            if (data.success) {
                allRecords = data.data.records;
                renderPurchaseLogs(data.data.records);
                renderPagination(data.data.total, data.data.page_size);
            } else {
                document.getElementById('purchaseLogList').innerHTML = 
                    '<tr><td colspan="9" style="text-align:center;color:var(--text-tertiary);padding:40px;">' + (data.error || '暂无入库记录') + '</td></tr>';
                document.getElementById('pagination').innerHTML = '';
            }
        } catch (err) {
            console.error(err);
            alert('查询失败');
        }
    }

    function renderPurchaseLogs(records) {
        const tbody = document.getElementById('purchaseLogList');
        if (!records || records.length === 0) {
            tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;color:var(--text-tertiary);padding:40px;">暂无入库记录</td></tr>';
            return;
        }

        const selectAllChecked = records.every(r => selectedItems.some(s => s.batch_id === r.batch_id));
        document.getElementById('selectAll').checked = selectAllChecked && records.length > 0;

        tbody.innerHTML = records.map(r => {
            const isSelected = selectedItems.some(s => s.batch_id === r.batch_id);
            return `
                <tr style="${isSelected ? 'background:var(--info-light);' : ''}">
                    <td><input type="checkbox" ${isSelected ? 'checked' : ''} onchange="toggleSelect(event, ${r.batch_id}, '${r.barcode}', '${escapeHtml(r.product_name || r.common_name || '')}', '${r.condition_type}', ${r.qty}, ${r.suggested_price})"></td>
                    <td>${formatDate(r.purchased_at)}</td>
                    <td><code style="font-size:12px;">${r.batch_no}</code></td>
                    <td><code style="font-size:12px;">${r.barcode}</code></td>
                    <td>${r.product_name || r.common_name || '-'}</td>
                    <td><span class="condition-badge ${getConditionClass(r.condition_type)}">${getConditionName(r.condition_type)}</span></td>
                    <td style="font-weight:bold;">${r.qty}</td>
                    <td style="color:var(--danger); font-weight:bold;">¥${parseFloat(r.suggested_price).toFixed(2)}</td>
                    <td><button class="btn btn-sm btn-success" onclick="singlePrint(${r.batch_id})" style="font-size:12px;white-space:nowrap;">🖨️ 打印</button></td>
                </tr>
            `;
        }).join('');

        updateSelectedUI();
    }

    function escapeHtml(text) {
        return String(text).replace(/'/g, "\\'").replace(/"/g, '\\"');
    }

    function toggleSelectAll() {
        const checked = document.getElementById('selectAll').checked;
        allRecords.forEach(r => {
            const exists = selectedItems.some(s => s.batch_id === r.batch_id);
            if (checked && !exists) {
                selectedItems.push({
                    batch_id: r.batch_id,
                    barcode: r.barcode,
                    productName: r.product_name || r.common_name || '',
                    conditionType: r.condition_type,
                    qty: r.qty,
                    price: r.suggested_price
                });
            } else if (!checked) {
                selectedItems = selectedItems.filter(s => !allRecords.some(r => r.batch_id === s.batch_id));
            }
        });
        updateSelectedUI();
        renderPurchaseLogs(allRecords);
    }

    function toggleSelect(event, batchId, barcode, productName, conditionType, qty, price) {
        const index = selectedItems.findIndex(item => item.batch_id === batchId);
        if (index >= 0) {
            selectedItems.splice(index, 1);
        } else {
            selectedItems.push({ batch_id: batchId, barcode, productName, conditionType, qty, price });
        }
        
        const row = event.target.closest('tr');
        if (row) {
            row.style.backgroundColor = selectedItems.some(s => s.batch_id === batchId) ? 'var(--info-light)' : '';
        }
        
        updateSelectedUI();
        updateSelectAllState();
    }
    
    function updateSelectAllState() {
        if (allRecords.length === 0) {
            document.getElementById('selectAll').checked = false;
            return;
        }
        const allSelected = allRecords.every(r => selectedItems.some(s => s.batch_id === r.batch_id));
        document.getElementById('selectAll').checked = allSelected;
    }

    function updateSelectedUI() {
        const totalQty = selectedItems.reduce((sum, item) => sum + item.qty, 0);
        document.getElementById('selectedCount').textContent = 
            selectedItems.length > 0 ? `已选择 ${selectedItems.length} 条入库记录，共 ${totalQty} 个标签` : '';

        const container = document.getElementById('printButtonContainer');
        container.innerHTML = '';
        if (selectedItems.length > 0) {
            const btn = document.createElement('button');
            btn.className = 'btn btn-success';
            btn.textContent = `🖨️ 批量打印标签 (${selectedItems.length} 条)`;
            btn.onclick = openPrintModal;
            container.appendChild(btn);
        }
    }

    function openPrintModal() {
        if (selectedItems.length === 0) {
            alert('请先选择要打印标签的入库记录');
            return;
        }

        loadTemplates();

        const totalQty = selectedItems.reduce((sum, item) => sum + item.qty, 0);
        document.getElementById('selectedPrintCount').textContent = selectedItems.length;
        document.getElementById('totalLabels').textContent = totalQty;

        let summaryHtml = '';
        selectedItems.forEach(item => {
            summaryHtml += `<div style="padding:3px 0; border-bottom:1px solid var(--border);">
                ${item.productName} ×${item.qty} <span style="color:var(--danger);">¥${parseFloat(item.price).toFixed(2)}</span>
            </div>`;
        });
        document.getElementById('selectedItemsSummary').innerHTML = summaryHtml || '<div style="color:var(--text-tertiary);">暂无选中</div>';

        // 恢复上次使用的模板，没有则默认第一个
        const savedIndex = localStorage.getItem('ppmart_last_template');
        if (savedIndex !== null && labelTemplates[savedIndex]) {
            currentTemplateIndex = savedIndex;
            document.getElementById('labelTemplate').value = savedIndex;
        } else if (labelTemplates.length > 0) {
            currentTemplateIndex = '0';
            document.getElementById('labelTemplate').value = '0';
        }

        showModal('printModal');
    }

    function getSelectedTemplate() {
        const index = document.getElementById('labelTemplate').value;
        if (index === '' || !labelTemplates[index]) {
            alert('请先选择一个模板');
            return null;
        }
        return labelTemplates[index];
    }

    function previewLabels() {
        const template = getSelectedTemplate();
        if (!template) return;

        closeModal('printModal');
        showModal('previewModal');

        const densityFilter = {
            'light': 'opacity(0.8)',
            'normal': 'opacity(1)',
            'dark': 'opacity(1.1)'
        };

        // 预览使用 mm 单位
        let html = `<div style="display:inline-block; background:#fff; padding:12px; border-radius:4px; filter:${densityFilter[template.density]}">
            <div style="position:relative; width:${template.canvasWidth}mm; height:${template.canvasHeight}mm; border:1px dashed #ccc; overflow:visible;">`;

        template.elements.forEach((el, index) => {
            const content = getDefaultContent(el.type);
            if (el.type === 'barcode') {
                html += `<div style="
                    position:absolute;
                    left:${el.x}mm;
                    top:${el.y}mm;
                    width:${el.width}mm;
                    height:${el.height}mm;
                    display:flex;
                    align-items:center;
                    justify-content:center;
                    overflow:visible;
                    box-sizing:border-box;
                " class="preview-barcode-container" data-barcode="${sampleItem.barcode}" data-width="${el.width}" data-height="${el.height}"></div>`;
            } else {
                // 文本元素：只用 fontSize 控制大小，不设置容器宽高约束
                html += `<div style="
                    position:absolute;
                    left:${el.x}mm;
                    top:${el.y}mm;
                    font-size:${el.fontSize}mm;
                    font-weight:${el.fontWeight || 'normal'};
                    color:${el.color || '#000'};
                    white-space:nowrap;
                    line-height:1.2;
                ">${content}</div>`;
            }
        });

        html += '</div></div>';

        document.getElementById('previewContent').innerHTML = `
            <div style="display:flex; flex-direction:column; align-items:center; min-height:300px; justify-content:center;">
                ${html}
            </div>
            <div style="text-align:center; padding-top:16px; font-size:13px; color:var(--text-secondary);">
                尺寸: ${template.canvasWidth}×${template.canvasHeight}mm | 数量: ${selectedItems.reduce((s, i) => s + i.qty, 0)} 个
            </div>
        `;

        // 等待 DOM 更新后再生成条码
        requestAnimationFrame(() => {
            generatePreviewBarcodes();
        });
    }

    function generatePreviewBarcodes() {
        const containers = document.querySelectorAll('.preview-barcode-container');
        containers.forEach(container => {
            // 清空容器
            container.innerHTML = '';

            const barcode = container.dataset.barcode;
            const heightMm = parseFloat(container.dataset.height);

            const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            svg.style.width = '100%';
            svg.style.height = '100%';
            container.appendChild(svg);

            // 使用通用渲染函数
            if (!renderBarcode(svg, barcode, heightMm * 3.78 * 0.9)) {
                container.innerHTML = `<span style="font-family:monospace; font-size:3mm;">${barcode}</span>`;
            }
        });
    }

    // 自动检测条码格式
    function detectBarcodeFormat(barcode) {
        if (!barcode) return 'CODE128';
        const str = String(barcode).trim();
        // EAN13: 13位纯数字（需要校验位正确）
        if (/^\d{13}$/.test(str)) return 'EAN13';
        // EAN8: 8位纯数字
        if (/^\d{8}$/.test(str)) return 'EAN8';
        // UPC-A: 12位纯数字
        if (/^\d{12}$/.test(str)) return 'UPC';
        // 其他情况用 CODE128（支持任意字符，最通用）
        return 'CODE128';
    }

    // 生成条码的通用函数，带容错
    function renderBarcode(svg, barcode, height) {
        const format = detectBarcodeFormat(barcode);
        const formats = [format, 'CODE128', 'CODE39'];

        for (let i = 0; i < formats.length; i++) {
            try {
                JsBarcode(svg, barcode, {
                    format: formats[i],
                    displayValue: false,
                    width: 2,
                    height: height,
                    margin: 0
                });
                return true;
            } catch (e) {
                console.log('Format ' + formats[i] + ' failed for barcode ' + barcode + ':', e.message);
            }
        }
        return false;
    }

    function printLabelsDirect() {
        printLabelsFromTemplate(getSelectedTemplate());
    }

    function printLabels() {
        const template = getSelectedTemplate();
        if (!template) return;

        closeModal('printModal');
        printLabelsFromTemplate(template);
    }

    function directPrint() {
        const template = getSelectedTemplate();
        if (!template) return;

        if (!selectedItems.length) {
            alert('请先选择要打印的商品');
            return;
        }

        const printerName = document.getElementById('printerName').value.trim();

        const batchQty = {};
        selectedItems.forEach(item => { batchQty[item.batch_id] = item.qty; });
        const batchIds = selectedItems.map(item => item.batch_id);

        const btn = document.querySelector('.btn-success');
        const origText = btn.textContent;
        btn.textContent = '⏳ 正在打印...';
        btn.disabled = true;

        fetch('../api/direct_print.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                batch_ids: batchIds,
                batch_qty: batchQty,
                template: {
                    canvasWidth: template.canvasWidth,
                    canvasHeight: template.canvasHeight,
                    elements: template.elements
                },
                printer: printerName
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                closeModal('printModal');
                alert('✅ ' + (data.message || '打印完成'));
            } else {
                alert('❌ ' + (data.error || '打印失败'));
            }
        })
        .catch(err => {
            alert('❌ 请求失败: ' + err.message);
        })
        .finally(() => {
            btn.textContent = origText;
            btn.disabled = false;
        });
    }

    function singlePrint(batchId) {
        let template = getSelectedTemplate();
        if (!template) {
            const savedIndex = localStorage.getItem('ppmart_last_template');
            if (savedIndex !== null && labelTemplates[savedIndex]) {
                template = labelTemplates[savedIndex];
                document.getElementById('labelTemplate').value = savedIndex;
            } else if (labelTemplates.length > 0) {
                template = labelTemplates[0];
                document.getElementById('labelTemplate').value = '0';
                localStorage.setItem('ppmart_last_template', '0');
            } else {
                alert('请先创建一个标签模板');
                return;
            }
        }

        const record = allRecords.find(r => r.batch_id === batchId);
        if (!record) {
            alert('未找到该记录');
            return;
        }

        const qtyInput = prompt('请输入打印张数：', '1');
        if (qtyInput === null) return;
        const qty = parseInt(qtyInput);
        if (isNaN(qty) || qty < 1) {
            alert('请输入有效数量');
            return;
        }

        // 构造单条打印项（与批量浏览器打印同样的逻辑）
        const item = {
            barcode: record.barcode,
            productName: record.product_name || record.common_name || '',
            price: record.suggested_price,
            conditionType: record.condition_type,
            qty: qty
        };

        printSingleLabel(item, template, qty);
    }

    function printSingleLabel(item, template, qty) {
        const density = template.density || 'normal';
        const densityFilter = {
            'light': 'opacity(0.8)',
            'normal': 'opacity(1)',
            'dark': 'opacity(1.1)'
        };

        let html = '';
        for (let i = 0; i < qty; i++) {
            html += `<div class="label-page" style="position:relative; width:${template.canvasWidth}mm; min-height:${template.canvasHeight}mm; height:${template.canvasHeight}mm; filter:${densityFilter[density]}; box-sizing:border-box; overflow:hidden; page-break-after:always; page-break-inside:avoid; break-inside:avoid;">`;

            template.elements.forEach(el => {
                const content = getElementContentForItem(el.type, item);
                if (el.type === 'barcode') {
                    html += `<div style="
                        position:absolute;
                        left:${el.x}mm;
                        top:${el.y}mm;
                        width:${el.width}mm;
                        height:${el.height}mm;
                        display:flex;
                        align-items:center;
                        justify-content:center;
                        overflow:visible;
                        box-sizing:border-box;
                    " class="barcode-placeholder" data-barcode="${item.barcode}" data-width="${el.width}" data-height="${el.height}"></div>`;
                } else {
                    html += `<div style="
                        position:absolute;
                        left:${el.x}mm;
                        top:${el.y}mm;
                        font-size:${el.fontSize}mm;
                        font-weight:${el.fontWeight || 'normal'};
                        color:${el.color || '#000'};
                        white-space:nowrap;
                        line-height:1.2;
                    ">${content}</div>`;
                }
            });

            html += '</div>';
        }

        const printStyles = `
            <style>
                @media print {
                    @page { margin: 0mm; size: ${template.canvasWidth}mm ${template.canvasHeight}mm; }
                    html, body { margin: 0; padding: 0; }
                    .label-page {
                        margin: 0; padding: 0;
                        page-break-after: always;
                        page-break-inside: avoid;
                        overflow: hidden;
                    }
                    .label-page:last-child { page-break-after: avoid; }
                }
                html, body { margin: 0; padding: 0; }
                * { box-sizing: border-box; }
                div { font-family: -apple-system, BlinkMacSystemFont, sans-serif; overflow: visible; }
                @supports (-webkit-print-color-adjust: exact) {
                    div { -webkit-print-color-adjust: exact; }
                }
            </style>
        `;

        const printWindow = window.open('', '_blank', 'width=800,height=600');
        printWindow.document.write(`<!DOCTYPE html>
<html>
<head>
    <title>打印标签</title>
    <meta charset="UTF-8">
    ${printStyles}
</head>
<body>
${html}
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"><\/script>
<script>
function detectBarcodeFormat(barcode) {
    if (!barcode) return 'CODE128';
    var str = String(barcode).trim();
    if (/^\\d{13}$/.test(str)) return 'EAN13';
    if (/^\\d{8}$/.test(str)) return 'EAN8';
    if (/^\\d{12}$/.test(str)) return 'UPC';
    return 'CODE128';
}
function waitForJsBarcode(callback, maxAttempts) {
    var attempts = 0;
    var check = function() {
        attempts++;
        if (typeof JsBarcode !== 'undefined') {
            callback();
        } else if (attempts < maxAttempts) {
            setTimeout(check, 100);
        } else {
            console.error('JsBarcode failed to load');
            callback();
        }
    };
    check();
}
waitForJsBarcode(function() {
    var placeholders = document.querySelectorAll('.barcode-placeholder');
    placeholders.forEach(function(placeholder) {
        var barcode = placeholder.dataset.barcode;
        var widthMm = parseFloat(placeholder.dataset.width);
        var heightMm = parseFloat(placeholder.dataset.height);
        var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.style.width = '100%';
        svg.style.height = '100%';
        placeholder.appendChild(svg);
        if (typeof JsBarcode !== 'undefined') {
            var format = detectBarcodeFormat(barcode);
            try {
                JsBarcode(svg, barcode, {
                    format: format,
                    displayValue: false,
                    width: 2,
                    height: heightMm * 3.78 * 0.9,
                    margin: 0
                });
            } catch (e) {
                try {
                    JsBarcode(svg, barcode, {
                        format: 'CODE128',
                        displayValue: false,
                        width: 2,
                        height: heightMm * 3.78 * 0.9,
                        margin: 0
                    });
                } catch (e2) {
                    placeholder.innerHTML = '<span style="font-family:monospace; font-size:3mm;">' + barcode + '</span>';
                }
            }
        } else {
            placeholder.innerHTML = '<span style="font-family:monospace; font-size:3mm;">' + barcode + '</span>';
        }
    });
    setTimeout(function() { window.print(); }, 200);
}, 50);
<\/script>
</body>
</html>`);
        printWindow.document.close();
    }

    function printLabelsFromTemplate(template) {
        const density = template.density || 'normal';
        const densityFilter = {
            'light': 'opacity(0.8)',
            'normal': 'opacity(1)',
            'dark': 'opacity(1.1)'
        };

        let html = '';
        selectedItems.forEach(item => {
            for (let i = 0; i < item.qty; i++) {
                html += `<div class="label-page" style="position:relative; width:${template.canvasWidth}mm; min-height:${template.canvasHeight}mm; height:${template.canvasHeight}mm; filter:${densityFilter[density]}; box-sizing:border-box; overflow:hidden; page-break-after:always; page-break-inside:avoid; break-inside:avoid;">`;

                template.elements.forEach(el => {
                    const content = getElementContentForItem(el.type, item);
                    if (el.type === 'barcode') {
                        html += `<div style="
                            position:absolute;
                            left:${el.x}mm;
                            top:${el.y}mm;
                            width:${el.width}mm;
                            height:${el.height}mm;
                            display:flex;
                            align-items:center;
                            justify-content:center;
                            overflow:visible;
                            box-sizing:border-box;
                        " class="barcode-placeholder" data-barcode="${item.barcode}" data-width="${el.width}" data-height="${el.height}"></div>`;
                    } else {
                        // 文本元素：只用 fontSize 控制大小
                        html += `<div style="
                            position:absolute;
                            left:${el.x}mm;
                            top:${el.y}mm;
                            font-size:${el.fontSize}mm;
                            font-weight:${el.fontWeight || 'normal'};
                            color:${el.color || '#000'};
                            white-space:nowrap;
                            line-height:1.2;
                        ">${content}</div>`;
                    }
                });

                html += '</div>';
            }
        });

        const printStyles = `
            <style>
                @media print {
                    @page {
                        margin: 0mm;
                        size: ${template.canvasWidth}mm ${template.canvasHeight}mm;
                    }
                    html, body {
                        margin: 0;
                        padding: 0;
                    }
                    .label-page {
                        margin: 0;
                        padding: 0;
                        page-break-after: always;
                        page-break-inside: avoid;
                        overflow: hidden;
                    }
                    .label-page:last-child {
                        page-break-after: avoid;
                    }
                }
                html, body {
                    margin: 0;
                    padding: 0;
                }
                * {
                    box-sizing: border-box;
                }
                div {
                    font-family: -apple-system, BlinkMacSystemFont, sans-serif;
                    overflow: visible;
                }
                @supports (-webkit-print-color-adjust: exact) {
                    div { -webkit-print-color-adjust: exact; }
                }
            </style>
        `;

        const printWindow = window.open('', '_blank', 'width=800,height=600');
        printWindow.document.write(`<!DOCTYPE html>
<html>
<head>
    <title>打印标签</title>
    <meta charset="UTF-8">
    ${printStyles}
</head>
<body>
${html}
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"><\/script>
<script>
function detectBarcodeFormat(barcode) {
    if (!barcode) return 'CODE128';
    var str = String(barcode).trim();
    if (/^\\d{13}$/.test(str)) return 'EAN13';
    if (/^\\d{8}$/.test(str)) return 'EAN8';
    if (/^\\d{12}$/.test(str)) return 'UPC';
    return 'CODE128';
}

function waitForJsBarcode(callback, maxAttempts) {
    var attempts = 0;
    var check = function() {
        attempts++;
        if (typeof JsBarcode !== 'undefined') {
            callback();
        } else if (attempts < maxAttempts) {
            setTimeout(check, 100);
        } else {
            console.error('JsBarcode failed to load');
            callback();
        }
    };
    check();
}

waitForJsBarcode(function() {
    var placeholders = document.querySelectorAll('.barcode-placeholder');
    placeholders.forEach(function(placeholder) {
        var barcode = placeholder.dataset.barcode;
        var widthMm = parseFloat(placeholder.dataset.width);
        var heightMm = parseFloat(placeholder.dataset.height);

        var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.style.width = '100%';
        svg.style.height = '100%';
        placeholder.appendChild(svg);

        if (typeof JsBarcode !== 'undefined') {
            var format = detectBarcodeFormat(barcode);
            try {
                JsBarcode(svg, barcode, {
                    format: format,
                    displayValue: false,
                    width: 2,
                    height: heightMm * 3.78 * 0.9,
                    margin: 0
                });
            } catch (e) {
                try {
                    JsBarcode(svg, barcode, {
                        format: 'CODE128',
                        displayValue: false,
                        width: 2,
                        height: heightMm * 3.78 * 0.9,
                        margin: 0
                    });
                } catch (e2) {
                    placeholder.innerHTML = '<span style="font-family:monospace; font-size:3mm;">' + barcode + '</span>';
                }
            }
        } else {
            placeholder.innerHTML = '<span style="font-family:monospace; font-size:3mm;">' + barcode + '</span>';
        }
    });

    setTimeout(function() {
        window.print();
    }, 200);
}, 50);
<\/script>
</body>
</html>`);
        printWindow.document.close();
    }

    function getElementContentForItem(type, item) {
        switch(type) {
            case 'barcode': return item.barcode;
            case 'barcodeText': return item.barcode;
            case 'name': return item.productName;
            case 'price': return '¥' + parseFloat(item.price).toFixed(2);
            case 'condition': 
                return allConditionTypes.map(c => 
                    `[${c === item.conditionType ? '☑' : '□'}] ${getConditionName(c)}`
                ).join(' ');
            default: return type;
        }
    }

    function renderPagination(total, pageSize) {
        totalPages = Math.ceil(total / pageSize);
        const pagination = document.getElementById('pagination');
        
        if (totalPages <= 1) {
            pagination.innerHTML = '';
            return;
        }

        let html = '';
        if (currentPage > 1) {
            html += `<button onclick="searchPurchaseLogs(${currentPage - 1})">上一页</button>`;
        }

        for (let i = 1; i <= totalPages; i++) {
            if (i === currentPage) {
                html += `<button class="active">${i}</button>`;
            } else {
                html += `<button onclick="searchPurchaseLogs(${i})">${i}</button>`;
            }
        }

        if (currentPage < totalPages) {
            html += `<button onclick="searchPurchaseLogs(${currentPage + 1})">下一页</button>`;
        }

        pagination.innerHTML = html;
    }

    function resetFilters() {
        document.getElementById('startDate').value = '';
        document.getElementById('endDate').value = '';
        document.getElementById('searchKeyword').value = '';
        document.getElementById('conditionType').value = '';
        selectedItems = [];
        searchPurchaseLogs(1);
    }

    function showModal(id) {
        document.getElementById(id).classList.add('show');
    }

    function closeModal(id) {
        document.getElementById(id).classList.remove('show');
    }

    window.addEventListener('resize', () => {
        if (document.getElementById('editorModal').classList.contains('show')) {
            initFabricCanvas();
        }
    });

    document.addEventListener('DOMContentLoaded', () => {
        loadTemplates();
        loadSystemSettings().then(() => {
            const today = new Date().toISOString().split('T')[0];
            const thirtyDaysAgo = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
            document.getElementById('startDate').value = thirtyDaysAgo;
            document.getElementById('endDate').value = today;
            searchPurchaseLogs(1);
        });
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Delete' && document.getElementById('editorModal').classList.contains('show')) {
            deleteFabricSelected();
        }
    });

    document.getElementById('labelTemplate').addEventListener('change', function() {
        currentTemplateIndex = this.value;
        if (this.value !== '') {
            localStorage.setItem('ppmart_last_template', this.value);
            loadTemplateToEditor(this.value);
        } else {
            localStorage.removeItem('ppmart_last_template');
        }
    });
    </script>