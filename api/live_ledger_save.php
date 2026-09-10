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

// 校验场次存在且在作用域内
$sess = requireLedgerSessionRow($pdo, $sessionId);
if (!in_array($sess['status'], ['active', 'ended'], true)) error('场次状态异常');
$sessionEnded = ($sess['status'] !== 'active');
// 已下播(未打包) 或 已打包出库：删除走软删除，保留记录
$softDelete = $sessionEnded || !empty($sess['off_air_at']);
$sessionShopId = !empty($sess['shop_id']) ? (int)$sess['shop_id'] : null;

/**
 * 软删一条明细：标记删除 + 撤回 pending 出库单；未打包出库时生成待回库单（与硬删一致）
 */
function ledgerSoftDeleteItemRow(PDO $pdo, array $item, int $sessionId, int $storeId, ?int $shopId, bool $sessionEnded): void {
    $pdo->prepare('UPDATE live_ledger_item SET is_deleted = 1 WHERE id = ?')->execute([(int)$item['id']]);
    $pdo->prepare("UPDATE warehouse_task SET status = 'cancelled' WHERE source_type = 'item' AND source_id = ? AND status = 'pending' AND type = 'out'")
        ->execute([(int)$item['id']]);
    if (!$sessionEnded && empty($item['is_gift']) && empty($item['is_temp'])) {
        $pdo->prepare("INSERT INTO warehouse_task (store_id, shop_id, session_id, source_type, source_id, customer_id, product_id, product_name, condition_type, qty, is_gift, type, status)
                       VALUES (?, ?, ?, 'item', ?, ?, ?, ?, ?, ?, 0, 'return', 'pending')")
            ->execute([$storeId, $shopId, $sessionId, (int)$item['id'], (int)$item['customer_id'], (int)$item['product_id'], $item['product_name'], $item['condition_type'], (int)$item['qty']]);
    }
}

/** 软删一条赠品：标记删除 + 撤 pending 出库单（赠品不回收） */
function ledgerSoftDeleteGiftRow(PDO $pdo, array $gift): void {
    $pdo->prepare('UPDATE live_ledger_gift SET is_deleted = 1 WHERE id = ?')->execute([(int)$gift['id']]);
    $pdo->prepare("UPDATE warehouse_task SET status = 'cancelled' WHERE source_type = 'gift' AND source_id = ? AND status = 'pending' AND type = 'out'")
        ->execute([(int)$gift['id']]);
}

/** 软删整个客户：客户+其明细+赠品全部标记删除，并处理出库/回库任务 */
function ledgerSoftDeleteCustomerRow(PDO $pdo, int $custId, int $sessionId, int $storeId, ?int $shopId, bool $sessionEnded): void {
    $pdo->prepare('UPDATE live_ledger_customer SET is_deleted = 1 WHERE id = ?')->execute([$custId]);

    $stmt = $pdo->prepare('SELECT * FROM live_ledger_item WHERE customer_id = ? AND is_deleted = 0');
    $stmt->execute([$custId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $it) {
        ledgerSoftDeleteItemRow($pdo, $it, $sessionId, $storeId, $shopId, $sessionEnded);
    }
    $stmt = $pdo->prepare('SELECT * FROM live_ledger_gift WHERE customer_id = ? AND is_deleted = 0');
    $stmt->execute([$custId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $g) {
        ledgerSoftDeleteGiftRow($pdo, $g);
    }
}

$pdo->beginTransaction();
try {
    // 现有客户 id 集合（用于删除）
    $stmt = $pdo->prepare("SELECT id FROM live_ledger_customer WHERE session_id = ?");
    $stmt->execute([$sessionId]);
    $existingCustomerIds = array_column($stmt->fetchAll(), 'id');
    $seenCustomerIds = [];

    $sortOrder = 0;
    $seenVipNos = [];
    foreach ($customers as $customer) {
        $sortOrder++;
        $custId = isset($customer['id']) ? (int)$customer['id'] : 0;
        $nickname = trim($customer['nickname'] ?? '');
        $vipNo = trim($customer['vip_no'] ?? '');

        // 同批次内 VIP 重复检测（新增客户）
        if ($vipNo !== '' && $custId <= 0) {
            if (isset($seenVipNos[$vipNo])) {
                throw new Exception("VIP $vipNo 在本场次已存在，请勿重复添加");
            }
            $seenVipNos[$vipNo] = true;
        }

        if ($custId > 0) {
            // 校验该客户属于本场次
            $stmt = $pdo->prepare("SELECT id, is_deleted FROM live_ledger_customer WHERE id = ? AND session_id = ?");
            $stmt->execute([$custId, $sessionId]);
            $existCust = $stmt->fetch();
            if (!$existCust) throw new Exception("客户 $custId 不属于场次");
            $custDeleted = !empty($customer['is_deleted']) ? 1 : 0;
            $stmt = $pdo->prepare("UPDATE live_ledger_customer SET nickname = ?, vip_no = ?, sort_order = ?, is_deleted = ? WHERE id = ?");
            $stmt->execute([$nickname, $vipNo, $sortOrder, $custDeleted, $custId]);
            if ($custDeleted && empty($existCust['is_deleted'])) {
                ledgerSoftDeleteCustomerRow($pdo, $custId, $sessionId, $storeId, $sessionShopId, $sessionEnded);
            }
            $seenCustomerIds[] = $custId;
        } else {
            // id<=0（前端临时负id转0，或新客户）：有 VIP 编号时先按 (session_id, vip_no) 查重，
            // 已存在则 UPDATE（避免前端未同步真实id时重复 INSERT），否则 INSERT
            // 无 VIP 编号：无法可靠查重，直接 INSERT（前端 autoSave 后重载即可拿到真实id）
            $existId = false;
            if ($vipNo !== '') {
                $existStmt = $pdo->prepare("SELECT id FROM live_ledger_customer WHERE session_id = ? AND vip_no = ? LIMIT 1");
                $existStmt->execute([$sessionId, $vipNo]);
                $existId = $existStmt->fetchColumn();
            }
            if ($existId) {
                $custId = (int)$existId;
                $stmt = $pdo->prepare("UPDATE live_ledger_customer SET nickname = ?, vip_no = ?, sort_order = ? WHERE id = ?");
                $stmt->execute([$nickname, $vipNo, $sortOrder, $custId]);
                $seenCustomerIds[] = $custId;
            } else {
                $stmt = $pdo->prepare("INSERT INTO live_ledger_customer (session_id, nickname, vip_no, sort_order) VALUES (?, ?, ?, ?)");
                $stmt->execute([$sessionId, $nickname, $vipNo, $sortOrder]);
                $custId = (int)$pdo->lastInsertId();
                $seenCustomerIds[] = $custId;
            }
        }

        // 同步客户库（vip_customers）：有VIP编号且有昵称时，在场次内改名同步更新客户管理库（按本店隔离）
        if ($vipNo !== '' && $nickname !== '') {
            $stmt = $pdo->prepare("SELECT id, nickname FROM vip_customers WHERE store_id = ? AND vip_no = ?");
            $stmt->execute([$storeId, $vipNo]);
            $vipRow = $stmt->fetch();
            if ($vipRow) {
                // 已存在：昵称不同则更新（场次内改名 → 同步客户库）
                if ($vipRow['nickname'] !== $nickname) {
                    $stmt = $pdo->prepare("UPDATE vip_customers SET nickname = ? WHERE id = ?");
                    $stmt->execute([$nickname, $vipRow['id']]);
                }
            } else {
                // 不存在：新客户入库（客户管理页可见，归属本店）
                $stmt = $pdo->prepare("INSERT INTO vip_customers (store_id, vip_no, nickname) VALUES (?, ?, ?)");
                $stmt->execute([$storeId, $vipNo, $nickname]);
            }
        }

        // 处理该客户的 items
        $stmt = $pdo->prepare("SELECT id, is_deleted FROM live_ledger_item WHERE customer_id = ?");
        $stmt->execute([$custId]);
        $existingItemRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $existingItemIds = array_column($existingItemRows, 'id');
        $existingItemDeleted = [];
        foreach ($existingItemRows as $r) { $existingItemDeleted[(int)$r['id']] = (int)$r['is_deleted']; }
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
            $isTemp = !empty($item['is_temp']) ? 1 : 0;
            $isDeleted = !empty($item['is_deleted']) ? 1 : 0;

            // 进价缺失/为0时自动补真实进价（防运营端或漏传导致成本丢失；临时商品无真实商品跳过）
            if ($purchaseCost <= 0 && !$isGift && $productId > 0 && !$isTemp) {
                $stmt = $pdo->prepare("SELECT purchase_price FROM inventory_batches WHERE product_id = ? AND condition_type = ? AND remaining_qty - locked_qty > 0 AND purchase_price > 0 AND store_id = ? ORDER BY purchased_at DESC, id DESC LIMIT 1");
                $stmt->execute([$productId, $conditionType, $storeId]);
                $realCost = $stmt->fetchColumn();
                if ($realCost !== false) $purchaseCost = floatval($realCost);
            }

            if ($itemId > 0) {
                $wasDeleted = !empty($existingItemDeleted[$itemId]);
                $stmt = $pdo->prepare("UPDATE live_ledger_item SET product_id = ?, condition_type = ?, product_name = ?, qty = ?, sell_price = ?, purchase_cost = ?, is_gift = ?, is_temp = ?, is_deleted = ? WHERE id = ? AND customer_id = ?");
                $stmt->execute([$productId, $conditionType, $productName, $qty, $sellPrice, $purchaseCost, $isGift, $isTemp, $isDeleted, $itemId, $custId]);
                $seenItemIds[] = $itemId;

                // 刚被标记删除 → 软删联动（撤 pending 出库单；未打包时生成回库单）
                if ($isDeleted && !$wasDeleted) {
                    $rowStmt = $pdo->prepare('SELECT * FROM live_ledger_item WHERE id = ?');
                    $rowStmt->execute([$itemId]);
                    $row = $rowStmt->fetch(PDO::FETCH_ASSOC);
                    if ($row) ledgerSoftDeleteItemRow($pdo, $row, $sessionId, $storeId, $sessionShopId, $sessionEnded);
                }

                // 同步仓库出库单（仅 pending 状态跟随修改，已处理的历史单不动）；临时商品无出库单
                if (!$isTemp && !$isDeleted) {
                    $stmt = $pdo->prepare("UPDATE warehouse_task SET product_id = ?, product_name = ?, condition_type = ?, qty = ?, is_gift = ? WHERE source_type = 'item' AND source_id = ? AND status = 'pending'");
                    $stmt->execute([$productId, $productName, $conditionType, $qty, $isGift, $itemId]);
                } elseif ($isDeleted) {
                    $pdo->prepare("UPDATE warehouse_task SET status = 'cancelled' WHERE source_type = 'item' AND source_id = ? AND status = 'pending' AND type = 'out'")->execute([$itemId]);
                }
            } else {
                $stmt = $pdo->prepare("INSERT INTO live_ledger_item (session_id, customer_id, product_id, condition_type, product_name, qty, sell_price, purchase_cost, is_gift, is_temp, is_deleted) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$sessionId, $custId, $productId, $conditionType, $productName, $qty, $sellPrice, $purchaseCost, $isGift, $isTemp, $isDeleted]);
                $itemId = (int)$pdo->lastInsertId();
                $seenItemIds[] = $itemId;

                // 新增商品 → 生成仓库待出库单（同事务）；临时商品不入库不生成出库单
                if (!$isTemp && $productId > 0 && !$isDeleted) {
                    $stmt = $pdo->prepare("INSERT INTO warehouse_task (store_id, shop_id, session_id, source_type, source_id, customer_id, product_id, product_name, condition_type, qty, is_gift, type, status) VALUES (?, ?, ?, 'item', ?, ?, ?, ?, ?, ?, ?, 'out', 'pending')");
                    $stmt->execute([$storeId, $sessionShopId, $sessionId, $itemId, $custId, $productId, $productName, $conditionType, $qty, $isGift]);
                }
            }
        }
        // 删除前端未提交的 items
        $toDelete = array_diff($existingItemIds, $seenItemIds);
        if (!empty($toDelete)) {
            $ph = implode(',', array_fill(0, count($toDelete), '?'));
            // 先取被删商品信息（生成回库单 / 软删用）
            $stmt = $pdo->prepare("SELECT * FROM live_ledger_item WHERE id IN ($ph)");
            $stmt->execute(array_values($toDelete));
            $deletedItems = $stmt->fetchAll();
            foreach ($deletedItems as $di) {
                if ($softDelete) {
                    ledgerSoftDeleteItemRow($pdo, $di, $sessionId, $storeId, $sessionShopId, $sessionEnded);
                } else {
                    // 撤回原待出库单（仅 pending）
                    $stmt = $pdo->prepare("UPDATE warehouse_task SET status = 'cancelled' WHERE source_type = 'item' AND source_id = ? AND status = 'pending'");
                    $stmt->execute([$di['id']]);
                    // 正常商品删除 → 自动生成待回库单（赠品/临时商品不回收，只撤单）
                    if (!$di['is_gift'] && !$di['is_temp']) {
                        $stmt = $pdo->prepare("INSERT INTO warehouse_task (store_id, shop_id, session_id, source_type, source_id, customer_id, product_id, product_name, condition_type, qty, is_gift, type, status) VALUES (?, ?, ?, 'item', ?, ?, ?, ?, ?, ?, 0, 'return', 'pending')");
                        $stmt->execute([$storeId, $sessionShopId, $di['session_id'], $di['id'], $di['customer_id'], $di['product_id'], $di['product_name'], $di['condition_type'], $di['qty']]);
                    }
                }
            }
            if (!$softDelete) {
                $stmt = $pdo->prepare("DELETE FROM live_ledger_item WHERE id IN ($ph)");
                $stmt->execute(array_values($toDelete));
            }
        }

        // 处理 gifts
        $stmt = $pdo->prepare("SELECT id, is_deleted FROM live_ledger_gift WHERE customer_id = ?");
        $stmt->execute([$custId]);
        $existingGiftRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $existingGiftIds = array_column($existingGiftRows, 'id');
        $existingGiftDeleted = [];
        foreach ($existingGiftRows as $r) { $existingGiftDeleted[(int)$r['id']] = (int)$r['is_deleted']; }
        $seenGiftIds = [];

        foreach (($customer['gifts'] ?? []) as $gift) {
            $giftId = isset($gift['id']) ? (int)$gift['id'] : 0;
            $cost = floatval($gift['cost'] ?? 0);
            $desc = trim($gift['description'] ?? '');
            $giftName = trim($gift['name'] ?? '');
            $giftQty = max(1, (int)($gift['qty'] ?? 1));
            $isDeleted = !empty($gift['is_deleted']) ? 1 : 0;

            if ($giftId > 0) {
                $wasDeleted = !empty($existingGiftDeleted[$giftId]);
                $stmt = $pdo->prepare("UPDATE live_ledger_gift SET cost = ?, description = ?, name = ?, qty = ?, is_deleted = ? WHERE id = ? AND customer_id = ?");
                $stmt->execute([$cost, $desc, $giftName, $giftQty, $isDeleted, $giftId, $custId]);
                $seenGiftIds[] = $giftId;

                if ($isDeleted && !$wasDeleted) {
                    $rowStmt = $pdo->prepare('SELECT * FROM live_ledger_gift WHERE id = ?');
                    $rowStmt->execute([$giftId]);
                    $row = $rowStmt->fetch(PDO::FETCH_ASSOC);
                    if ($row) ledgerSoftDeleteGiftRow($pdo, $row);
                }
                // 同步仓库出库单（仅 pending 跟随修改）
                if (!$isDeleted) {
                    $stmt = $pdo->prepare("UPDATE warehouse_task SET product_name = ?, qty = ? WHERE source_type = 'gift' AND source_id = ? AND status = 'pending'");
                    $stmt->execute([$giftName, $giftQty, $giftId]);
                } else {
                    $pdo->prepare("UPDATE warehouse_task SET status = 'cancelled' WHERE source_type = 'gift' AND source_id = ? AND status = 'pending' AND type = 'out'")->execute([$giftId]);
                }
            } else {
                $stmt = $pdo->prepare("INSERT INTO live_ledger_gift (session_id, customer_id, name, qty, cost, description, is_deleted) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$sessionId, $custId, $giftName, $giftQty, $cost, $desc, $isDeleted]);
                $giftId = (int)$pdo->lastInsertId();
                $seenGiftIds[] = $giftId;

                // 新增赠品 → 生成仓库待出库单（赠品照发，不回收）
                if (!$isDeleted) {
                    $stmt = $pdo->prepare("INSERT INTO warehouse_task (store_id, session_id, source_type, source_id, customer_id, product_name, qty, is_gift, type, status) VALUES (?, ?, 'gift', ?, ?, ?, ?, 1, 'out', 'pending')");
                    $stmt->execute([$storeId, $sessionId, $giftId, $custId, $giftName, $giftQty]);
                }
            }
        }
        $toDelete = array_diff($existingGiftIds, $seenGiftIds);
        if (!empty($toDelete)) {
            $ph = implode(',', array_fill(0, count($toDelete), '?'));
            if ($softDelete) {
                $stmt = $pdo->prepare("SELECT * FROM live_ledger_gift WHERE id IN ($ph)");
                $stmt->execute(array_values($toDelete));
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $g) {
                    ledgerSoftDeleteGiftRow($pdo, $g);
                }
            } else {
                // 撤回原赠品待出库单（赠品删除不回收，只撤单）
                $stmt = $pdo->prepare("UPDATE warehouse_task SET status = 'cancelled' WHERE source_type = 'gift' AND source_id IN ($ph) AND status = 'pending'");
                $stmt->execute(array_values($toDelete));
                $stmt = $pdo->prepare("DELETE FROM live_ledger_gift WHERE id IN ($ph)");
                $stmt->execute(array_values($toDelete));
            }
        }
    }

    // 删除前端未提交的客户（含其 items/gifts）
    // 保护：本次提交中存在"疑似新建"客户（id=0，即前端临时负id转0）时跳过删除，
    //       避免把已存在但前端尚未同步真实id的客户误删。前端 autoSave 后重载拿到真实id，
    //       下次保存时 id 全为正，删除逻辑再正常执行。
    $hasNewCustomer = false;
    foreach ($customers as $customer) {
        if ((int)($customer['id'] ?? 0) <= 0) { $hasNewCustomer = true; break; }
    }
    $toDeleteCust = [];
    if (!$hasNewCustomer) {
        $toDeleteCust = array_diff($existingCustomerIds, $seenCustomerIds);
    }
    if (!empty($toDeleteCust)) {
        $ph = implode(',', array_fill(0, count($toDeleteCust), '?'));
        if ($softDelete) {
            foreach (array_values($toDeleteCust) as $cid) {
                ledgerSoftDeleteCustomerRow($pdo, (int)$cid, $sessionId, $storeId, $sessionShopId, $sessionEnded);
            }
        } else {
            // 该客户被整体删除：先撤其全部 pending 出库单 + 非赠品生成待回库单
            $stmt = $pdo->prepare("UPDATE warehouse_task SET status = 'cancelled' WHERE customer_id IN ($ph) AND status = 'pending'");
            $stmt->execute(array_values($toDeleteCust));
            $stmt = $pdo->prepare("SELECT id, session_id, customer_id, product_id, product_name, condition_type, qty, is_gift FROM live_ledger_item WHERE customer_id IN ($ph) AND is_gift = 0");
            $stmt->execute(array_values($toDeleteCust));
            foreach ($stmt->fetchAll() as $di) {
                $stmt2 = $pdo->prepare("INSERT INTO warehouse_task (store_id, session_id, source_type, source_id, customer_id, product_id, product_name, condition_type, qty, is_gift, type, status) VALUES (?, ?, 'item', ?, ?, ?, ?, ?, ?, 0, 'return', 'pending')");
                $stmt2->execute([$storeId, $di['session_id'], $di['id'], $di['customer_id'], $di['product_id'], $di['product_name'], $di['condition_type'], $di['qty']]);
            }
            $stmt = $pdo->prepare("DELETE FROM live_ledger_item WHERE customer_id IN ($ph)");
            $stmt->execute(array_values($toDeleteCust));
            $stmt = $pdo->prepare("DELETE FROM live_ledger_gift WHERE customer_id IN ($ph)");
            $stmt->execute(array_values($toDeleteCust));
            $stmt = $pdo->prepare("DELETE FROM live_ledger_customer WHERE id IN ($ph)");
            $stmt->execute(array_values($toDeleteCust));
        }
    }

    $pdo->commit();
    success(['data' => ['saved_customers' => count($customers)]]);
} catch (Exception $e) {
    $pdo->rollBack();
    error('保存失败: ' . $e->getMessage());
}
