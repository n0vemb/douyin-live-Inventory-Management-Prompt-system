<?php
/**
 * 一次性回填：outbound_log 直播出库记录 → 按 remark 场次名匹配 live_ledger_session
 * 更新 live_session_id + account，便于商品流水追溯"哪场直播/哪个运营"
 * 用法: php backfill_outbound_session.php [--dry-run]
 */
$dryRun = in_array('--dry-run', $argv);

require __DIR__ . '/../config.php';
$pdo = getDB();

$rows = $pdo->query("
    SELECT ob.id, ob.remark, ob.live_session_id, ob.account
    FROM outbound_log ob
    WHERE ob.remark LIKE '直播出库(%'
      AND (ob.live_session_id IS NULL OR ob.live_session_id = 0)
")->fetchAll(PDO::FETCH_ASSOC);

echo "待回填: " . count($rows) . " 条\n";

// 预取全部场次名 → id/account 映射（同场次名可能多个，取最新 ended）
$sessions = $pdo->query("
    SELECT id, session_name, account FROM live_ledger_session ORDER BY id
")->fetchAll(PDO::FETCH_ASSOC);
$map = [];
foreach ($sessions as $s) {
    $map[$s['session_name']] = ['id' => (int)$s['id'], 'account' => $s['account'] ?? ''];
}

$updated = 0; $unmatched = [];
foreach ($rows as $r) {
    // 从 remark "直播出库(场次名)" 提取场次名
    if (!preg_match('/^直播出库\((.+)\)$/', $r['remark'], $m)) { $unmatched[] = $r['remark']; continue; }
    $name = $m[1];
    if (!isset($map[$name])) { $unmatched[] = $r['remark']; continue; }
    $sid = $map[$name]['id'];
    $account = $map[$name]['account'];
    if ($dryRun) {
        echo "  [dry] id={$r['id']} remark={$r['remark']} -> session_id=$sid account=$account\n";
        $updated++;
        continue;
    }
    $stmt = $pdo->prepare("UPDATE outbound_log SET live_session_id = ?, account = ? WHERE id = ?");
    $stmt->execute([$sid, $account, $r['id']]);
    $updated++;
}

echo "完成: " . ($dryRun ? '[dry-run] ' : '') . "更新 $updated 条\n";
if ($unmatched) {
    echo "未匹配 " . count($unmatched) . " 条:\n";
    foreach (array_slice($unmatched, 0, 10) as $u) echo "  $u\n";
}
