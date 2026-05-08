# CLAUDE.md
本文件为 Claude Code 在本仓库中的工作约定与项目上下文。

## 项目概览
- 项目名称：直播进销存系统（PPMart）
- 技术栈：PHP + MySQL + 原生前端（HTML/CSS/JS）
- 主要目录：
  - `admin/`：管理后台页面
  - `api/`：后端接口
  - `live.php`：直播辅助页面
  - `config.php`：数据库与系统配置

## 开发约定
- 提交github始终通过(all_proxy=socks5://127.0.0.1:10808)代理进行推送。
- 对用户的所有说明、总结、问题回复默认使用中文（简体），除非用户明确要求英文或其他语言。
- 代码、命令、路径、接口字段名保持原文，不做中文翻译。
- 优先保持现有代码风格与命名习惯，不做无关重构。
- 变更应尽量小而聚焦，避免影响直播中的关键流程（扫码、改价、售出、库存同步）。
- 涉及库存、销售、退还逻辑时，优先保证数据一致性和可追溯性（`inventory_log` / `sales_log`）。
- 新增或修改 API 时，保持 `success/data/message` 返回结构一致。

## 关键业务规则（摘要）
- 创建直播场次时会复制现有库存到直播库存。
- 直播售出只影响直播库存，不直接扣减实际库存。
- 实际库存在“出库”环节才变化。
- 价格修改逻辑需兼容 `live_price` 与建议价（等于建议价时可置空）。

## 常见工作流程
1. 修改页面逻辑时，同步检查对应 `api/*.php` 接口。
2. 修改状态字段相关逻辑时，确认从数据库读取状态配置，不依赖硬编码。
3. 涉及标签打印功能时，注意当前实现基于浏览器打印（`window.print()` + `@page`），并非热敏协议直连。

## 数据与配置
- 数据库连接可用环境变量覆盖（见 `README.md` 与 `config.php`）：
  - `PPMART_DB_HOST`
  - `PPMART_DB_USER`
  - `PPMART_DB_PASS`
  - `PPMART_DB_NAME`
  - `PPMART_ENABLE_MAINTENANCE_API`

## 本地运行命令（固定建议）
1. 不启动本地 PHP 服务（项目根目录执行）：
   - `php -S 127.0.0.1:8000 -t /Users/bike/n0vem/ppmart`
2. 浏览器访问：
   - `http://127.0.0.1:8000/admin/`
   - `http://127.0.0.1:8000/live.php`
3. 不进行数据库初始化（首次）：
   - `mysql -u root -p < database_v2_batch_system.sql`

## 本地测试与校验命令（固定建议）
1. PHP 语法检查（全量）：
   - `find /Users/bike/n0vem/ppmart -name "*.php" -print0 | xargs -0 -n1 php -l`
2. 核心页面连通性检查：
   - `curl -I http://127.0.0.1:8000/admin/`
   - `curl -I http://127.0.0.1:8000/live.php`
3. 关键 API 冒烟检查（需服务已启动且数据库可用）：
   - `curl -s http://127.0.0.1:8000/api/get_settings.php`
4. 修改涉及库存/销售逻辑时，必须人工回归：
   - 直播扫码 → 改价 → 售出 → 退还 → 出库 全链路至少跑 1 次。

## 强制提交前检查清单（Mandatory Pre-Commit Checklist）
- 任何代码提交、合并请求、或交付前，必须先完成以下检查；未执行则视为不允许提交。
- 必跑项（每次提交都要执行）：
  1. `find /Users/bike/n0vem/ppmart -name "*.php" -print0 | xargs -0 -n1 php -l`
  2. `curl -I http://127.0.0.1:8000/admin/`
  3. `curl -I http://127.0.0.1:8000/live.php`
  4. `curl -s http://127.0.0.1:8000/api/get_settings.php`
- 涉及库存、销售、退还、出库、改价逻辑改动时，除必跑项外还必须完成人工回归：
  - 直播扫码 → 改价 → 售出 → 退还 → 出库 全链路至少 1 次。
- 任一检查失败：禁止提交；先修复问题，再重新完整执行清单。

## 提交前自检建议
- 检查改动是否破坏扫码、改价、售出、出库主流程。
- 检查接口参数名和前端调用是否一致。
- 检查 SQL/库存计算是否可能出现负库存或重复扣减。
