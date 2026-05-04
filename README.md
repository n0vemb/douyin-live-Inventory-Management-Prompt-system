# 直播进销存系统 - 项目文档

## 项目概述

这是一个泡泡玛特盲盒商品的进销存管理系统，可适用于所有直播项目的进销存及直播提示系统，支持直播辅助功能。截止26年5月2日，已可以实际用于生产中，由于是自用项目，没有增加用户管理及权限功能，如有需要可自行完善。再次吐槽一下傻逼ai做的ui真是丑爆了，蓝紫色的配色真是丑爆了。

特别说明： 创建直播时自动复制现有库存加入直播可用库存。在直播中产生的销售仅影响直播库存，不影响实际库存，在商品页面出库后才会影响真实库存。

<br />

**to do list:**

**1、标签打印功能，入库自动打印条码标签。(✅已完成，原来是用手动添加后打印，后来完善了批量导入后，改成了手动批量打印)**

**2、批量入库，excel导入（  ✅已完成）**

**3、进销存进一步完善，入库、出库更详细。**

有什么想要的建议可以在议题里面发布。

<br />

Bug FIx：

- 修复退还功能：保留 sales\_log 记录，避免延迟退还时显示没有可退还数量的问题
- 修复改价功能：当新价格等于建议价格时，正确设置 live\_price 为 NULL
- 优化退还逻辑：通过 inventory\_log 追踪退还，不删除销售记录
- 修复配置状态：所有页面获取商品状态不再使用内置数据，而是采用读取数据库。
- 返送提示优化：直播页面中增加商品简介展示，用于主播念稿。

***

## 目录结构

```
/admin/                    # 管理后台页面
  ├── layout.php           # 管理后台布局
  ├── index.php            # 管理后台首页
  ├── products.php         # 商品管理页面
  ├── sessions.php         # 直播场次管理
  ├── outbound.php         # 商品出库
  ├── sales.php            # 销售记录
  └── settings.php         # 系统配置页面

/api/                      # 项目 API（管理后台与直播页共用）
  ├── get_settings.php     # 获取系统配置
  ├── save_settings.php    # 保存系统配置
  ├── list_products.php    # 商品列表
  ├── add_product.php      # 添加商品
  ├── update_product.php   # 更新商品
  ├── delete_product.php   # 删除商品
  ├── search_stock.php     # 搜索库存
  ├── list_sessions.php    # 直播场次列表
  ├── create_session.php   # 创建直播场次
  ├── delete_session.php   # 删除直播场次
  ├── purchase_batch.php   # 批量入库
  ├── outbound_batch.php   # 批量出库
  ├── sales_summary.php    # 销售汇总
  ├── change_price.php     # 修改价格
  ├── list_sales.php       # 销售记录
  ├── get_product.php      # 获取商品详情
  ├── adjust_inventory.php # 库存调整
  ├── purchase.php         # 价格调整（兼容接口）
  └── scan_product_live.php # 直播扫码

/live.php                 # 直播辅助页面

/config.php               # 数据库配置文件

/database_v2_batch_system.sql  # 数据库结构
```

***

## 功能模块

### 1. 商品管理 (products.php)

#### 添加商品

- 自动生成条码（69414486 + 5位随机数）
- 支持多状态库存录入（原盒未拆、拆盒无瑕、无盒无瑕、微瑕）
- 支持参考价设置

#### 商品列表

- 支持关键词搜索（名称、条码、系列）
- 状态筛选
- 入库时间筛选
- 分页显示

#### 库存操作

- **入库**：批量入库，记录采购价
- **出库**：批量出库
- **调整**：调整库存数量

### 2. 直播辅助 (live.php)

#### 扫码查询

- 扫描条码自动查询商品
- 显示所有状态的价格和库存

#### 价格管理

- 点击价格可直接修改直播价格
- 修改后实时更新显示

#### 语音播报

- 商品上架时自动语音播报
- 售出时语音提示
- 可开关语音功能

#### 快捷操作

- 键盘 1/2/3/4 对应状态快速售出
- Q/W/E/R 快捷键对应状态
- Tab 切换状态

### 3. 系统配置 (settings.php)

#### 基本设置

- 系统名称自定义
- Logo 配置（支持上传图片/SVG，或输入图片 URL）

#### 库存状态管理

- 添加/编辑/删除状态类型
- 自定义状态颜色
- 支持添加自定义状态

#### 直播页面布局配置

- 可配置元素：
  - 商品名称
  - 常用名称
  - 参考价
  - 商品图片
  - 价格列表
- 每个元素可配置：
  - 显示/隐藏
  - 位置（左、上）
  - 大小（宽度、高度）
  - 字号
  - 层级
  - 间距（价格列表）

#### 实时预览

- 在设置页调整配置时会实时保存到 localStorage
- 直播页面每 200ms 检查配置变化并实时应用

### 4. 直播场次 (sessions.php)

#### 创建场次

- 自动创建直播场次
- 入库时自动添加商品到当前场次

#### 销售汇总

- 实时显示当前场次销售数据
- 今日销量/销售额统计

***

## 数据库表结构

### products（商品表）

| 字段             | 类型            | 说明    |
| -------------- | ------------- | ----- |
| id             | int           | 主键    |
| barcode        | varchar(20)   | 条码    |
| name           | varchar(100)  | 商品名称  |
| common\_name   | varchar(50)   | 常用名   |
| series         | varchar(50)   | 系列    |
| image\_url     | varchar(255)  | 图片URL |
| qiandao\_price | decimal(10,2) | 千岛参考价 |
| created\_at    | datetime      | 创建时间  |

### inventory\_batches（库存批次表）

