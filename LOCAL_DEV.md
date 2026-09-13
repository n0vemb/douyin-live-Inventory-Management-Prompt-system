# 本地开发环境（beeke 机器）

## 仓库信息
- 远程：git@github.com:n0vemb/douyin-live-Inventory-Management-Prompt-system.git
- 本地分支：feature/full_platform（与测试服/生产服一致）
- 本地路径：~/智播中枢/

## 部署工作流（2026-09 更新）
1. 本地改代码（本地目录：`~/智播中枢/`，即本仓库）
2. 直接上测试站（pp.19lab.top），**不走 git**
3. 冒烟测试（服务器 `php -l` + 页面能打开不报 500）
4. 人工测试
5. 等店主通知
6. 收到通知后，才 git commit + push，并同步生产站

> 未收到通知前：只动测试站，**不推 git、不动生产站**。
> 老流程（git push → 测试站 pull → 生产站 pull）已不再使用。

## 环境
- 生产：store.19lab.top → /www/wwwroot/store.19lab.top（库 ppmart2）
- 测试：pp.19lab.top → **/www/wwwroot/pp.lab19.top**（库 ppmart2_test，php-cgi-74-pp.sock）
  - 注意：站点**目录名是 `pp.lab19.top`**（lab19），但 nginx 配置文件名是 `pp.n0vem.top.conf`，别按配置文件名找目录。
- 同一台服务器 38.90.15.5

## SSH
- GitHub：~/.ssh/id_ed25519（公钥已加到 GitHub n0vemb 账户），直连正常，无需代理
- 服务器：`ssh -p 11519 root@38.90.15.5`，密钥 `~/.ssh/id_rsa`
  - 22 端口不通，**必须带 `-p 11519`**；其它密钥（id_ed25519 / developer_key / eon）会 Permission denied。

## 发布单个文件（保留属主/权限，可回滚）
```bash
# 1) 服务器上先备份
ssh -p 11519 -i ~/.ssh/id_rsa root@38.90.15.5 \
  'cd /www/wwwroot/pp.lab19.top && cp -p admin/xxx.php admin/xxx.php.bak_$(date +%Y%m%d_%H%M%S)'

# 2) 用 cat > 覆盖内容（不要用 scp 新建，会变成 root 所有）
ssh -p 11519 -i ~/.ssh/id_rsa root@38.90.15.5 \
  'cat > /www/wwwroot/pp.lab19.top/admin/xxx.php' < admin/xxx.php

# 3) 校验 md5 与本地一致
ssh -p 11519 -i ~/.ssh/id_rsa root@38.90.15.5 \
  'md5sum /www/wwwroot/pp.lab19.top/admin/xxx.php'
md5 -q admin/xxx.php
```
