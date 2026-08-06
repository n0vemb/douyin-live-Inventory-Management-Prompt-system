<?php
/**
 * permission_helper.php — 运营角色数据脱敏工具
 * 供各 API 对 operator 角色隐藏成本/利润字段
 */
require_once __DIR__ . '/../auth.php';

/**
 * 当前请求是否需要对成本/利润脱敏（运营角色）
 */
function shouldMaskProfit(): bool {
    return isOperator();
}

/**
 * 对单个商品的成本字段脱敏（原地修改）
 * 隐藏: purchase_price, 进价, cost, profit, 毛利率
 */
function maskProductCost(&$product): void {
    if (!shouldMaskProfit()) return;
    foreach (['purchase_price', 'cost', 'latest_cost', 'batch_cost', 'purchase_cost'] as $k) {
        if (isset($product[$k])) $product[$k] = null;
    }
    foreach (['profit', 'profit_rate', 'gross_profit', 'gross_margin'] as $k) {
        if (isset($product[$k])) $product[$k] = null;
    }
    // 中文键也处理
    foreach (['进价', '成本', '毛利', '毛利率'] as $k) {
        if (isset($product[$k])) $product[$k] = null;
    }
}

/**
 * 对数组中的每个元素脱敏成本字段
 */
function maskProductsCost(&$products): void {
    if (!shouldMaskProfit()) return;
    foreach ($products as &$p) maskProductCost($p);
}

/**
 * 对利润记录脱敏（历史/财务）
 */
function maskProfitRecord(&$record): void {
    if (!shouldMaskProfit()) return;
    foreach (['profit_base', 'profit_base_rate', 'profit_with_gift', 'profit_with_gift_rate',
              'profit_with_reduce', 'profit_with_reduce_rate', 'profit_both', 'profit_both_rate',
              'cost', 'total_cost', 'gift_cost', '娃成本', '毛利', '毛利率', '满赠成本'] as $k) {
        if (isset($record[$k])) $record[$k] = null;
    }
}

/**
 * 对直播记账场次/客户快照脱敏（隐藏成本毛利）
 */
function maskLedgerData(&$data): void {
    if (!shouldMaskProfit()) return;

    // 场次汇总
    foreach (['total_cost', 'total_profit_base', 'total_gift_cost', 'total_profit_with_gift',
              'total_profit_with_reduce', 'total_profit_both', 'cost'] as $k) {
        if (isset($data[$k])) $data[$k] = null;
    }
    if (isset($data['totals']) && is_array($data['totals'])) {
        foreach (['cost', 'profit_base', 'profit_base_rate', 'gift_cost', 'profit_with_gift',
                  'profit_with_gift_rate', 'profit_with_reduce', 'profit_with_reduce_rate',
                  'profit_both', 'profit_both_rate'] as $k) {
            if (isset($data['totals'][$k])) $data['totals'][$k] = null;
        }
    }
    // 客户明细
    if (isset($data['customers']) && is_array($data['customers'])) {
        foreach ($data['customers'] as &$c) {
            if (isset($c['metrics']) && is_array($c['metrics'])) {
                foreach (['cost', 'profit_base', 'profit_base_rate', 'gift_cost',
                          'profit_with_gift', 'profit_with_gift_rate', 'profit_with_reduce',
                          'profit_with_reduce_rate', 'profit_both', 'profit_both_rate'] as $k) {
                    if (isset($c['metrics'][$k])) $c['metrics'][$k] = null;
                }
            }
            // 明细里的进价
            if (isset($c['items']) && is_array($c['items'])) {
                foreach ($c['items'] as &$item) {
                    if (isset($item['purchase_cost'])) $item['purchase_cost'] = null;
                }
            }
            // 赠品成本
            if (isset($c['gifts']) && is_array($c['gifts'])) {
                foreach ($c['gifts'] as &$g) {
                    if (isset($g['cost'])) $g['cost'] = null;
                }
            }
        }
    }
}
