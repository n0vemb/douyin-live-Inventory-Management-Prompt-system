# 本地开发环境（beeke 机器）

## 仓库信息
- 远程：git@github.com:n0vemb/douyin-live-Inventory-Management-Prompt-system.git
- 本地分支：feature/full_platform（与测试服/生产服一致）
- 本地路径：~/智播中枢/

## 部署工作流（2026-08 起）
1. 本地 `~/智播中枢/` 修改代码 → git commit → git push（SSH 直连，不走代理）
2. 测试服（pp.19lab.top / /www/wwwroot/pp.n0vem.top）git pull
3. 验证通过后，生产服（store.19lab.top / /www/wwwroot/store.19lab.top）git pull
4. 测试服后续会删除，生产服直接 pull 同步

## 环境
- 生产：store.19lab.top → /www/wwwroot/store.19lab.top（库 ppmart2）
- 测试：pp.19lab.top → /www/wwwroot/pp.n0vem.top（库 ppmart2_test，php-cgi-74-pp.sock）
- 同一台服务器 38.90.15.5

## SSH
- 本地密钥：~/.ssh/id_ed25519（公钥已加到 GitHub n0vemb 账户）
- 直连 GitHub 正常，无需代理
