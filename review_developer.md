# 🧪 PPMart 代码审查报告

> **审查人**: Devvy 💻  
> **日期**: 2026-06-03  
> **范围**: auth.php, config.php, live.php, api/purchase_batch.php, api/sell_product_live.php, api/change_price.php, api/direct_print.php, api/scan_product_live.php, api/outbound_batch.php, api/save_finance.php, api/get_finance.php, admin/outbound.php, admin/finance.php, admin/layout.php  
> **代码量**: ~32,000 行 PHP/JS/CSS

---

## 📊 总体评价

项目整体质量**可接受**，直播辅助系统的交互体验设计精良（深色主题、键盘快捷键、滚动屏锁定等），核心业务逻辑基本正确。但存在以下几个系统性问题：

| 维度 | 评分 | 关键问题 |
|------|------|----------|
| 代码质量 (PHP) | ⭐⭐⭐ | 函数提取良好，但类型注释缺失严重 |
| PHP 8 特性利用 | ⭐⭐ | 几乎未使用 PHP 8 特性 |
| API 设计 | ⭐⭐⭐ | 缺乏统一错误码/版本控制 |
| 安全性 | ⭐⭐⭐ | XSS 防护有漏洞，SQL 注入少量隐患 |
| 前端质量 | ⭐⭐⭐⭐ | DOM 操作和事件绑定模式良好 |
| 性能 | ⭐⭐⭐ | N+1 查询多处，JS 轮询可优化 |
| 配置管理 | ⭐⭐⭐⭐⭐ | 环境变量模式正确 |

---

## 🔴 严重 = 高