| 字段              | 类型            | 说明   |
| --------------- | ------------- | ---- |
| id              | int           | 主键   |
| product\_id     | int           | 商品ID |
| purchase\_price | decimal(10,2) | 采购价  |
| condition\_type | varchar(20)   | 状态类型 |
| total\_qty      | int           | 总数量  |
| remaining\_qty  | int           | 剩余数量 |
| purchased\_at   | datetime      | 采购时间 |

### live\_sessions（直播场次表）

| 字段          | 类型           | 说明   |
| ----------- | ------------ | ---- |
| id          | int          | 主键   |
| name        | varchar(100) | 场次名称 |
| started\_at | datetime     | 开始时间 |
| ended\_at   | datetime     | 结束时间 |
| status      | varchar(20)  | 状态   |

### live\_inventory（直播库存表）

| 字段                | 类型            | 说明   |
| ----------------- | ------------- | ---- |
| id                | int           | 主键   |
| live\_session\_id | int           | 场次ID |
| product\_id       | int           | 商品ID |
| condition\_type   | varchar(20)   | 状态类型 |
| initial\_stock    | int           | 初始库存 |
| current\_stock    | int           | 当前库存 |
| suggested\_price  | decimal(10,2) | 参考价  |
| live\_price       | decimal(10,2) | 直播价  |

### outbound\_log（出库记录表）

| 字段              | 类型            | 说明   |
| --------------- | ------------- | ---- |
| id              | int           | 主键   |
| batch\_id       | int           | 批次ID |
| qty             | int           | 数量   |
| outbound\_price | decimal(10,2) | 出库价  |
| outbound\_at    | datetime      | 出库时间 |

### inventory\_log（库存变动日志表）

| 字段                | 类型            | 说明   |
| ----------------- | ------------- | ---- |
| id                | int           | 主键   |
| product\_id       | int           | 商品ID |
| live\_session\_id | int           | 场次ID |
| condition\_type   | varchar(20)   | 状态类型 |
| action            | varchar(20)   | 动作类型 |
| qty               | int           | 数量   |
| price             | decimal(10,2) | 价格   |
| created\_at       | datetime      | 创建时间 |

### system\_settings（系统配置表）

| 字段             | 类型          | 说明        |
| -------------- | ----------- | --------- |
| id             | int         | 主键        |
| setting\_key   | varchar(50) | 配置键       |
| setting\_value | text        | 配置值(JSON) |
| updated\_at    | datetime    | 更新时间      |

***

## API 接口

### 获取系统配置

```
GET /api/get_settings.php
Response: { success: true, settings: { system_name, logo_path, condition_types, live_display } }
```

### 保存系统配置

```
POST /api/save_settings.php
Body: { settings: { system_name, condition_types, live_display } }
Response: { success: true }
```

### 直播扫码查询

```
POST /api/scan_product_live.php
Body: { barcode, live_session_id }
Response: { success: true, data: { id, name, common_name, series, inventory } }
```

### 修改直播价格

```
POST /api/change_price.php
Body: { product_id, condition_type, new_price, live_session_id }
Response: { success: true, data: { new_price } }
```

### 直播售出

```
POST /api/sell_product_live.php
Body: { product_id, condition_type, sale_price, live_session_id }
Response: { success: true }
```

***

## 配置文件

### config.php

```php
支持环境变量（推荐）：
- PPMART_DB_HOST
- PPMART_DB_USER
- PPMART_DB_PASS
- PPMART_DB_NAME
- PPMART_ENABLE_MAINTENANCE_API（可选，`1` 时允许执行维护接口）

未设置时会使用 config.php 中的默认值。

CONDITION_TYPES   // 状态类型映射
CONDITION_KEYS    // 快捷键映射
```

***

## 使用指南

### 1. 初始化

1. 导入数据库：`database_v2_batch_system.sql`
2. 配置数据库连接：`config.php`
3. 执行配置初始化SQL（添加默认配置）

### 2. 商品管理

1. 进入"商品管理"
2. 点击"添加商品"或"批量入库"
3. 录入商品信息和各状态库存
4. 可在列表搜索、筛选商品

### 3. 直播辅助

1. 进入"直播场次"，创建新场次
2. 点击"开始直播"进入直播辅助页面
3. 扫描商品条码查询
4. 点击价格修改
5. 点击状态按钮或按快捷键售出

### 4. 配置管理

1. 进入"系统配置"
2. 设置系统名称
3. 管理库存状态类型
4. 配置直播页面布局
5. 打开直播页面查看效果

***

## 更新日志

### v2.2

- 数据概览页大重构：移除低库存商品、最近出入库、当前直播面板
- 新增销售趋势图表（基于 Chart.js），支持按日/按周/按月切换
- 直播页布局配置增强：参考价颜色、状态字号/颜色、价格字号/颜色、价格偏移、库存偏移
- 直播页价格变动时状态卡片高亮改为黄色背景
- 出库页商品状态名称改为从数据库动态读取
- 标签编辑器弹窗改为全屏模式
- 新增销售趋势 API（api/sales_trend.php）
- 数据概览页添加缓存控制头

### v2.1

- 新增 Logo 配置：支持上传图片/SVG 或 URL，在管理后台头部显示
- 新增一键统一改价：在库存详情中按状态批量更新所有批次售价
- 优化弹窗样式：统一弹窗宽度，修复深色模式下取消按钮看不清的问题
- 优化系统配置页：系统名称与 Logo 设置合并为一行
- 修复系统名称未从数据库读取的问题
- 直播页左上角查询状态优化

### v2.0

- 新增系统配置页面
- 支持可视化配置直播页面布局
- 库存状态可自定义
- 实时预览配置效果

### v1.0

- 基础进销存功能
- 直播辅助功能
- 多状态库存管理

