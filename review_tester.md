# PPMart 测试审查报告 🧪

**审查日期**: 2026-06-03
**审查人**: Testy (AI 测试工程师)
**项目**: PPMart — 泡泡玛特盲盒直播进销存系统
**版本**: 生产运行中（基于 git log 推定）

---

## 1. 测试覆盖现状

### 测试文件
- **数量**: 0
- **任何形式的自动化测试**: ❌ 不存在
- `*test*` / `*spec*` / `*phpunit*` 文件: **无**
- `composer.json` / `package.json`: **无**（PHP 纯原生项目，无依赖管理）

### CI/CD
- `.github/`, `.gitlab-ci.yml`, `.circleci/`, `Dockerfile*`, `docker-compose*`: **全部不存在**
- 部署方式看起来是手动上传到服务器（存在 `.user.ini`、`__MACOSX` 元数据残留、`.bak` 文件并存） → 说明部署流程不规范

### 开发流程
- 存在 `.bak` 后缀备份文件（如 `outbound.php.bak.202605191728`）→ 无版本控制之外的自动化回滚机制
- `__MACOSX/` 目录残留 → 开发者在 macOS 上工作，上传时带了系统元数据

### 关键结论
> **该项目当前处于「零测试 + 手动部署」阶段，风险极高。**

---

## 2. 测试场景矩阵

### 2.1 商品 CRUD

| 场景 | 优先级 | 说明 |
|------|--------|------|
| 创建商品（全部必填/选填字段） | P0 | `add_product.php` — name 必填，其他可选 |
| 创建商品 — 空名称 | P0 | 应返回 `请提供商品名称` |
| 创建商品 — 条码重复 | P0 | 条码唯一性校验 |
| 创建商品 — 条码自动生成 | P1 | EAN-13 校验位计算正确性 |
| 创建商品 — 超长名称/简介 | P1 | 数据库字段长度限制 vs 输入 |
| 更新商品 — 修改条码 | P0 | 条码冲突检测 |
| 更新商品 — 修改图片路径 | P2 | 旧图片文件清理逻辑 |
| 删除商品 — 级联删除 | P0 | 删除 products + 关联表（`purchase_log`, `inventory_log`, `inventory_batches`） |
| 批量删除商品 | P1 | `product_ids` 数组兼容性 |
| 批量导入(CSV/Excel) | P1 | 文件解析、编码转换、空行跳过、行数过多超时 |

### 2.2 库存管理

| 场景 | 优先级 | 说明 |
|------|--------|------|
| 入库 — 创建批次 | P0 | `purchase.php` — 进价、数量、状态 |
| 入库 — 相同商品相同状态重复入库 | P1 | 多批次 FIFO 处理 |
| 手动调整库存 — 增加 | P1 | 无历史批次时拒绝 |
| 手动调整库存 — 减少 | P0 | 从最早批次 FIFO 扣减 |
| 手动调整库存 — 减少超过库存量 | P0 | 拒绝并报错 |
| 库存盘点 — 完整数据加载 | P1 | `inventory_audit.php` |
| 库存导出 | P2 | CSV 格式 |

### 2.3 直播售卖全链路

| 场景 | 优先级 | 说明 | 涉及文件 |
|------|--------|------|----------|
| 创建直播场次 | P0 | 结束旧场次 + 复制库存快照 | `api/create_session.php` |
| 创建场次 — 零库存商品 | P1 | 只有无库存商品时的行为 | `api/create_session.php` |
| 扫描商品进场次 | P0 | 条码/拼音搜索 + 库存快照加载 | `live.php`, `api/scan_product_live.php` |
| 直播售出商品 | **P0** | 并发扣减库存 `FOR UPDATE` | `api/sell_product_live.php` |
| 直播售出 — 库存不足 | P0 | `current_stock < qty` 拒绝 | `api/sell_product_live.php` |
| 直播退还商品 | P0 | 检查已售未退数量 | `api/return_product_live.php` |
| 直播退还 — 超过已售数 | P0 | 防止刷库存 | `api/return_product_live.php` |
| 直播退还 — 超过初始库存 | P0 | `afterQty > initial_stock` 拒绝 | `api/return_product_live.php` |
| 同一商品多状态售卖 | P1 | sealed/opened/boxless/flawed 各自追踪 | `live_inventory` 表 |
| 场次切换 — 多场次并行 | P1 | 理论上只有一场 active | `create_session.php` |