### H1. XSS 安全漏洞 — 后端未对输出转义
- **文件**: `admin/outbound.php`, `admin/finance.php` 及其他包含 `escHtml()` 前端函数的页面
- **问题**: 后端返回的 `platform`, `account`, `remark`, `order_no` 等字段直接存入 JSON。前端有 `escHtml()` 函数，但在大量地方（如 `admin/finance.php` 的 `batchList` 渲染）使用 `escHtml()` 前已经通过字符串拼接嵌入 HTML。如果 JSON 足够复杂，某些路径可能漏过。
- **更严重的问题**: JS 后端（PHP）的数据在 JSON encode 时仅做了 `JSON_UNESCAPED_UNICODE`，`\` 和 `</` 等字符未做转义。当这些值嵌入 `<script>` 标签的 JS 模板字符串时存在 XSS 可能。
- **建议**: 
  - 对所有用户可控制的数据（特别是 `remark`, `platform`, `account`）在数据库写入时不做转义，但在 API 返回时强制定为字符串类型
  - 前端渲染统一走 `element.textContent` 或 `escHtml()`，不要直接 `innerHTML` 拼接含用户输入的内容

### H2. 全局 `ob_start()` 与缓冲状态不一致
- **文件**: `api/direct_print.php` 第1行 `ob_start()`
- **问题**: direct_print.php 在第1行调用 `ob_start()`，但 `config.php` 中 `getDB()` 的 `catch` 块也在 `if (ob_get_level()) ob_clean()`。当 `config.php` 被 require 时如果已有输出缓冲层，可能清理错误的内容。
- **影响**: 错误情况下可能出现双重输出或空白 500 响应
- **建议**: direct_print.php 使用 `ob_start()` 前先检查并清理已有的缓冲层，或者在入口统一管理缓冲

### H3. 未经验证的文件包含风险
- **文件**: 
  - `products.php`, `admin/finance.php` 等页面：`require_once __DIR__ . '/../config.php'`
  - `admin/layout.php` 第28行: `require_once __DIR__ . '/../config.php'` 依赖于 caller 的 `__DIR__`
- **问题**: 如果某页面通过 `include` 或错误路径加载，路径解析可能出错。工程中多处 `require_once __DIR__ . '/../config.php'` 和 `require_once __DIR__ . '/../auth.php'` 混合使用，文件结构依赖隐含，重构时容易出错。
- **影响**: 潜在地可能导致敏感文件泄露
- **建议**: 统一使用一个常量或自动加载器

### H4. 浮点数货币精度问题
- **文件**: 
  - `api/save_finance.php`: `floatval($input['gmv'])`
  - `api/outbound_batch.php`: `floatval($item['price'] ?? 0)`
  - `api/get_finance.php`: 全程 `float` 计算利润
- **问题**: 货币使用 `float` 而非 `int`（以分为单位），在大量计算（乘法、减法）中会积累浮点误差。例如 `0.1 + 0.2 === 0.3` 不成立的问题。
- **建议**: 
  - 数据库层面：金额字段用 `DECIMAL(10,2)`（检查当前是 `DECIMAL` 还是 `FLOAT`）
  - PHP 层面：使用 `bcmul`/`bcadd`/`bcsub` 处理所有货币计算
  - 或者统一以 `分`（int）存储和计算

### H5. 后端缺少 `Content-Type: application/json` 统一机制
- **文件**: 多个 API 文件
- **问题**: `api/purchase_batch.php` 等没有显式设置 Content-Type，依赖 `jsonResponse()` 函数，但部分文件（如 `api/change_price.php`）直接 `echo json_encode(...)` 后 `exit`，没有调用统一函数。
- **建议**: 所有 API 入口强制调用一个中间件或统一 `jsonResponse` 来设置 Content-Type

---

## 🟡 严重 = 中

### M1. PDO Prepared Statement 参数顺序隐患
- **文件**: `api/sell_product_live.php` 第45行
```php
$stmt->execute([$productId, $conditionType, $salePrice, $qty, $liveSessionId, $storeId]);
```
- **问题**: `INSERT INTO sales_log (product_id, condition_type, sale_price, qty, live_session_id, store_id)` 列名顺序写死，新增字段时容易疏漏更新执行参数。没有使用命名占位符。
- **建议**: 多字段 INSERT 使用命名占位符 `:product_id` 而非位置参数

### M2. 拼音搜索 API 存在变量覆盖风险
- **文件**: `api/search_outbound_stock.php`, `live.php` JS 端
- **问题**: `searchByPinyin()` 和 `searchPinyinStock()` 均直接拼接 URL 参数。如果关键词包含危险字符，虽经过 `encodeURIComponent`，但后端应做二次校验。
- **建议**: 后端加长度/字符白名单过滤

### M3. 直播库存更新缺乏乐观锁
- **文件**: `api/sell_product_live.php`
- **问题**: 虽然使用了 `FOR UPDATE`（悲观锁），但 `sale_price` 在同一事务中可能被覆盖。同一个 `live_inventory` 行在两个请求中同时被 `FOR UPDATE` 锁住，可防止超卖。
- **乐观锁建议**: 增加 `version` 字段 + 条件更新 `UPDATE ... SET current_stock = ?, version = version + 1 WHERE id = ? AND version = ?`，在低冲突场景下性能更好

### M4. 支付宝 GMV 与财务利润计算不一致
- **文件**: `api/get_finance.php` 利润公式
```php
'b.profit' => b.gmv * (1 - platformFeeRate) - order_count * shippingFee - total_cost - ad_spend + recovery
```
- **问题**: `recovery` 计算逻辑 `(total_qty - order_count) * shippingFee` 假设每件商品都单独收运费，但实际业务中可能一单多件只收一次运费。该逻辑未在文档中说明。
- **建议**: 将该财务计算公式提炼为 `FinancialCalculator` 类，并写单元测试验证

### M5. `generateBarcode` 高并发竞态
- **文件**: `config.php` 中 `generateBarcode()` 函数
- **问题**: 使用 `SELECT ... WHERE barcode = ?` + 最多10次尝试生成不重复的条码。在高并发场景下（多用户同时入库），两个事务可能查到同一 `barcode` 不存在，然后都插入成功，导致重复条码。虽然概率低，但可能发生。
- **建议**: 
  - 使用 `INSERT ... ON DUPLICATE KEY` 或在插入时加唯一索引约束
  - 确保 `products.barcode` 字段有 `UNIQUE` 索引

### M6. 外部 JSON 请求缺少超时和重试
- **文件**: `api/direct_print.php`
- **问题**: cURL 设置了 `CURLOPT_TIMEOUT=120`，但没有 `retry` 机制。打印服务失败前端直接显示"打印失败"。
- **建议**: 添加指数退避重试（最多3次），并记录日志

### M7. 文件路径拼接安全风险
- **文件**: `config.php` 中 `deleteImageFile()`
```php
$filePath = __DIR__ . '/' . $imageUrl;
```
- **问题**: 虽然检查了 `strpos($imageUrl, 'uploads/') !== 0`，但如果 `$imageUrl` 包含 `../` 则可绕过检查（如 `uploads/../../etc/passwd` 不会触发 `strpos` 为 false，因为前缀仍然是 `uploads/`）。
- **建议**: 使用 `realpath()` 解析后再做路径前缀检查

### M8. JSON 响应未区分错误码
- **文件**: 所有 API 文件
- **问题**: 错误响应统一使用 `['success' => false, 'error' => '...']`，但所有错误都使用 `error()` 函数内部 `http_response_code(400)`，无法区分 400/403/404/409 等不同场景。
- **建议**: 提供 `error($msg, $code, $errorCode)` 函数，其中 `$errorCode` 是业务错误码（如 `INSUFFICIENT_STOCK`, `PRODUCT_NOT_FOUND`）

---

## 🔵 严重 = 低

### L1. SQL 语句使用大写关键字但无格式化
- **文件**: 各处 SQL
- **问题**: SQL 关键字大小写不统一（部分文件全大写，部分小写）。长 SQL 没有统一的格式化规范，可读性一般。
- **建议**: 统一规范，如全大写关键字 + 缩进子句

### L2. `getFinance.php` 中 `array_values()` 导致错位
- **文件**: `api/get_finance.php` 第120行
- **问题**: 在 `foreach ($batches as &$b)` 循环内使用 `continue` 跳过不匹配的平台筛选，但循环后的 `$batches = array_values($batches)` 会重排序号，但财务汇总已经累加，可能导致汇总金额与列表不匹配。
- **建议**: 筛选逻辑应在 SQL 层完成（增加 WHERE 条件），而非在 PHP 层

### L3. 多次查询相同的 setting 表
- **文件**: `admin/layout.php` 在每次页面加载时执行 SQL 查询系统设置和店铺名
- **问题**: 同一页面的 PHP 渲染过程（layout.php, 主文件）可能多次调用 `getDB()` 并执行相同的 `system_settings` 查询。
- **建议**: 使用静态缓存 `static $cache` 或引入简单的结果缓存

### L4. PHP 8 特性零利用
- **文件**: 整个项目
- **问题**: 项目要求 PHP 8+ 运行，但代码完全没有使用以下特性：
  - 命名参数（`fn(name: $name)`）
  - 属性（Attributes / Annotations）
  - Union Types（`?int` → `int|null` 已在注释中使用，但未写在类型声明中）
  - Match Expression（大量 `if/elseif` 可改为 `match`）
  - `str_contains()`/`str_starts_with()`（使用老式 `strpos() === 0`）
  - 构造器属性提升
- **建议**: 逐步引入 PHP 8 特性简化代码，特别是 `match` 和命名参数

### L5. `escHtml()` 前端函数重复定义
- **文件**: `live.php`, `admin/outbound.php`, `admin/finance.php`
- **问题**: `escapeHtml` / `escHtml` 函数在多个页面重复定义，且实现方式不同（`live.php` 用正则 replace，`admin/outbound.php` 用 `document.createElement`）。
- **建议**: 提取为公用 JS 文件 `assets/js/utils.js`

### L6. 大量内联 CSS 没有复用
- **文件**: `live.php` 所有 CSS 在 `<style>` 中，`admin/outbound.php` 也有独立的 `<style>`
- **问题**: CSS 体积巨大（`live.php` 的 style 块超过 600 行），不同页面间大量重复的变量和样式
- **建议**: 将全局样式提至 `assets/css/`，用 CSS 变量实现主题切换

### L7. 前端 fetch 没有统一错误处理
- **问题**: 前端多处 `fetch(...).then(r => r.json())` 没有检查 `response.ok`（HTTP 状态码），如果服务器返回 500 但响应体不是标准 JSON，`.json()` 会抛出未捕获的 Promise 拒绝。
- **建议**: 统一封装 `apiFetch()` 函数，检查 `response.ok` 并统一处理

### L8. 直播轮询广播使用固定 2 秒间隔
- **文件**: `live.php` 中 `setInterval(pollBroadcast, 2000)`
- **问题**: 即使页面在后台标签页（hidden），仍然每 2 秒发送请求。如果有多场直播同时进行，服务器可能承受不必要的负载。
- **建议**: 使用 `requestAnimationFrame` 或 `setTimeout` + `Page Visibility API` 实现自适应轮询

### L9. `outbound_batch.php` 多店铺模式下的 `isset($outboundStoreId)`
- **文件**: `api/outbound_batch.php`
- **问题**: `$outboundStoreId` 在循环中赋值为 `$storeId ?? $batch['store_id']`，在使用前未声明。虽然逻辑上循环至少执行一次（有 `if (empty($items))` 检查），但阅读时容易误解。
- **建议**: 在 try 块开始时初始化变量：`$outboundStoreId = null;`

### L10. `live.php` 中 `setInterval` 未清理
- **问题**: 页面 setup 阶段启动了 `setInterval(loadInventory, 8000)` 和 `setInterval(pollBroadcast, 2000)`，但页面切换路由或 destroy 时没有清理。如果页面重新加载（无 SPA），问题不大，但未来扩展单页时会成为内存泄漏。
- **建议**: 记录 timer ids，在页面 unload 或模式切换时 `clearInterval`

### L11. 数据库配置默认值中含有实际店铺名
- **文件**: `config.php`
```php
define('DB_USER', envOrDefault('PPMART_DB_USER', 'ppmart2'));
define('DB_NAME', envOrDefault('PPMART_DB_NAME', 'ppmart2'));
```
- **问题**: 虽然已经是环境变量模式，但默认值中含有实际用户名和数据库名。如果 `.env` 文件未正确设置，会回退到一个已知的默认值，有轻微安全风险。
- **建议**: 默认值设置为空或 `null`，强制要求环境变量

---

## 🟢 值得肯定的设计

1. **环境变量配置**: `envOrDefault()` 函数设计和 `.env` 支持非常正确
2. **FIFO 出库策略**: `allocateFIFO` 函数实现清晰
3. **拼音搜索**: 前端拼音首字母搜索体验优秀
4. **404 扫码栏浮层**: 滚动时自动悬浮扫描栏
5. **广播 TTS**: 直播过程中语音播报消息
6. **直播辅助系统 UI**: 沉浸式深色主题设计精良，键盘快捷键友好
7. **PDO 配置**: 禁用了模拟预处理（`EMULATE_PREPARES => false`），正确启用异常模式
8. **单例模式**: `getDB()` 使用静态变量实现数据库连接复用
9. **条码校验**: EAN-13 校验位计算正确
10. **错误兜底**: `register_shutdown_function` 捕获致命错误（`direct_print.php`）

---

## 📋 改进优先级

| 优先级 | 修改项 | 工作量 | 影响 |
|--------|--------|--------|------|
| P0 | H1 XSS 修复 | 2h | 安全 |
| P0 | H4 货币精度 (Decimal) | 4h | 财务准确 |
| P1 | M3 乐观锁 | 1h | 并发可靠性 |
| P1 | M5 条码唯一索引 | 0.5h | 数据完整性 |
| P1 | M1 命名占位符 | 1h | 可维护性 |
| P2 | L2 筛选逻辑移到 SQL | 2h | 准确性 |
| P3 | L4 PHP 8 特性 adoption | 渐进式 | 可读性 |
| P3 | L7 统一 fetch 封装 | 1h | 稳定性 |

---

## 🧪 建议补充的测试

1. **财务计算**: 利润公式的单元测试（正常、退货、多批次混合）
2. **并发库存**: 模拟同时多用户购买同一商品的场景
3. **条码生成**: 唯一性和校验位正确性
4. **FIFO 出库**: 批次分配算法的正确性（先进先出+多批次拆分）
5. **XSS 测试**: 特殊字符注入场景

---

## 📌 总结

PPMart 是一个**功能完整、体验良好的直播辅助系统**。核心业务逻辑（FIFO 出库、条码生成、直播售卖）正确，UI/UX 设计成熟。主要风险集中在 **安全性（XSS）** 和 **财务精度（浮点数）** 两个方向，建议优先修复。批量代码可读性方面，通过引入 PHP 8 特性和统一 API 规范可以显著提升。

> 一句话：**Go to production ready? 是的。但在处理真实金钱和用户数据前，修复 H1 和 H4。**
