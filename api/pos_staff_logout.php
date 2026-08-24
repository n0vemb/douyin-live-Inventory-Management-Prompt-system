<?php
/**
 * pos_staff_logout.php — 退出店员模式
 */
require_once __DIR__ . '/pos_auth.php';
unset($_SESSION['pos_staff']);
success(['staff' => false]);
