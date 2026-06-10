#!/usr/bin/env bash
set -euo pipefail

# PPMart Windows 打印代理 交叉编译脚本
# 在 macOS/Linux 上交叉编译出 Windows 可执行文件
#
# 前置条件：安装 Go
#   brew install go        # macOS
#   sudo apt install golang # Linux

NAME="ppmart-print-proxy"
VERSION=$(git describe --tags --always 2>/dev/null || echo "dev")

echo "==> 交叉编译 Windows amd64..."
GOOS=windows GOARCH=amd64 \
  go build -ldflags="-s -w -X main.version=${VERSION}" \
  -o "${NAME}.exe" .

echo "==> 压缩 (UPX)..."
if command -v upx &>/dev/null; then
  upx --best "${NAME}.exe" 2>/dev/null || upx -1 "${NAME}.exe"
  echo "     UPX 压缩完成"
else
  echo "     未安装 upx，跳过压缩（可选：brew install upx）"
fi

echo ""
echo "==== 构建完成 ===="
ls -lh "${NAME}.exe"
