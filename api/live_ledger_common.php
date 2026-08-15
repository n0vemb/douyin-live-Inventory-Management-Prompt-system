<?php
/**
 * live_ledger_common.php — 直播出库记账公共函数
 * 计算逻辑与原型一致，保证前后端口径统一
 */

/**
 * 计算单个客户的利润指标
 * @param array $customer 客户数据 { items: [{qty, sell_price, purchase_cost, is_gift}], gifts: [{cost}] }
 * @param array $settings 场次配置
 * @return array 各指标
 */
function ledgerCalcCustomer($customer, $settings) {
    $items = array_filter($customer['items'] ?? [], function($i) { return empty($i['is_gift']); });
    $totalQty = 0;
    $gmv = 0.0;
    $cost = 0.0;
    foreach ($items as $item) {
        $qty = (int)($item['qty'] ?? 1);
        $totalQty += $qty;
        $gmv += floatval($item['sell_price'] ?? 0) * $qty;
        $cost += floatval($item['purchase_cost'] ?? 0) * $qty;
    }
    $gmv = round($gmv, 2);
    $cost = round($cost, 2);

    $shipping = $totalQty >= 9 ? floatval($settings['shipping_fee_9']) : floatval($settings['shipping_fee_8']);
    $platformFee = round($gmv * floatval($settings['platform_fee_rate']), 2);
    $packing = floatval($settings['packing_cost']);

    $profitBase = round($gmv - $cost - $shipping - $platformFee - $packing, 2);
    $giftCost = 0.0;
    foreach (($customer['gifts'] ?? []) as $g) {
        $giftCost += floatval($g['cost'] ?? 0);
    }
    $giftCost = round($giftCost, 2);

    $reduceAmount = ($gmv >= floatval($settings['reduce_threshold'])) ? floatval($settings['reduce_amount']) : 0.0;

    $profitWithGift = round($profitBase - $giftCost, 2);
    $profitWithReduce = round($profitBase - $reduceAmount, 2);
    $profitBoth = round($profitBase - $giftCost - $reduceAmount, 2);

    return [
        'total_qty' => $totalQty,
        'gmv' => $gmv,
        'cost' => $cost,
        'shipping' => round($shipping, 2),
        'platform_fee' => $platformFee,
        'packing' => round($packing, 2),
        'profit_base' => $profitBase,
        'profit_base_rate' => $gmv > 0 ? round($profitBase / $gmv, 4) : 0,
        'gift_cost' => $giftCost,
        'profit_with_gift' => $profitWithGift,
        'profit_with_gift_rate' => $gmv > 0 ? round($profitWithGift / $gmv, 4) : 0,
        'reduce_amount' => $reduceAmount,
        'profit_with_reduce' => $profitWithReduce,
        'profit_with_reduce_rate' => $gmv > 0 ? round($profitWithReduce / $gmv, 4) : 0,
        'profit_both' => $profitBoth,
        'profit_both_rate' => $gmv > 0 ? round($profitBoth / $gmv, 4) : 0,
    ];
}

/**
 * 读取场次配置（DB → 数组）
 */
function ledgerGetSettings($pdo, $sessionId) {
    $stmt = $pdo->prepare("SELECT * FROM live_ledger_session WHERE id = ?");
    $stmt->execute([$sessionId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;

    // 赠品预设（名称+价格，仅用于快速添加赠品计算毛利）
    $presets = !empty($row['gift_presets_json']) ? json_decode($row['gift_presets_json'], true) : [];
    if (!is_array($presets)) $presets = [];

    return [
        'session_name' => $row['session_name'],
        'store_id' => $row['store_id'] ?? null,
        'anchor' => $row['anchor'] ?? '',
        'operator' => $row['operator'] ?? '',
        'account' => $row['account'] ?? '',
        'activity_type' => $row['activity_type'],
        'gift_every_n' => (int)$row['gift_every_n'],
        'reduce_threshold' => floatval($row['reduce_threshold']),
        'reduce_amount' => floatval($row['reduce_amount']),
        'platform_fee_rate' => floatval($row['platform_fee_rate']),
        'packing_cost' => floatval($row['packing_cost']),
        'shipping_fee_8' => floatval($row['shipping_fee_8']),
        'shipping_fee_9' => floatval($row['shipping_fee_9']),
        'gift_presets' => $presets,
        'status' => $row['status'],
        'created_at' => $row['created_at'],
        'ended_at' => $row['ended_at'],
    ];
}

/**
 * 读取场次全部数据（客户+明细+赠品）
 */
function ledgerLoadSession($pdo, $sessionId) {
    $settings = ledgerGetSettings($pdo, $sessionId);
    if (!$settings) return null;

    // 动态加载 condition 名称（店铺级优先，否则系统级；均无则回退写死映射）
    $conditionNames = ['sealed' => '原盒未拆', 'opened' => '拆盒无瑕', 'boxless' => '无盒无瑕', 'flawed' => '微瑕'];
    try {
        $storeId = $settings['store_id'] ?? null;
        if (!empty($storeId)) {
            $stmt = $pdo->prepare("SELECT condition_types FROM stores WHERE id = ?");
            $stmt->execute([$storeId]);
            $result = $stmt->fetch();
            if ($result && !empty($result['condition_types'])) {
                $types = json_decode($result['condition_types'], true);
                if (is_array($types)) {
                    $conditionNames = [];
                    foreach ($types as $t) { $conditionNames[$t['key']] = $t['name']; }
                }
            }
        }
        if (empty($conditionNames) || (count($conditionNames) === 4 && $conditionNames['sealed'] === '原盒未拆')) {
            $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'condition_types' AND store_id IS NULL");
            $stmt->execute();
            $result = $stmt->fetch();
            if ($result && !empty($result['setting_value'])) {
                $types = json_decode($result['setting_value'], true);
                if (is_array($types)) {
                    $conditionNames = [];
                    foreach ($types as $t) { $conditionNames[$t['key']] = $t['name']; }
                }
            }
        }
    } catch (Exception $e) {}

    $stmt = $pdo->prepare("SELECT * FROM live_ledger_customer WHERE session_id = ? ORDER BY sort_order, id");
    $stmt->execute([$sessionId]);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($customers as &$c) {
        $stmt = $pdo->prepare("SELECT * FROM live_ledger_item WHERE customer_id = ? ORDER BY id");
        $stmt->execute([$c['id']]);
        $c['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // 补 condition_name（动态配置）
        foreach ($c['items'] as &$item) {
            $item['condition_name'] = $conditionNames[$item['condition_type']] ?? $item['condition_type'];
        }
        unset($item);

        $stmt = $pdo->prepare("SELECT * FROM live_ledger_gift WHERE customer_id = ? ORDER BY id");
        $stmt->execute([$c['id']]);
        $c['gifts'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $c['metrics'] = ledgerCalcCustomer($c, $settings);
    }

// 福袋记录（场次级）
$stmt = $pdo->prepare("SELECT * FROM live_ledger_lucky_draw WHERE session_id = ? ORDER BY id");
$stmt->execute([$sessionId]);
$luckyDraws = $stmt->fetchAll(PDO::FETCH_ASSOC);
$luckyDrawTotalCost = 0.0;
foreach ($luckyDraws as $ld) {
    $luckyDrawTotalCost += floatval($ld['cost'] ?? 0);
}

return ['settings' => $settings, 'customers' => $customers, 'lucky_draws' => $luckyDraws, 'lucky_draw_cost' => round($luckyDrawTotalCost, 2)];
}
