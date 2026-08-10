<?php
/**
 * vip_sync.php — 外部系统 VIP 客户同步接口
 *
 * 用途：外部系统按店铺推送 VIP 编号 + 昵称，PPMart 增量同步到客户库
 * 鉴权：Header `X-API-Key: <token>`，token 绑定店铺（stores.vip_sync_token）
 *       → 每个店铺独立 token，接口按 token 识别归属店铺，外部系统无需传 store_id
 *
 * 同步规则（2026-08-10 用户确认）：
 *   - (store_id, vip_no) 不存在            → 新增（inserted）
 *   - 存在且昵称不同 → 更新客户库昵称（updated）
 *   - 存在且昵称相同 → 跳过（skipped）
 *   - 缺 vip_no / nickname → invalid
 *
 * 请求示例（单条）：
 *   POST /api/vip_sync.php
 *   X-API-Key: <店铺token>
 *   {"vip_no":"1001","nickname":"小熊饼"}
 *
 * 请求示例（批量）：
 *   {"items":[{"vip_no":"1001","nickname":"小熊饼"},{"vip_no":"1002","nickname":"困困"}]}
 *
 * 每条推送写入 vip_sync_log 审计日志（result: inserted/updated/skipped/invalid）
 */
require_once __DIR__ . '/../config.php';

// ---- Token 鉴权：查 stores 表匹配 token，拿到店铺身份 ----
$token = $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($token === '') {
    http_response_code(401);
    jsonResponse(['success' => false, 'error' => 'unauthorized']);
}

$pdo = getDB();
$stmt = $pdo->prepare("SELECT id, name FROM stores WHERE vip_sync_token = ? AND vip_sync_token IS NOT NULL AND vip_sync_token != ''");
$stmt->execute([$token]);
$store = $stmt->fetch();
if (!$store) {
    http_response_code(401);
    jsonResponse(['success' => false, 'error' => 'unauthorized']);
}
$storeId = (int)$store['id'];

// ---- 解析请求体（兼容单条/批量） ----
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    error('无效的JSON请求体');
}

if (isset($input['items']) && is_array($input['items'])) {
    $items = $input['items'];
} else {
    $items = [$input];
}
if (count($items) === 0) {
    error('没有可同步的数据');
}

$accepted = 0; // 新增
$updated  = 0; // 昵称更新
$skipped  = 0; // 已存在且昵称一致
$invalid  = 0; // 缺字段

$pdo->beginTransaction();
try {
    // INSERT ... ON DUPLICATE KEY UPDATE：
    //   rowCount 1 = 新增；2 = 昵称被更新；0 = 存在但昵称相同（无实际变更）
    $upsertStmt = $pdo->prepare(
        "INSERT INTO vip_customers (store_id, vip_no, nickname) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE nickname = VALUES(nickname)"
    );
    $logStmt = $pdo->prepare("INSERT INTO vip_sync_log (store_id, vip_no, nickname, result) VALUES (?, ?, ?, ?)");

    foreach ($items as $item) {
        if (!is_array($item)) {
            $invalid++;
            $logStmt->execute([$storeId, '', '', 'invalid']);
            continue;
        }
        $vipNo   = trim((string)($item['vip_no'] ?? ''));
        $nickname = trim((string)($item['nickname'] ?? ''));
        if ($vipNo === '' || $nickname === '') {
            $invalid++;
            $logStmt->execute([$storeId, $vipNo, $nickname, 'invalid']);
            continue;
        }

        $upsertStmt->execute([$storeId, $vipNo, $nickname]);
        $affected = $upsertStmt->rowCount();
        if ($affected === 1) {
            $accepted++;
            $logStmt->execute([$storeId, $vipNo, $nickname, 'inserted']);
        } elseif ($affected === 2) {
            $updated++;
            $logStmt->execute([$storeId, $vipNo, $nickname, 'updated']);
        } else {
            $skipped++;
            $logStmt->execute([$storeId, $vipNo, $nickname, 'skipped']);
        }
    }
    $pdo->commit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error('同步失败: ' . $e->getMessage(), 500);
}

success([
    'message'  => '同步完成',
    'store_id' => $storeId,
    'accepted' => $accepted,
    'updated'  => $updated,
    'skipped'  => $skipped,
    'invalid'  => $invalid,
]);
