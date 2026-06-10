<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$_SESSION = [];
session_destroy();
setcookie(session_name(), '', time() - 3600, '/');

header('Content-Type: application/json');
echo json_encode(['success' => true, 'message' => '已退出登录']);
