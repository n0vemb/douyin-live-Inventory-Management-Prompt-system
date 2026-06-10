# PPMart 直播进销存系统 — 项目架构审查报告

> **审查人**：Arki 🏛️（AI 系统架构师）  
> **审查日期**：2026-06-03  
> **代码版本**：生产运行中（基于 SQL 备份 2026-06-03）  
> **代码量**：~80 个 PHP 文件 + 1 个 SQL + 1 个 Go 打印代理  
> **审查范围**：整体架构、安全性、数据库设计、可扩展性、可维护性、部署架构

---

## 目录

1. [执行摘要](#1-执行摘要)
2. [整体架构评估](#2-整体架构评估)
3. [安全性审查](#3-安全性审查)
4. [数据库设计审查](#4-数据库设计审查)
5. [可扩展性评估](#5-可扩展性评估)
6. [部署架构评估](#6-部署架构评估)
7. [可维护性评估](#7-可维护性评估)
8. [总结与路线图](#8-总结与路线图)

---

## 1. 执行摘要

| 维度 | 评分 | 说明 |
|------|------|------|
| 整体架构 | ⭐⭐⭐✩✩ | 简单扁平结构，满足当前需求，但缺乏框架、路由、自动加载 |
| 安全性 | ⭐⭐⭐⭐✩ | SQL 注入防护优秀（PDO 预编译），XSS 基本覆盖，但缺少 CSRF 防护 |
| 数据库设计 | ⭐⭐⭐✩✩ | 表结构合理但有缺失字段和冗余表，索引基本覆盖但缺乏复合索引 |
| 可扩展性 | ⭐⭐⭐✩✩ | 多租户设计基本可用，但边界不一致，新增功能需较多改动 |
| 部署架构 | ⭐⭐⭐✩✩ | 单机单库部署，存在单点故障风险 |
| 可维护性 | ⭐⭐⭐✩✩ | 代码风格尚可，但无框架/命名空间/自动加载，复用度一般 |

**总体评估**：这是一个**务实可用的中小型系统**，在有限的工程投入下实现了核心业务闭环。主要风险集中在：CSRF 防护缺失、多租户边界不完整、无事务回滚兜底机制（部分场景）、单点部署风险、以及代码复用度不足导致的长期维护成本。

---

## 2. 整体架构评估

### 2.1 目录结构与分层

```
ppmart/
├── admin/              # 管理后台视图层（HTML + 内联 PHP）
├── api/                # 接口层（Controller 角色）
├── root-level .php     # 混合视图/入口（login, register, live, outbound）
├── config.php          # 配置 + 全局工具函数
├── auth.php            # 认证中间件函数
├── print_proxy/        # Windows 标签打印代理（Go）
├── uploads/            # 用户上传文件
├── assets/             # 前端静态资源
└── images/             # 系统图片
```

**分层评价**：

- **没有真正的 MVC 分层**。`api/` 文件承担 Controller 职责，但直接内嵌 SQL 查询（无 Model 层）。`admin/` 文件是 View，但大量 PHP 逻辑内嵌在 HTML 中。
- **没有命名空间或自动加载**。所有文件的类/函数通过 `require_once` 手动引入，全局函数命名冲突风险随代码量增长。
- **没有路由器**。所有请求直接映射到 PHP 文件路径，无法做统一的请求前/后处理（日志、限流、CORS 等）。

### 2.2 架构模式分析

当前架构是典型的 **"Scripted PHP"（脚本式 PHP）**——每个 PHP 文件即一个入口点。虽有 `config.php` 作为配置中心、`auth.php` 作为认证中间件，但没有实现任何设计模式：

| 缺失的模式 | 影响 |
|-----------|------|
| 前端控制器（Front Controller） | 无法统一处理请求过滤、路由、中间件链 |
| 依赖注入（DI） | `getDB()` 是静态单例，难以替换/测试 |
| 仓储模式（Repository） | SQL 逻辑分散在各 API 文件中 |
| MVC 或分层架构 | 业务逻辑和展示逻辑高度耦合 |

**风险等级：中** — 当前系统运行正常，但随着功能增加，脚本式架构的维护成本会指数增长。

### 2.3 整体架构建议

```
短期（1-3月）：
├── 引入一个简单的 Composer 自动加载
├── 将 SQL 查询抽取为独立的 Repository 或 DAO 类
├── 在 admin/ 中剥离 PHP 业务逻辑到独立的 service 文件

中期（3-6月）：
├── 引入轻量路由（如 FastRoute / slim 框架）
├── 添加统一错误处理与日志中间件
├── 添加统一的 API 入口点（api/index.php）
```

---

## 3. 安全性审查

### 3.1 SQL 注入防护 ✅ 优秀

项目全面使用 PDO 预编译语句 (`prepare` + `execute`)，`PDO::ATTR_EMULATE_PREPARES => false` 确保了真实预编译。示例：

```php
// ✅ 安全的做法（项目中使用）
$stmt = $pdo->prepare('SELECT * FROM products WHERE barcode = ?');
$stmt->execute([$barcode]);
```

**但存在 1 处动态拼接**（`api/save_finance.php`）：

```php
// ⚠️ 动态 SQL 拼接（虽然参数已用 ? 占位，但列名动态拼接）
$stmt = $pdo->prepare('UPDATE outbound_log SET ' . implode(', ', $updateFields) . ' WHERE outbound_batch_no = ? AND store_id = ?');
```

**风险等级：低** — 拼接的字段名 `$updateFields` 来源于固定的 `$input` 键白名单，实际攻击面极小。

### 3.2 XSS 防护 ✅ 基本覆盖

主要输出点使用 `htmlspecialchars()` 进行转义：

```php
<?= htmlspecialchars($loginSystemName) ?>
<?= htmlspecialchars($product['name']) ?>
```

**但内联 JS 输出存在风险**：

```php
// ⚠️ 在 layout.php 中，$layoutSystemName 被用作 innerHTML
<h1 id="headerTitle"><?= htmlspecialchars($layoutSystemName) ?></h1>
```

但 `settings.php` 的"系统名称"配置中，用户输入经 `htmlspecialchars` 后再渲染，风险可控。

**风险等级：低**

### 3.3 CSRF 防护 ❌ 完全缺失

**严重问题**：整个系统没有任何 CSRF token 机制。所有 POST/PUT/DELETE 请求仅依赖 Session Cookie + `SameSite=Lax`。

攻击场景：
- 攻击者构造一个恶意页面，向 `https://ppmert.example.com/api/delete_product.php` 发送 POST
- 如果用户在浏览器中已登录 PPMart，该请求会携带 Cookie 自动通过认证
- 可能导致商品被批量删除

虽然有 `SameSite=Lax` 的部分保护（阻止了跨站 GET 请求 POST 的 Cookie 发送），但：
1. `SameSite=Lax` 在 POST 表单提交时仍会发送 Cookie
2. `SameSite` 依赖浏览器支持，旧版浏览器和部分工具忽略此属性
3. 登录页面（login.php）同样没有 CSRF token

**风险等级：高**

**建议修复**：
```php
// 1. 在 config.php 中添加 CSRF token 生成函数
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(string $token): bool {
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

// 2. 在所有页面表单中添加隐藏字段
// echo '<input type="hidden" name="csrf_token" value="' . generateCsrfToken() . '">'

// 3. 在 API 入口验证
// $input['csrf_token'] 与 $_SESSION['csrf_token'] 对比
```

### 3.4 认证与鉴权 ✅ 合理

| 机制 | 评价 |
|------|------|
| 密码存储 | ✅ `password_hash(PASSWORD_DEFAULT)` + `password_verify()` 使用 bcrypt |
| 会话管理 | ✅ `session_set_cookie_params(['httponly'=>true, 'samesite'=>'Lax'])`，但有改进空间 |
| 认证中间件 | ✅ `requireAuth()` + `requireSuperAdmin()` + `getStoreId()` 设计合理 |
| 会话超时 | ❌ 没有设置 `session.gc_maxlifetime` 或空闲超时机制 |
| 密码复杂度 | ❌ 注册时仅限制密码 ≥ 6 位，无强度要求 |

**风险等级：中**

建议添加：
```php
// 会话超时（config.php 中）
ini_set('session.gc_maxlifetime', 28800);  // 8小时
// 或者在每次请求时检查最后活动时间
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 28800)) {
    session_destroy();
    header('Location: /login.php');
    exit;
}
$_SESSION['last_activity'] = time();
```

### 3.5 文件上传安全 ✅ 基本安全

图片上传（`api/upload_image.php`）实现了：
- ✅ MIME 类型检测（finfo + 降级扩展名判断）
- ✅ 文件名重命名（`uniqid()` + 时间戳）
- ✅ 10MB 大小限制
- ✅ 保存在 `uploads/` 子目录

**但缺少**：`exif_imagetype()` 二次验证、图片内容安全扫描。

**风险等级：低**

### 3.6 HTTPS 与 HTTP Headers ❌ 缺失

代码中未设置安全相关的 HTTP Headers：
- 没有 `Strict-Transport-Security`
- 没有 `Content-Security-Policy`
- 没有 `X-Content-Type-Options: nosniff`
- 没有 `X-Frame-Options: DENY`

**风险等级：中** — 在 Nginx 级别可以配置，但代码层面未强制要求。

---

## 4. 数据库设计审查

### 4.1 表结构总览

| 表名 | 用途 | 外键 | 评价 |
|------|------|------|------|
| `products` | 商品主表 | 无 | ✅ 设计良好 |
| `inventory_batches` | 库存批次 | → `products(id)` | ✅ 设计良好 |
| `live_sessions` | 直播场次 | 无 | ✅ 基本合理 |
| `live_inventory` | 直播库存快照 | → `products`, `live_sessions` | ✅ 设计合理 |
| `sales_log` | 销售记录 | → `products`, `live_sessions` | ⚠️ 缺少 `store_id` |
| `purchase_log` | 采购记录 | 无 | ⚠️ 缺少 `store_id` |
| `outbound_log` | 出库记录 | → `inventory_batches(id)` | ⚠️ 缺少 `store_id` |
| `inventory_log` | 库存变动日志 | 无 | ⚠️ 缺少 `store_id` |
| `inventory_backup` | **疑似废弃** | 无 | ❌ 冗余表，与 `inventory_batches` 功能重叠 |
| `system_settings` | 系统配置 | 无 | ⚠️ `unique key` 无 `store_id` 维度 |
| `broadcast_messages` | 广播消息 | 无 | ⚠️ 缺少 `store_id` |
| `label_templates` | 标签模板 | 无 | ⚠️ 缺少 `store_id` |
| `users`, `stores` | 用户/店铺 | → `stores(id)` | ✅ 设计合理 |

### 4.2 关键问题

#### ❌ 问题 1：多张表缺少 `store_id`（多租户不完整）

| 表名 | 是否缺少 `store_id` | 影响 |
|------|---------------------|------|
| `sales_log` | ❌ 缺少 | 超管跨店铺查询销售数据需要联表 |
| `purchase_log` | ❌ 缺少 | 同上 |
| `outbound_log` | 有 `store_id` ✅ | 但需确认迁移脚本是否包含 |
| `inventory_log` | ❌ 缺少 | 库存变动无法按店铺隔离 |
| `broadcast_messages` | ❌ 缺少 | 广播消息无法按店铺隔离 |
| `system_settings` | 字段存在 ✅ | 但索引未覆盖 `store_id` 查询 |
| `label_templates` | ❌ 缺少 | 标签模板无法按店铺隔离 |

**风险等级：高** — 多租户隔离的关键表中缺少店铺维度，可能导致数据交叉。

#### ❌ 问题 2：`inventory_backup` 废弃表未清理

```sql
CREATE TABLE `inventory_backup` (...) 
ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COMMENT='库存状态表';
```

此表结构与功能与 `inventory_batches` 重叠，且最近的数据是 2026-04-30（旧数据）。代码中未引用此表，属于遗留表。

**风险等级：低**

#### ❌ 问题 3：缺少审计字段

```sql
-- inventory_batches 缺少 deleted_by, deleted_at（软删除）
-- outbound_log 缺少 created_by
-- purchase_log 缺少 store_id, created_by
```

**风险等级：中**

#### ⚠️ 问题 4：索引覆盖分析

| 查询模式 | 现有索引 | 是否充分 |
|----------|---------|---------|
| 按 product_id + condition_type 查库存 | `idx_product_condition` | ✅ |
| 按 barcode 查商品 | `idx_barcode` (UNIQUE) | ✅ |
| 按 name/series 筛选 | `idx_name`, `idx_series` | ⚠️ 缺少复合索引 `(store_id, series)` |
| 按 session 查直播库存 | `idx_session` | ⚠️ 缺少 `(session_id, store_id)` |
| 按 outbound_batch_no 查 | `idx_outbound_batch_no` | ✅ |
| 按 sold_at 查销售 | `idx_sold_at` | ⚠️ 缺少 `(store_id, sold_at)` |

**风险等级：低** — 当前数据量级（~200 商品、~300 批次）下不影响性能。随业务增长需补充复合索引。

### 4.3 数据库设计改进建议

```sql
-- 1. inventory_batches 添加审计字段
ALTER TABLE inventory_batches
  ADD COLUMN `deleted_at` timestamp NULL DEFAULT NULL COMMENT '删除时间',
  ADD COLUMN `deleted_by` int(10) unsigned DEFAULT NULL COMMENT '删除人';

-- 2. 补充多租户缺失字段
ALTER TABLE sales_log ADD COLUMN `store_id` int(10) unsigned NOT NULL AFTER `id`;
ALTER TABLE purchase_log ADD COLUMN `store_id` int(10) unsigned NOT NULL AFTER `id`;
ALTER TABLE inventory_log ADD COLUMN `store_id` int(10) unsigned NOT NULL AFTER `id`;
ALTER TABLE broadcast_messages ADD COLUMN `store_id` int(10) unsigned NOT NULL AFTER `id`;
ALTER TABLE label_templates ADD COLUMN `store_id` int(10) unsigned DEFAULT NULL AFTER `id`;

-- 3. system_settings 索引优化（当前 unique key 不含 store_id）
-- 设计缺陷：store_id IS NULL 与 store_id = X 会冲突
-- 建议改成唯一键含 store_id 或拆分表

-- 4. 清理废弃表（确认无代码引用后）
-- DROP TABLE IF EXISTS inventory_backup;
```

### 4.4 EAN-13 条码生成分析

`config.php` 中的条码生成逻辑存在轻度设计问题：

```php
// 前缀硬编码为 69414486，但每家店铺应当有自己的前缀
$prefix = $_SESSION['barcode_prefix'] ?? '69414486';
```

好在代码实际使用 `$_SESSION['barcode_prefix']`（登录时从 stores 表读取），且 `generateStoreBarcodePrefix()` 确保了前缀的唯一性。✅ 实际运行良好。

---

## 5. 可扩展性评估

### 5.1 多租户设计

**当前模式**：通过 `store_id` 列进行数据隔离 + 超级管理员可切换店铺视图。

**设计评价**：

```
                    ┌──────────────────────┐
                    │  Super Admin (超管)   │
                    │  可查看全平台数据      │
                    └──────────┬───────────┘
                               │
          ┌────────────────────┼────────────────────┐
          ▼                    ▼                    ▼
    ┌──────────┐        ┌──────────┐        ┌──────────┐
    │ Store A  │        │ Store B  │        │ Store C  │
    │ store_id=1│       │ store_id=2│       │ store_id=3│
    └──────────┘        └──────────┘        └──────────┘
```

**优点**：
- `getStoreId()` 统一了店铺数据筛选逻辑
- 超管通过 `view_store_id` 切换查看范围
- 普通用户自动绑定到自己的 `store_id`

**缺点**：
- 部分表缺少 `store_id` 字段（见 4.2）
- 没有租户级别的资源隔离（上传文件、PHP 配置等）
- 没有租户配额管理

**新增店铺的成本**：只需通过 `register.php` 注册即可，基本能自动完成。**合理**。

### 5.2 新增功能的难易度

| 新增需求 | 预计工作量 | 障碍 |
|---------|-----------|------|
| 新增一个管理页面 | 1-2 天 | 需要复制 `layout.php` 模板，手动处理 SQL |
| 新增一个 API 接口 | 0.5 天 | 简单的脚本文件 |
| 新增数据库表 | 1 天 | 无 Migration 工具，需手动写 SQL |
| 集成第三方支付/物流 | 3-5 天 | 需要在 API 中嵌入 HTTP 调用 |
| 切换到 RESTful API 架构 | 2-4 周 | 需要重写前后端通信模式 |
| 增加多语言支持 | 1-2 周 | 当前无 i18n 基础设施 |

### 5.3 性能可扩展性

当前架构在以下方面存在潜在瓶颈：

1. **无缓存层** — 每次请求直接查数据库，无 Redis/Memcached
2. **全量数据查询** — `api/list_products.php` 查询所有商品 + 所有批次然后 PHP 聚合，无分页的 IN 查询
3. **无队列系统** — 打印、批量导入等操作同步执行
4. **无读写分离** — 所有请求打在单一数据库

**按预估数据量评估**：

| 数据规模 | 当前 | 可支撑上限 | 须采取措施 |
|---------|------|-----------|-----------|
| 商品数 | ~200 | ~10,000 | 加查询分页 |
| 库存批次 | ~300 | ~50,000 | 加查询分页 |
| 销售记录 | ~100 | ~100,000 | 加复合索引 + 归档 |
| 日活用户 | ~5 | ~50 | 足够 |
| 并发连接 | 单进程 | ~30（PHP 内置） | 切换到 Nginx + PHP-FPM |

---

## 6. 部署架构评估

### 6.1 当前部署模式

```
                       ┌──────────────┐
                       │   Internet    │
                       └──────┬───────┘
                              │ HTTPS
                       ┌──────▼───────┐
                       │   Nginx      │
                       │ PHP-FPM      │
                       │              │
                       │ ppmart/      │
                       └──────┬───────┘
                              │
                       ┌──────▼───────┐
                       │   MySQL 5.7  │
                       │   (单实例)   │
                       └──────────────┘
```

### 6.2 单点故障分析

| 组件 | 单点故障风险 | 影响 | 缓解措施 |
|------|------------|------|---------|
| MySQL 数据库 | ⚠️ 是 | 系统完全不可用 | 主从复制 / RDS 托管 |
| Nginx + PHP | ⚠️ 是 | 系统完全不可用 | 多实例 + 负载均衡 |
| 存储（uploads/） | ⚠️ 是 | 图片丢失 | 对象存储（OSS/S3） |
| 标签打印代理 | ⚠️ 是 | 打印不可用 | 独立部署 + 健康检查 |

**风险等级：中** — 对于小型生产系统可以接受。建议至少做数据库主从备份。

### 6.3 数据库恢复策略

SQL 备份文件 (`ppmart.sql`) 包含完整结构和所有数据，但：
- ❌ 不包含事务隔离级别设置
- ❌ 不包含存储过程/触发器等
- ⚠️ 备份文件 2014 行，包含 INSERT 语句，批量恢复时可能存在外键约束问题

### 6.4 部署最佳实践建议

```
生产环境推荐架构：

                     ┌──────────────────────────────┐
                     │   CDN (静态资源缓存)           │
                     └──────────────┬───────────────┘
                                    │
┌─────────────┐    ┌────────────────▼──────────────┐
│ 阿里云OSS   │◄───│   Nginx 反向代理 + SSL 终止     │
│ (图片存储)   │    └──────────────┬────────────────┘
└─────────────┘           │
              ┌────────────┼────────────┐
              ▼            ▼            ▼
        ┌──────────┐ ┌──────────┐ ┌──────────┐
        │ PHP-FPM 1│ │ PHP-FPM 2│ │ PHP-FPM 3│
        └──────────┘ └──────────┘ └──────────┘
              │            │            │
              └────────────┼────────────┘
                           ▼
                    ┌──────────────┐
                    │  Redis       │
                    │ (Session/    │
                    │  缓存/队列)   │
                    └──────┬───────┘
                           │
                    ┌──────▼───────┐
                    │  MySQL 主库   │
                    └──────┬───────┘
                           │
                    ┌──────▼───────┐
                    │ MySQL 从库    │
                    │ (查询/备份)   │
                    └──────────────┘
```

---

## 7. 可维护性评估

### 7.1 代码耦合度

当前架构的依赖关系图：

```
login.php ──────► config.php (getDB, generateBarcode)
   │
   ├──► auth.php (requireAuth, getStoreId)
   │
   └──► system_settings (直接 SQL 查询)

api/*.php ──────► config.php ──► auth.php
   │                 │
   └── 直接 SQL      └── 全局函数

admin/*.php ────► layout.php ──► config.php ──► auth.php
```

**耦合度分析**：
- `config.php` 承担了数据库连接、条码生成、文件操作、条件类型定义等多重职责（**神类模式**）
- 所有文件直接依赖 `config.php` 和 `auth.php`，无法简单地替换实现
- `api/*.php` 将 HTTP 输入解析、认证、业务逻辑、SQL 查询、JSON 输出全部揉在一个文件中

### 7.2 代码复用率

| 复用模式 | 现状 | 评价 |
|---------|------|------|
| 重复的 SQL 查询 | `SELECT * FROM products WHERE barcode = ?` 在多个 API 中重复 | ❌ |
| 相似的列表渲染 | `admin/products.php`, `admin/outbound.php` 等页面各自实现列表逻辑 | ❌ |
| 状态配置读取 | 多处重复读取 `system_settings` + `stores.condition_types` | ❌ |
| 数据库连接 | 通过 `getDB()` 单例复用 | ✅ |

### 7.3 配置管理

**评价**：
- ✅ 数据库配置支持环境变量覆盖（`PPMART_*`），适合容器化部署
- ✅ 业务配置存储在 `system_settings` 表中，支持运行时修改
- ❌ 没有配置版本管理（无法追踪配置变更历史）
- ❌ 没有配置校验层（设置系统名称时可以保存空值）

### 7.4 错误处理

**评价**：
- ✅ `getDB()` 有 try-catch，连接失败时返回 JSON 错误
- ❌ 大部分 API 文件没有全局异常处理
- ❌ 没有日志系统（`error_log()` 写在很少的地方）
- ⚠️ 使用 `@` 错误抑制符（如 `@unlink`）—— 应替换为显式检查

### 7.5 测试性

**评价**：
- ❌ 没有任何单元测试或集成测试
- ❌ `getDB()` 静态单例使得测试时无法替换数据库连接
- ⚠️ `CLAUDE.md` 中的"提交前检查清单"是手动人工回归，无自动化测试
- ❌ 没有 Mock 或 Stub 机制

---

## 8. 总结与路线图

### 8.1 风险等级汇总

| 排序 | 问题 | 风险等级 | 影响范围 |
|------|------|---------|---------|
| 1 | **缺少 CSRF 防护** | 🔴 **高** | 全系统 POST/API |
| 2 | **多表缺少 store_id 字段** | 🔴 **高** | 多租户数据隔离 |
| 3 | **无日志系统** | 🟡 **中** | 运维排障 |
| 4 | **无会话超时机制** | 🟡 **中** | 认证安全 |
| 5 | **脚本式架构耦合度高** | 🟡 **中** | 长期维护 |
| 6 | 缺少安全 HTTP Headers | 🟡 中 | 安全防御深度 |
| 7 | 无缓存层 | 🟡 中 | 性能 |
| 8 | 单点部署 | 🟡 中 | 可用性 |
| 9 | `inventory_backup` 废弃表 | 🟢 低 | 数据库整洁 |
| 10 | 无单元测试 | 🟢 低 | 开发效率 |
| 11 | 一处动态 SQL 拼接 | 🟢 低 | SQL 注入 |
| 12 | 索引不充分 | 🟢 低 | 查询性能 |

### 8.2 分阶段改进建议

#### 🔴 第 1 优先级（安全"保底"）

```text
1. 添加 CSRF Token 机制（全系统）
   - 预计：1-2 天
   - 文件范围：config.php + auth.php + 所有 POST API
   
2. 添加会话超时
   - 预计：0.5 天
   - 文件范围：config.php
   
3. 补充缺失的 store_id 字段
   - 预计：2-3 天（含数据迁移与回填）
   - 影响表：sales_log, purchase_log, inventory_log, broadcast_messages, label_templates
```

#### 🟡 第 2 优先级（工程能力提升）

```text
4. 引入 Composer + PSR-4 自动加载
5. 抽取 Model 层（Repository 模式）
6. 添加统一错误处理与日志（Monolog 或 PHP error_log）
7. 补充分页查询（list_products, list_sales 等）
8. 删除废弃表 inventory_backup
```

#### 🟢 第 3 优先级（架构现代化）

```text
9. 引入轻量框架（Laravel/Lumen/Slim）或自建路由
10. 添加 Redis Session 存储（支持多实例）
11. 图片迁移到对象存储（OSS/S3）
12. 补充单元/集成测试
13. 数据库主从 + 读写分离
```

### 8.3 架构评分卡总结

```
┌────────────────────────────────────────────────────┐
│              PPMart 架构健康评分                     │
├────────────────────────────────────────────────────┤
│ 安全防护           ████████░░░░ 6.5/10              │
│ 数据库设计         ███████░░░░░ 6.0/10              │
│ 代码组织           █████░░░░░░░ 5.0/10              │
│ 可扩展性           ██████░░░░░░ 5.5/10              │
│ 可维护性           █████░░░░░░░ 5.0/10              │
│ 性能与部署         █████░░░░░░░ 5.0/10              │
├────────────────────────────────────────────────────┤
│ 综合评分           ██████░░░░░░ 5.5/10              │
└────────────────────────────────────────────────────┘
```

### 8.4 最后说明

> 虽然架构评分中等，需要说明的是：**这是一个正在实际产生价值的业务系统**，能够稳定运行本身就是最好的架构证明。本报告指出的问题并非"系统不能运行"，而是"如何让系统在更大规模、更长周期中持续可控"。建议优先修复高风险的 CSRF 和多租户数据隔离问题，其他改进可根据业务节奏分阶段进行。

---

*报告结束*
