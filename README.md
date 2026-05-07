# 直播进销存系统 - 项目文档

## 项目概述

泡泡玛特盲盒商品的进销存管理系统，可适用于所有直播项目的进销存及直播提示系统，支持直播辅助功能。截止 2026 年 5 月，已可实际用于生产。

特别说明：创建直播时自动复制现有库存加入直播可用库存。在直播中产生的销售仅影响直播库存，不影响实际库存，在商品页面出库后才会影响真实库存。

<img width="3232" height="2160" alt="Frame 103" src="https://github.com/user-attachments/assets/e0f29cb6-6c85-4348-bfd7-da3f4a34669d" />

<br />

**to do list:**

**1、标签打印功能，入库自动打印条码标签。(✅已完成，原来是用手动添加后打印，后来完善了批量导入后，改成了手动批量打印，支持逐行独立打印)**

**2、批量入库，excel导入（ ✅已完成）**

**3、进销存进一步完善，入库、出库更详细。**

**4、图片上传按系列分目录存储，编辑/删除商品时自动清理旧图（ ✅已完成）**

**5、进价管理系统配置：商品录入时可设置进价，直播页面支持展示进价（ ✅已完成）**

有什么想要的建议可以在议题里面发布。

<br />

Bug Fix：

- 修复退还功能：保留 sales_log 记录，避免延迟退还时显示没有可退还数量的问题
- 修复改价功能：当新价格等于建议价格时，正确设置 live_price 为 NULL
- 优化退还逻辑：通过 inventory_log 追踪退还，不删除销售记录
- 修复配置状态：所有页面获取商品状态不再使用内置数据，而是采用读取数据库。
- 返送提示优化：直播页面中增加商品简介展示，用于主播念稿。
- 修复 Windows 下 Shift+数字小键盘快速售出/加库存不可用的问题（改用 e.code 匹配）
- 修复标签模板选择逻辑：持久化到 localStorage，刷新后自动恢复上次使用的模板
- 修复批量打印数据不一致：改为后端重新查询数据库最新数据再生成标签
- 商品列表改为按入库时间排序，编辑后不再跳转到最顶
- 编辑商品后保持当前系列筛选状态，不再重置到全部商品

***

## 目录结构

```
├── admin/                        # 管理后台页面
│   ├── layout.php                # 管理后台布局
│   ├── index.php                 # 管理后台首页
│   ├── products.php              # 商品管理页面（全选/批量删除/导入）
│   ├── sessions.php              # 直播场次管理
│   ├── outbound.php              # 商品出库
│   ├── sales.php                 # 销售记录
│   ├── settings.php              # 系统配置页面
│   └── assets/css/style.css      # 样式
│
├── api/                          # 后端接口
│   ├── get_settings.php          # 获取系统配置
│   ├── save_settings.php         # 保存系统配置
│   ├── list_products.php         # 商品列表（含进价/售价聚合）
│   ├── add_product.php           # 添加商品
│   ├── update_product.php        # 更新商品
│   ├── delete_product.php        # 删除商品（支持批量）
│   ├── bulk_import_products.php  # 批量导入（CSV/XLSX，含名称匹配）
│   ├── search_stock.php          # 搜索库存
│   ├── list_sessions.php         # 直播场次列表
│   ├── create_session.php        # 创建直播场次
│   ├── delete_session.php        # 删除直播场次
│   ├── purchase_batch.php        # 批量入库
│   ├── outbound_batch.php        # 批量出库
│   ├── sales_summary.php         # 销售汇总
│   ├── change_price.php          # 修改价格
│   ├── list_sales.php            # 销售记录
│   ├── get_product.php           # 获取商品详情
│   ├── adjust_inventory.php      # 库存调整
│   ├── purchase.php              # 价格调整（兼容接口）
│   └── scan_product_live.php     # 直播扫码
│
├── live.php                      # 直播辅助页面
├── config.php                    # 数据库与系统配置
└── logo.png                      # 系统 Logo
```

## 功能模块

### 1. 商品管理 (products.php)

#### 添加商品

- 自动生成带 EAN-13 校验位的条形码（`69414486` + 4 位随机数 + 校验位）
- 支持多状态库存录入（原盒未拆、拆盒无瑕、无盒无瑕、微瑕—支持自定义）
- 支持参考价设置

#### 商品列表

- 展示图片、条码、名称、系列、商品简介（替代参考价列）、库存状态、库存总量、进价、售价
- 支持关键词搜索（名称、条码、系列）
- 支持全选/勾选 + 批量删除
- 库存状态点击查看详情
- 按入库时间排序，编辑商品不改变顺序
- 筛选系列后编辑商品自动保持筛选状态

#### 批量导入

- 支持 CSV 和 XLSX 格式
- 下载导入模板
- **条码为空时自动处理**：按名称匹配已有商品（追加库存）或自动生成条形码新建
- 支持各状态库存数量、进价、售价批量录入

### 2. 直播辅助 (live.php)

- 扫描条码自动查询商品，显示所有状态价格、库存、进价
- 点击价格可直接修改直播价格，实时更新
- 商品上架/售出时自动语音播报（可开关）
- 键盘 1/2/3/4 对应状态快速售出，Shift+Num 加库存，Q/W/E/R 快捷键改价
- Tab 切换状态
- 键盘操作提示底部通栏显示
- 直播页面进价显示可在系统配置中控制开关与位置

### 3. 系统配置 (settings.php)