### 2.4 出库流程

| 场景 | 优先级 | 说明 |
|------|--------|------|
| 创建出库批次 | P0 | 多个批次商品一起出库 |
| 出库 — 库存不足 | P0 | 事务中 `FOR UPDATE` 锁定行 |
| 出库 — 扣减库存 | P0 | `remaining_qty` 正确更新 |
| 出库 — 回滚/删除批次 | P0 | `delete_outbound.php` — 恢复库存 |
| 出库 — 超管全平台视角 | P1 | `store_id` 为 null 时不过滤 |
| 出库 — 财务数据关联 | P1 | GMV / 订单数 / 投流费 |

### 2.5 多租户数据隔离

| 场景 | 优先级 | 说明 |
|------|--------|------|
| 用户 A 看到 A 店数据 | **P0** | `getStoreId()` 注入所有 SQL |
| 超管切换店铺 | P0 | `view_store_id` 机制 |
| 用户 A 越权查看 B 店数据 | **P0** | 通过修改 `store_id` 参数 |
| 越权操作（API 直接调用） | **P0** | 接口是否校验 `store_id` |

> ⚠️ **重要发现**: `delete_outbound.php` 在恢复库存时，`UPDATE inventory_batches` 的 SQL **没有校验 `store_id`**。如果有店铺A的 outbound 记录被 `store_id` 过滤掉了，但仍然会恢复该批次的库存（因为 `inventory_batches` 的更新语句没有加 `store_id` 条件）。
> 看 `delete_outbound.php` 第 54-55 行：
> ```php
> UPDATE inventory_batches SET remaining_qty = remaining_qty + ? WHERE id = ?
> ```
> 只根据 `batch_id` 更新，没有校验 `store_id`。

### 2.6 并发库存扣减

| 场景 | 优先级 | 说明 |
|------|--------|------|
| 多人同时售出同一商品 | **P0** | `FOR UPDATE` 行锁（已实现，需验证） |
| 高并发下死锁 | P1 | `beginTransaction` + `FOR UPDATE` 顺序一致性 |
| 并发售出 + 并发退还 | P1 | 退还也使用了 `FOR UPDATE` |
| 出库 + 售出同时发生 | P1 | 两个事务操作同一 `inventory_batches` |

> 直播售卖代码已使用 `FOR UPDATE`（加分），但需要压测验证锁粒度（是行锁还是间隙锁取决于索引）。

---

## 3. 边界情况分析

| 边界 | 风险等级 | 代码位置 | 问题 |
|------|----------|----------|------|
| **负数库存** | 🔴 高危 | `inventory_batches.remaining_qty` | 没有 `CHECK(remaining_qty >= 0)` 约束，纯靠应用层逻辑 |
| **零数量出库** | 🟡 中危 | `outbound_batch.php` L39 | `if ($qty <= 0)` 已拦截 |
| **大负数调整** | 🟡 中危 | `adjust_inventory.php` | `$adjustQty === 0` 拦截，但负数检查 OK |
| **超长商品名称 (>255)** | 🟢 低危 | 各 INSERT/UPDATE | MySQL varchar(255) 会截断但不会报错 |
| **特殊字符/注入** | 🔴 高危 | 多处动态 SQL | **见第4节安全分析** |
| **库存溢出** | 🟢 低危 | 各种 SUM/累加 | DECIMAL(12,2) 最大值 99999999.99，商业场景足够 |
| **价格为 0** | 🟡 中危 | 各处价格校验 | 部分地方只检查 `empty` 可能放过 0 |
| **空数组出库** | 🟢 低危 | `outbound_batch.php` | `if (empty($items))` 已拦截 |
| **空数据批量导入** | 🟡 中危 | `bulk_import_products.php` | 跳过空行，但空文件可能只报错不够友好 |
| **时间戳边界** | 🟡 中危 | 财务报表查询 | `start_date` + `00:00:00` / `end_date` + `23:59:59` 粒度可能漏单 |
| **Unicode/Emoji 名称** | 🟢 低危 | `sanitizeSeriesDir` | 未过滤 Emoji，可能造成目录名异常 |

---

## 4. 安全测试分析

