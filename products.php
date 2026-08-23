<?php
// 根目录 products.php 是历史遗留坏文件（require 不存在的 layout.php）
// 统一跳转到 admin/products.php 正确页面
header('Location: admin/products.php');
exit;