- 系统名称自定义
- Logo 配置（支持上传图片/SVG 或图片 URL）
- 库存状态管理：添加/编辑/删除状态类型，自定义颜色
- 直播页面布局配置：显示/隐藏元素、位置、大小、字号、层级、间距，支持进价显示
- 实时预览：配置变化实时保存并应用

### 4. 直播场次 (sessions.php)

- 创建场次时自动复制现有库存
- 入库时自动添加商品到当前场次
- 实时显示当前场次销售数据

## 数据库表结构

### products（商品表）

| 字段 | 类型 | 说明 |
|---|---|---|
| id | int | 主键 |
| barcode | varchar(20) | 条码（EAN-13） |
| name | varchar(100) | 商品名称 |
| common_name | varchar(50) | 常用名 |
| series | varchar(50) | 系列 |
| brand | varchar(100) | 品牌 |
| image_url | varchar(255) | 图片 URL |
| qiandao_price | decimal(10,2) | 参考价 |
| release_date | date | 发售时间 |
| product_description | text | 产品介绍 |
| remark | text | 备注 |
| created_at | datetime | 创建时间 |
| updated_at | datetime | 更新时间 |

### inventory_batches（库存批次表）

| 字段 | 类型 | 说明 |
|---|---|---|
| id | int | 主键 |
| product_id | int | 商品 ID |
| condition_type | varchar(20) | 状态类型 |
| batch_no | varchar(50) | 批次号 |
| purchase_price | decimal(10,2) | 进价 |
| suggested_price | decimal(10,2) | 建议售价 |
| total_qty | int | 总数量 |
| remaining_qty | int | 剩余数量 |
| supplier | varchar(100) | 供应商 |
| remark | text | 备注 |
| purchased_at | datetime | 采购时间 |
| created_at | datetime | 创建时间 |

### 更多表

- `live_sessions` — 直播场次
- `live_inventory` — 直播库存
- `sales_log` — 销售记录
- `purchase_log` — 入库记录
- `outbound_log` — 出库记录
- `inventory_log` — 库存变动日志
- `system_settings` — 系统配置

## API 接口

所有接口返回统一格式：`{ success: bool, data?: any, error?: string }`

### 商品

| 接口 | 方法 | 说明 |
|---|---|---|
| `/api/list_products.php` | GET | 商品列表，支持 keyword/series 筛选 |
| `/api/add_product.php` | POST | 添加商品（条码为空时自动生成 EAN-13） |
| `/api/update_product.php` | POST | 更新商品 |
| `/api/delete_product.php` | POST | 删除商品（product_id 或 product_ids 数组） |
| `/api/get_product.php` | POST | 获取商品详情 |
| `/api/bulk_import_products.php` | POST | 批量导入商品（multipart/form-data） |
| `/api/upload_image.php` | POST | 图片上传（按系列分目录存储，自动清理旧图） |
| `/api/search_stock.php` | GET | 搜索库存 |

### 库存

| 接口 | 方法 | 说明 |
|---|---|---|
| `/api/purchase_batch.php` | POST | 批量入库 |
| `/api/outbound_batch.php` | POST | 批量出库 |
| `/api/adjust_inventory.php` | POST | 库存调整 |
| `/api/direct_print.php` | POST | 标签直打（支持按批次查询最新数据，Windows 打印代理） |

### 直播

| 接口 | 方法 | 说明 |
|---|---|---|
| `/api/list_sessions.php` | GET | 直播场次列表 |
| `/api/create_session.php` | POST | 创建直播场次 |
| `/api/delete_session.php` | POST | 删除直播场次 |
| `/api/scan_product_live.php` | GET | 直播扫码查询 |
| `/api/change_price.php` | POST | 修改直播价格 |

### 销售

| 接口 | 方法 | 说明 |
|---|---|---|
| `/api/list_sales.php` | GET | 销售记录 |
| `/api/sales_summary.php` | GET | 销售汇总 |

### 配置

| 接口 | 方法 | 说明 |
|---|---|---|
| `/api/get_settings.php` | GET | 获取系统配置 |
| `/api/save_settings.php` | POST | 保存系统配置 |

## 本地开发

### 环境要求

- PHP 7.4+
- MySQL 5.7+
- ZipArchive 扩展（XLSX 导入需要）

### 快速启动

```bash
# 1. 初始化数据库
mysql -u root -p < database_v2_batch_system.sql

# 2. 启动 PHP 内置服务
php -S 127.0.0.1:8000 -t /path/to/ppmart

# 3. 浏览器访问
open http://127.0.0.1:8000/admin/
open http://127.0.0.1:8000/live.php
```

### 环境变量

- `PPMART_DB_HOST` — 数据库主机，默认 `172.18.0.2`
- `PPMART_DB_USER` — 数据库用户，默认 `ppmart`
- `PPMART_DB_PASS` — 数据库密码
- `PPMART_DB_NAME` — 数据库名，默认 `ppmart`
- `PPMART_ENABLE_MAINTENANCE_API` — 是否启用维护接口

## 关键业务规则

- 创建直播场次时会复制现有库存到直播库存
- 直播售出只影响直播库存，不直接扣减实际库存
- 实际库存在「出库」环节才变化
- 价格修改需兼容 `live_price` 与建议价（等于建议价时可置空）
- 批量导入时条码为空 → 按名称匹配已有商品或自动生成 EAN-13 条码
- 商品删除会级联删除相关库存、销售、日志记录（事务保证）