### 4.1 认证与会话
| 项目 | 状态 | 说明 |
|------|------|------|
| 密码哈希 | ✅ | 使用 `password_hash(PASSWORD_DEFAULT)` / `password_verify` |
| 会话 Cookie | ✅ | `httponly=true`, `samesite=Lax` |
| 会话固定保护 | ⚠️ | `session_regenerate_id()` **未调用** — 登录后 session_id 不变 |
| CSRF Token | ❌ | **完全缺失** — 所有 POST API 仅靠 Session Cookie 验证 |
| 暴力破解防护 | ❌ | 登录无验证码 / 无限流 |

### 4.2 SQL 注入
| 项目 | 状态 | 说明 |
|------|------|------|
| 主流程 (CRUD) | ✅ | 全部使用 PDO Prepared Statements |
| 动态 IN 子句 | ⚠️ | `implode(',', array_fill(...))` 生成占位符 — `$productIds` 需确保为整型 |
| ORDER BY 动态拼接 | ⚠️ | `list_products.php` 中 `ORDER BY ...` 拼接了用户输入 `$keyword`，没有使用白名单 |

### 4.3 XSS (跨站脚本)
| 项目 | 状态 | 说明 |
|------|------|------|
| 登录页面 | ✅ | `htmlspecialchars()` 使用了 |
| 后台页面 | ⚠️ | 大量 PHP 文件直接 `echo` 变量（如 `$product['name']`），多数在 `<td>` 标签内，但部分地方未转义 |
| 商品图片 URL | 🟡 | 如果恶意用户上传 `javascript:alert(1)` 类型的 URL，前端直接作为 `<img src>` 渲染 |

### 4.4 越权访问
| 项目 | 状态 | 说明 |
|------|------|------|
| API 级别 auth | ✅ | 全部 API 调用 `requireAuth()` |
| 店铺数据隔离 | ⚠️ | `getStoreId()` 实现的 store-level 隔离，但少数 API 存在遗漏 (如 `delete_outbound.php` 库存恢复) |
| 超管权限 | ✅ | `requireSuperAdmin()` + `isSuperAdmin()` |

### 4.5 文件上传安全
| 项目 | 状态 | 说明 |
|------|------|------|
| MIME 类型校验 | ✅ | 使用 `finfo` 检测真实 MIME |
| 大小限制 | ✅ | 10MB 上限 |
| 路径遍历 | ✅ | `sanitizeSeriesDir()` 过滤 `..` 和 `/` |
| 扩展名白名单 | ⚠️ | 降级逻辑使用扩展名判断，但配合 `move_uploaded_file` 风险可控 |

### 4.6 CSRF
> **不存在任何 CSRF 保护机制。**
> 所有 POST API 仅依赖 Cookie 中的 Session ID 进行身份认证，没有 CSRF Token。
> 攻击者可以构造恶意页面，在受害者已登录 PPMart 的情况下，诱导其点击/提交表单，执行任意业务操作（创建商品、出库、删除等）。

---

## 5. 质量风险排名

以下是按**业务影响 × 发生概率**排序的 TOP 风险：

| 排名 | 风险 | 影响 | 概率 | 紧急度 |
|------|------|------|------|--------|
| 🥇 | **并发库存超卖** — 虽然有 `FOR UPDATE`，但没有压测验证，高并发直播时可能超卖 | 财务损失、客户投诉 | 中 | 🔴 立即 |
| 🥇 | **数据完整性缺失 — 数据库无 CHECK 约束** (`remaining_qty >= 0`) | 负数库存、数据不一致 | 中 | 🔴 立即 |
| 🥇 | **出库库存恢复越权** — `delete_outbound.php` 恢复库存不校验 `store_id` | 跨店铺库存篡改 | 低但后果严重 | 🔴 立即 |
| 🥇 | **无自动化测试** — 任何变更都可能引入回归 | 全部 | 高 | 🔴 立即 |
| 🥈 | **直播售卖价 0 元** — `salePrice <= 0` 已校验，但数据库无约束 | 0元售出 | 低 | 🟡 高 |
| 🥈 | **CSRF 漏洞** — 所有 API 无 CSRF Token | 任意操作可被跨站利用 | 中 | 🟡 高 |
| 🥈 | **CRUD 事务完整性** — 部分操作(如 `delete_product.php`)在事务内，但部分操作(如简单的商品更新)无事务 | 部分更新失败导致不一致 | 低 | 🟡 高 |
| 🥉 | **财务报表精度** — 利润计算子查询复杂，效率低下 | 计算延迟、超时 | 中 | 🟡 中 |
| 🥉 | **场次会议并发** — 自动结束旧场次，多人同时创建可能丢失正在进行的售卖 | 销售数据丢失 | 低 | 🟡 中 |

