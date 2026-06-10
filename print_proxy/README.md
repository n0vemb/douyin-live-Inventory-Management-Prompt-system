# PPMart Windows 打印代理

极简的本地 HTTP 服务，接收标签 PNG 图片并发送到 Windows 打印机。

## 工作原理

```
PPMart PHP 后端 (macOS/Linux)
  └─ direct_print.php 生成标签 PNG
      └─ POST base64 编码的 PNG → Windows 打印代理 (:9188)
          └─ PowerShell + .NET GDI → 本地打印机
```

## 使用方式

### 1. 编译（在 macOS/Linux 上）

```bash
cd print_proxy/
./build.sh
```

或者不要 `build.sh` 手动编译：

```bash
cd print_proxy/
GOOS=windows GOARCH=amd64 go build -ldflags="-s -w" -o ppmart-print-proxy.exe .
# 可选压缩：upx --best ppmart-print-proxy.exe
```

> **需要安装 Go**：https://go.dev/dl/ 或 `brew install go`

### 2. 部署到 Windows

把 `ppmart-print-proxy.exe` 放到 Windows 机器上，双击运行即可。

默认监听 `:9188`，可通过环境变量修改端口：

```cmd
set PORT=8080
ppmart-print-proxy.exe
```

或者设为开机启动（可选）：
1. 按 `Win + R`，输入 `shell:startup`
2. 将 `ppmart-print-proxy.exe` 的快捷方式放入启动文件夹

### 3. 配置 PPMart 后端

在 PPMart 的后端服务器上设置环境变量（或修改 `config.php`）：

```bash
export PPMART_PRINT_PROXY=http://192.168.x.x:9188   # 替换为 Windows 机器的实际 IP
```

或在 `config.php` 中直接修改：

```php
define('WINDOWS_PRINT_PROXY_URL', 'http://192.168.x.x:9188');
```

设置后，`direct_print.php` 会自动将标签 PNG 发送到该代理，由 Windows 完成打印。

### 4. 测试

```bash
# 检查代理是否在线
curl http://192.168.x.x:9188/ping

# 获取 Windows 打印机列表
curl http://192.168.x.x:9188/printers
```

## API 接口

### `POST /print`

接收并打印标签。

请求体：

```json
{
    "images": ["<base64 编码的 PNG 图片>", "..."],
    "printer": "Brother QL-820NWB",
    "pageWidth": 60,
    "pageHeight": 40
}
```

- `images`：必填，base64 PNG 数组，每个元素是一张标签图片的 base64 编码
- `printer`：可选，指定打印机名称（留空使用系统默认打印机）
- `pageWidth` / `pageHeight`：可选，标签尺寸（mm），用于设置打印纸张大小

### `GET /ping`

健康检查。返回 `{"success": true, "message": "pong"}`。

### `GET /printers`

列出系统中所有可用的打印机名称。

## Windows 防火墙

首次运行时 Windows 可能弹出防火墙提示，点击「允许访问」即可。

如果连接不上，检查 Windows 防火墙是否阻止了 `:9188` 端口：

```cmd
netsh advfirewall firewall add rule name="PPMart Print Proxy" dir=in action=allow protocol=TCP localport=9188
```
