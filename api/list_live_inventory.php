<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$pdo = getDB();
requireAuth(); $storeId = getStoreId();

// 获取当前活跃直播场次
$stmt = $pdo->prepare("SELECT * FROM live_sessions WHERE status = 'active'" . ($storeId ? " AND store_id = ?" : "") . " ORDER BY started_at DESC LIMIT 1");
$stmt->execute($storeId ? [$storeId] : []);
$session = $stmt->fetch();

if (!$session) {
    success(['data' => ['session' => null, 'products' => []]]);
    exit;
}

$liveSessionId = $session['id'];

// 读取状态类型名称映射
$conditionMap = [
    'sealed' => '原盒未拆',
    'opened' => '拆盒无瑕',
    'boxless' => '无盒无瑕',
    'flawed' => '微瑕'
];
try {
    if ($storeId) {
        $stmt2 = $pdo->prepare("SELECT condition_types FROM stores WHERE id = ?");
        $stmt2->execute([$storeId]);
        $row = $stmt2->fetch();
        if ($row && $row['condition_types']) {
            $types = json_decode($row['condition_types'], true);
            if ($types && is_array($types)) {
                $conditionMap = [];
                foreach ($types as $t) $conditionMap[$t['key']] = $t['name'];
            }
        }
    } else {
        $stmt2 = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'condition_types' AND store_id IS NULL");
        $row = $stmt2->fetch();
        if ($row && $row['setting_value']) {
            $types = json_decode($row['setting_value'], true);
            if ($types && is_array($types)) {
                $conditionMap = [];
                foreach ($types as $t) $conditionMap[$t['key']] = $t['name'];
            }
        }
    }
} catch (Exception $e) {}

// 查该场次所有 live_inventory（有库存的）
$stmt = $pdo->prepare("
    SELECT li.*, p.name, p.common_name, p.series, p.barcode, p.qiandao_price, p.image_url
    FROM live_inventory li
    JOIN products p ON p.id = li.product_id
    WHERE li.live_session_id = ?
    ORDER BY p.name
");
$stmt->execute([$liveSessionId]);
$rows = $stmt->fetchAll();

// 按 product_id 分组
$products = [];
foreach ($rows as $row) {
    $pid = $row['product_id'];
    if (!isset($products[$pid])) {
        $products[$pid] = [
            'id' => $pid,
            'name' => $row['name'],
            'common_name' => $row['common_name'],
            'series' => $row['series'],
            'barcode' => $row['barcode'],
            'qiandao_price' => $row['qiandao_price'],
            'image_url' => $row['image_url'],
            'inventory' => []
        ];
    }
    $cname = $conditionMap[$row['condition_type']] ?? $row['condition_type'];
    $livePrice = $row['live_price'];
    $suggestedPrice = $row['suggested_price'];
    $products[$pid]['inventory'][$cname] = [
        'stock' => (int)$row['current_stock'],
        'suggested_price' => (float)$suggestedPrice,
        'live_price' => $livePrice ? (float)$livePrice : null
    ];
    // 同时保留英文 key 方便前端查找
    $products[$pid]['inventory'][$row['condition_type']] = $products[$pid]['inventory'][$cname];
}

// 转为前端卡片网格需要的 inventory_summary 格式
$result = array_map(function($p) {
    $summary = [];
    foreach ($p['inventory'] as $key => $info) {
        // 只保留英文字段 key 的（跳过中文 key 避免重复）
        if (in_array($key, ['sealed','opened','boxless','flawed']) || !isset($summary[$key])) {
            $summary[$key] = [
                'total_stock' => $info['stock'],
                'suggested_price' => $info['suggested_price']
            ];
        }
    }
    // 确保 summary key 是英文 condition key
    $cleanSummary = [];
    foreach ($p['inventory'] as $key => $info) {
        if (!preg_match('/^[a-z_]+$/', $key)) continue; // 跳过中文 key
        $cleanSummary[$key] = [
            'total_stock' => $info['stock'],
            'suggested_price' => $info['suggested_price']
        ];
    }
    return [
        'id' => $p['id'],
        'name' => $p['name'],
        'common_name' => $p['common_name'],
        'series' => $p['series'],
        'barcode' => $p['barcode'],
        'qiandao_price' => $p['qiandao_price'],
        'image_url' => $p['image_url'],
        'inventory_summary' => $cleanSummary
    ];
}, array_values($products));

success(['data' => [
    'session' => $session,
    'products' => $result
]]);