---

## 6. 测试策略建议

### 6.1 自动化测试优先级

```
P0 (极高 — 核心利润相关，须立即覆盖)
├── 单元测试
│   ├── EAN-13 校验位计算 ✅（纯函数，易测）
│   ├── 条码生成去重逻辑
│   ├── getStoreId() 多角色逻辑
│   └── sanitizeSeriesDir() 路径清理
│
├── 集成测试（操作数据库）
│   ├── 商品 CRUD 全流程
│   ├── 直播售卖 + 并发扣减（模拟并行请求）
│   ├── 出库 → 库存扣减 → 回滚
│   └── 多租户数据隔离验证

P1 (高 — 业务完整性)
├── 批量导入商品（CSV/Excel 各种格式）
├── 库存盘点/手动调整
├── 财务管理计算
├── 库存导出
└── 场次创建切换

P2 (中 — 健壮性)
├── 超长输入、特殊字符
├── 空数据、零值边界
├── 文件上传边界
├── 用户权限管理
└── 各类查询分页/排序

P3 (低 — 可暂时手动测试)
├── 前端 UI 渲染（商品卡片样式等）
├── 打印标签格式
├── 批量导出的格式美化
└── 系统设置页面
```

### 6.2 测试技术栈建议

```
测试框架:  PHPUnit（PHP 项目标配，无依赖）
数据库:    SQLite 内存数据库 或 独立 MySQL 测试库
Mocking:   PHPUnit 原生 Mock
HTTP 测试:  GuzzleHttp + 内置 PHP 服务器 (php -S) 或 Postman/Swagger
CI/CD:     推荐 GitHub Actions 或 Gitea Actions（如果自托管）
覆盖率:    phpdbg 或 Xdebug 的 PHPUnit Coverage
```

**示例项目结构建议:**
```
ppmart/
├── tests/
│   ├── Unit/
│   │   ├── Ean13Test.php
│   │   ├── BarcodeGeneratorTest.php
│   │   ├── StoreIdTest.php
│   │   └── SanitizeTest.php
│   ├── Integration/
│   │   ├── ProductCrudTest.php
│   │   ├── LiveSellTest.php
│   │   ├── ConcurrentDeductionTest.php
│   │   ├── OutboundTest.php
│   │   └── MultiTenantTest.php
│   ├── Security/
│   │   ├── AuthTest.php
│   │   ├── CsrfTest.php
│   │   └── SqlInjectionTest.php
│   ├── bootstrap.php        # 测试环境初始化
│   └── phpunit.xml
├── composer.json
└── vendor/ (PHPUnit)
```

### 6.3 数据库约束建议（防御性编程）

需要添加到数据库的约束（比纯代码校验更可靠）：

```sql
ALTER TABLE inventory_batches
  ADD CONSTRAINT chk_remaining_qty_non_negative
  CHECK (remaining_qty >= 0);

ALTER TABLE sales_log
  ADD CONSTRAINT chk_returned_qty_valid
  CHECK (returned_qty >= 0 AND returned_qty <= qty);

ALTER TABLE outbound_log
  ADD CONSTRAINT chk_outbound_qty_positive
  CHECK (qty > 0);

ALTER TABLE live_inventory
  ADD CONSTRAINT chk_live_stock_non_negative
  CHECK (current_stock >= 0);
```

> MySQL 8.0.16+ 支持 `CHECK` 约束。如果生产环境版本不支持，需要在应用层强制校验。

### 6.4 手动测试建议

以下场景建议人工测试而非自动化：

| 场景 | 理由 |
|------|------|
| 直播售卖 UI 交互流程 | 涉及实时扫码、键盘快捷键、倒计时等复杂交互 |
| 标签打印格式 | 热敏打印机输出格式，需人工检查对齐和可读性 |
| 图片上传预览 | 图片裁剪、压缩质量、加载速度 |
| 多设备适配 | 后台管理在 PC / Pad / 手机上的布局 |
| 批量导入大文件 (>10MB) | 服务器超时 / 内存上限 |

---

## 7. 具体代码风险点

