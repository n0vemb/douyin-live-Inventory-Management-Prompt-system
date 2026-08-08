<?php
/**
 * live_ledger_save.php — 保存场次客户/明细/赠品（全量替换）
 * POST { session_id, customers: [{ id?, nickname, vip_no, items: [{id?, product_id, product_name, qty, sell_price, purchase_cost, is_gift}], gifts: [{id?, cost, description}] }] }
 *
 * 策略：前端传全量，后端按客户 id 匹配（>0 更新，==0 新增），
 * 删除前端不存在的记录。放在事务里，保证一致性。
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$pdo = getDB();
requireAuth(); $storeId = getStoreId();
if (empty($storeId)) {
    error('请先选择店铺后再操作');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) error('无效请求');

$sessionId = isset($input['session_id']) ? (int)$input['session_id'] : 0;
$customers = $input['customers'] ?? [];

if ($sessionId <= 0) error('缺少场次ID');

// 校验场次存在且属于本店铺且 active
$stmt = $pdo->prepare("SELECT status FROM live_ledger_session WHERE id = ? AND store_id = ?");
$stmt->execute([$sessionId, $storeId]);
$sess = $stmt->fetch();
if (!$sess) error('场次不存在');
if ($sess['status'] !== 'active') error('已结束的场次不能修改');

$pdo->beginTransaction();
try {
    // 现有客户 id 集合（用于删除）
    $stmt = $pdo->prepare("SELECT id FROM live_ledger_customer WHERE session_id = ?");
    $stmt->execute([$sessionId]);
    $existingCustomerIds = array_column($stmt->fetchAll(), 'id');
    $seenCustomerIds = [];

    $sortOrder = 0;
    foreach ($customers as $customer) {
        $sortOrder++;
        $custId = isset($customer['id']) ? (int)$customer['id'] : 0;
        $nickname = trim($customer['nickname'] ?? '');
        $vipNo = trim($customer['vip_no'] ?? '');

        if ($custId > 0) {
            // 校验该客户属于本场次
            $stmt = $pdo->prepare("SELECT id FROM live_ledger_customer WHERE id = ? AND session_id = ?");
            $stmt->execute([$custId, $sessionId]);
            if (!$stmt->fetch()) throw new Exception("客户 $custId 不属于场次");
            $stmt = $pdo->prepare("UPDATE live_ledger_customer SET nickname = ?, vip_no = ?, sort_order = ? WHERE id = ?");
            $stmt->execute([$nickname, $vipNo, $sortOrder, $custId]);
            $seenCustomerIds[] = $custId;
        } else {
            $stmt = $pdo->prepare("INSERT INTO live_ledger_customer (session_id, nickname, vip_no, sort_order) VALUES (?, ?, ?, ?)");
            $stmt->execute([$sessionId, $nickname, $vipNo, $sortOrder]);
            $custId = (int)$pdo->lastInsertId();
            $seenCustomerIds[] = $custId;
        }

        // 处理该客户的 items
        $stmt = $pdo->prepare("SELECT id FROM live_ledger_item WHERE customer_id = ?");
        $stmt->execute([$custId]);
        $existingItemIds = array_column($stmt->fetchAll(), 'id');
        $seenItemIds = [];

        foreach (($customer['items'] ?? []) as $item) {
            $itemId = isset($item['id']) ? (int)$item['id'] : 0;
            $productId = (int)($item['product_id'] ?? 0);
            $productName = trim($item['product_name'] ?? '');
            $conditionType = trim($item['condition_type'] ?? '');
            $qty = max(1, (int)($item['qty'] ?? 1));
            $sellPrice = floatval($item['sell_price'] ?? 0);
            $purchaseCost = floatval($item['purchase_cost'] ?? 0);
            $isGift = !empty($item['is_gift']) ? 1 : 0;

            // 进价缺失/为0时自动补真实进价（防运营端或漏传导致成本丢失）
            if ($purchaseCost <= 0 && !$isGift && $productId > 0) {
                $stmt = $pdo->prepare("SELECT purchase_price FROM inventory_batches WHERE product_id = ? AND condition_type = ? AND remaining_qty > 0 AND purchase_price > 0 AND store_id = ? ORDER BY purchased_at DESC, id DESC LIMIT 1");
                $stmt->execute([$productId, $conditionType, $storeId]);
                $realCost = $stmt->fetchColumn();
                if ($realCost !== false) $purchaseCost = floatval($realCost);
            }

            if ($itemId > 0) {
                $stmt = $pdo->prepare("UPDATE live_ledger_item SET product_id = ?, condition_type = ?, product_name = ?, qty = ?, sell_price = ?, purchase_cost = ?, is_gift = ? WHERE id = ? AND customer_id = ?");
                $stmt->execute([$productId, $conditionType, $productName, $qty, $sellPrice, $purchaseCost, $isGift, $itemId, $custId]);
                $seenItemIds[] = $itemId;
            } else {
                $stmt = $pdo->prepare("INSERT INTO live_ledger_item (session_id, customer_id, product_id, condition_type, product_name, qty, sell_price, purchase_cost, is_gift) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$sessionId, $custId, $productId, $conditionType, $productName, $qty, $sellPrice, $purchaseCost, $isGift]);
                $seenItemIds[] = (int)$pdo->lastInsertId();
            }
        }
        // 删除前端未提交的 items
        $toDelete = array_diff($existingItemIds, $seenItemIds);
        if (!empty($toDelete)) {
            $ph = implode(',', array_fill(0, count($toDelete), '?'));
            $stmt = $pdo->prepare("DELETE FROM live_ledger_item WHERE id IN ($ph)");
            $stmt->execute(array_values($toDelete));
        }

        // 处理 gifts
        $stmt = $pdo->prepare("SELECT id FROM live_ledger_gift WHERE customer_id = ?");
        $stmt->execute([$custId]);
        $existingGiftIds = array_column($stmt->fetchAll(), 'id');
        $seenGiftIds = [];

        foreach (($customer['gifts'] ?? []) as $gift) {
            $giftId = isset($gift['id']) ? (int)$gift['id'] : 0;
            $cost = floatval($gift['cost'] ?? 0);
            $desc = trim($gift['description'] ?? '');

            if ($giftId > 0) {
                $stmt = $pdo->prepare("UPDATE live_ledger_gift SET cost = ?, description = ? WHERE id = ? AND customer_id = ?");
                $stmt->execute([$cost, $desc, $giftId, $custId]);
                $seenGiftIds[] = $giftId;
            } else {
                $stmt = $pdo->prepare("INSERT INTO live_ledger_gift (session_id, customer_id, cost, description) VALUES (?, ?, ?, ?)");
                $stmt->execute([$sessionId, $custId, $cost, $desc]);
                $seenGiftIds[] = (int)$pdo->lastInsertId();
            }
        }
        $toDelete = array_diff($existingGiftIds, $seenGiftIds);
        if (!empty($toDelete)) {
            $ph = implode(',', array_fill(0, count($toDelete), '?'));
            $stmt = $pdo->prepare("DELETE FROM live_ledger_gift WHERE id IN ($ph)");
            $stmt->execute(array_values($toDelete));
        }
    }

    // 删除前端未提交的客户（含其 items/gifts）
    $toDeleteCust = array_diff($existingCustomerIds, $seenCustomerIds);
    if (!empty($toDeleteCust)) {
        $ph = implode(',', array_fill(0, count($toDeleteCust), '?'));
        $stmt = $pdo->prepare("DELETE FROM live_ledger_item WHERE customer_id IN ($ph)");
        $stmt->execute(array_values($toDeleteCust));
        $stmt = $pdo->prepare("DELETE FROM live_ledger_gift WHERE customer_id IN ($ph)");
        $stmt->execute(array_values($toDeleteCust));
        $stmt = $pdo->prepare("DELETE FROM live_ledger_customer WHERE id IN ($ph)");
        $stmt->execute(array_values($toDeleteCust));
    }

    $pdo->commit();
    success(['data' => ['saved_customers' => count($customers)]]);
} catch (Exception $e) {
    $pdo->rollBack();
    error('保存失败: ' . $e->getMessage());
}