### 7.1 `delete_outbound.php` — 库存恢复越权
```php
// 问题：恢复库存没有校验 store_id
$updateStmt = $pdo->prepare("
    UPDATE inventory_batches
    SET remaining_qty = remaining_qty + ?
    WHERE id = ?
");
$updateStmt->execute([$outbound['qty'], $outbound['batch_id']]);
```
**修复建议**: 加上 `AND store_id = ?` 条件。

### 7.2 `outbound_batch.php` — 冗余 UPDATE 语句
```php
// 高版本走 store_id 过滤，低版本不走，两段代码共存了一部分
if ($storeId) {
    $stmt = $pdo->prepare('UPDATE inventory_batches SET remaining_qty = ? WHERE id = ? AND store_id = ?');
    $stmt->execute([$newRemaining, $batchId, $storeId]);
} else {
    $stmt = $pdo->prepare('UPDATE inventory_batches SET remaining_qty = ? WHERE id = ?');
    $stmt->execute([$newRemaining, $batchId]);
}
```
**问题**: 超管视角（`store_id === null`）时绕过店铺过滤 → 应该是故意的，但需要确认是否有业务需要这么做。

### 7.3 `live_inventory` 无负库存约束
```php
// sell_product_live.php — 纯应用层检查
if ($liveInv['current_stock'] < $qty) {
    throw new Exception('直播库存不足');
}
```
**问题**: 如果 `$qty` 是负数，条件永远不成立。虽然 `$qty = $input['qty'] ?? 1` 看起来只能为正，但如果 API 被直接调用传负数，可"刷库存"。

### 7.4 `list_products.php` — ORDER BY 动态拼接
```php
$sql .= ' ORDER BY (SELECT MAX(ib.purchased_at) FROM inventory_batches ib WHERE ib.product_id = p.id) DESC, p.id DESC';
```
这里拼接的是固定 SQL 片段，不是用户输入，所以实际上安全。但注释说是为了排序——如果后续加上排序参数需要严格白名单。

### 7.5 财务报表 — 复杂子查询性能
```php
SUM((s.sale_price - COALESCE((SELECT MIN(ib.purchase_price) ...
```
**问题**: 每行销售记录都会触发子查询，返回 500 条销售记录时可能有 500 次子查询，直播高峰期间可能导致数据库 CPU 飙升。

---

## 8. 行动清单（优先级排序）

### 🔴 本周内完成
1. **添加数据库 CHECK 约束** — 防止 `remaining_qty` < 0
2. **修复 `delete_outbound.php` 越权问题** — 库存恢复加 `store_id` 过滤
3. **增加登录限流 / 验证码** — 防止暴力破解
4. **添加 CSRF Token 机制** — 至少对 POST/DELETE 请求
5. **验证 sell_product_live.php 中 `$qty` 是否为负数** — 增加 `$qty > 0` 断言
6. **撰写第一个测试用例**（单元测试：EAN-13 校验位计算）

### 🟡 本月内完成
7. **搭建 PHPUnit + 测试数据库**
8. **覆盖核心 CRUD 集成测试**
9. **高并发压测直播售卖 API**
10. **财务报表子查询优化（加索引或缓存）**
11. **添加 `session_regenerate_id()` 登录后重置 SESSION ID**
12. **建立 CI/CD 流水线（GitHub Actions）**

### 🟢 下季度
13. **前端 XSS audit** — 全面检查未转义的输出
14. **端到端测试** — 完整业务流：入库→直播→售出→出库→财务核算
15. **性能基准测试** — 建立性能基线
16. **渗透测试** — 第三方安全审计

---

## 总结

PPMart 是一个**功能完整但运维原始**的生产系统。

**做得好的** 👍：
- 使用 PDO Prepared Statements（防 SQL 注入）
- 密码使用 `password_hash` 存储
- 关键路径使用了事务 + `FOR UPDATE`
- 多租户 model 设计合理（`getStoreId()`）
- 图片上传有 MIME 和大小校验

**最大风险** 🔴：
1. **零测试覆盖** — 任何修改都是盲改
2. **无数据库约束** — 纯靠代码逻辑保护数据完整性
3. **CSRF 完全缺失** — 所有 API 可被跨站利用
4. **一处越权漏洞** — `delete_outbound.php` 库存恢复
5. **登录无防护** — 无验证码、无限流

**建议立即投入**: 本周内修复以上 5 个最大风险，然后搭建测试框架逐步覆盖核心流程。

---

*报告生成: Testy 🧪 于 2026-06-03*
