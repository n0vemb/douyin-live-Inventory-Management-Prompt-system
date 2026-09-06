<?php
/**
 * product_meta_scan.php — 商品信息完善：扫描在库商品的品牌/IP · 系列
 * 权限：店管 / 超管（当前会话店铺）
 * 数据源：ysjp 目录快照（千岛在 candidates 接口按需兜底）
 * GET/POST 参数：keyword, filter(all|missing_brand|missing_series|mismatch|no_source)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/product_meta_lib.php';

$pdo = getDB();
requireAuth();
$storeId = getStoreId();
if (!$storeId) error('请先选择店铺（数智新匠）后再使用');
if (!in_array($_SESSION['role'] ?? '', ['store_admin', 'super_admin'], true)) {
    error('无权限：仅店管/超管可使用商品信息完善工具', 403);
}

$input = $_GET;
$keyword = trim((string)($input['keyword'] ?? ''));
$filter = (string)($input['filter'] ?? 'all');
$skipIds = [];
foreach (explode(',', (string)($input['skip'] ?? '')) as $sid) {
    $sid = (int)$sid;
    if ($sid > 0) $skipIds[$sid] = $sid;
}

try {
    $cat = pmLoadCatalog();
    if (!$cat) error('ysjp 目录数据缺失：' . pmCatalogPath());

    $stmt = $pdo->prepare("SELECT p.id, p.name, p.common_name, COALESCE(p.brand,'') brand, COALESCE(p.series,'') series,
                                  (SELECT COALESCE(SUM(b.remaining_qty),0) FROM inventory_batches b
                                   WHERE b.product_id = p.id AND b.store_id = p.store_id AND b.remaining_qty > 0) stock_total
                           FROM products p
                           WHERE p.store_id = ?" . ($skipIds ? " AND p.id NOT IN (" . implode(',', $skipIds) . ")" : "") . "
                           ORDER BY p.name, p.id");
    $stmt->execute([$storeId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $instockCount = 0;
    foreach ($rows as $rr) if ((int)$rr['stock_total'] > 0) $instockCount++;

    $storeName = $pdo->prepare('SELECT name FROM stores WHERE id = ?');
    $storeName->execute([$storeId]);

    // 供前端下拉/补全的候选值
    $dlSeries = [];
    foreach (($cat['ips'] ?? []) as $iv) {
        foreach (($iv['series'] ?? []) as $s) if ($s !== '') $dlSeries[pmNorm($s)] = $s;
    }
    $dlBrands = [];
    foreach (($cat['ips'] ?? []) as $iv) {
        $bn = trim((string)($iv['name'] ?? ''));
        if ($bn !== '') $dlBrands[pmNorm($bn)] = $bn;
    }

    $problems = [];
    $groups = [];
    $stats = ['total' => 0, 'instock' => $instockCount, 'missing_brand' => 0, 'missing_series' => 0, 'mismatch' => 0, 'variant' => 0, 'no_source' => 0];
    $groupMap = [];

    foreach ($rows as $p) {
        $p['brand'] = trim((string)$p['brand']);
        $p['series'] = trim((string)$p['series']);
        $m = pmMatchProduct($p, $cat);
        $matched = $m['matched'];
        $cands = $m['cands'];
        $unique = $m['unique'];

        $flags = [];
        $recBrand = '';
        $recSeries = '';
        $recSrc = '';
        if ($matched && $unique) {
            $c = $cands[0];
            $recBrand = $c['ip_name'];
            $recSeries = $c['series'];
            $recSrc = $c['src'];
            if ($p['brand'] === '') $flags[] = 'missing_brand';
            elseif (!pmBrandMatches($p['brand'], $c)) $flags[] = 'brand_mismatch';
            if ($p['series'] === '') $flags[] = 'missing_series';
            else {
                $normCur = pmNorm($p['series']);
                $normRec = pmNorm($c['series']);
                if ($normCur !== $normRec) {
                    if (pmSeriesSameText($p['series'], $c['series'])) {
                        // 只差「系列/套装/盒」字样：视为同一写法，不提示
                    } elseif (pmSeriesSimilar($p['series'], $c['series'])) {
                        $flags[] = 'series_variant';
                    } else {
                        $flags[] = 'series_mismatch';
                    }
                }
            }
        } elseif (!$matched) {
            $flags[] = 'no_source';
        } else {
            $flags[] = 'multi_candidate';
        }

        $bad = (bool)$flags && $flags !== ['multi_candidate'];
        $row = [
            'id'             => (int)$p['id'],
            'name'           => $p['name'],
            'common_name'    => $p['common_name'] ?: '',
            'brand'          => $p['brand'],
            'series'         => $p['series'],
            'stock_total'    => (int)$p['stock_total'],
            'periph'         => pmPeripheralType($p['name']),
            'matched'        => $matched,
            'unique'         => $unique,
            'cands'          => array_slice($cands, 0, 8),
            'flags'          => $flags,
            'rec_brand'      => $recBrand,
            'rec_series'     => $recSeries,
            'rec_src'        => $recSrc,
        ];

        foreach ($flags as $f) {
            if ($f === 'missing_brand') $stats['missing_brand']++;
            if ($f === 'missing_series') $stats['missing_series']++;
            if (in_array($f, ['brand_mismatch', 'series_mismatch'], true)) $stats['mismatch']++;
            if ($f === 'series_variant') $stats['variant']++;
            if ($f === 'no_source') $stats['no_source']++;
        }
        $stats['total']++;

        // 同源系列归组：用于「写法不一致」统一（在日光之下 / 第10代 在日光下系列）
        if ($matched && $unique) {
            $c = $cands[0];
            $gk = $c['ip'] . '|' . $c['src'];
            if (!isset($groupMap[$gk])) {
                $groupMap[$gk] = [
                    'key'       => $gk,
                    'ip'        => $c['ip'],
                    'ip_name'   => $c['ip_name'],
                    'series'    => $c['series'],
                    'src'       => $c['src'],
                    'products'  => [],
                    'variants'  => [],
                ];
            }
            $groupMap[$gk]['products'][] = [
                'id'     => (int)$p['id'],
                'name'   => $p['name'],
                'brand'  => $p['brand'],
                'series' => $p['series'],
                // 需要“统一” = 文字与推荐不一致（缺系列字/初代vs第1代等），已一致的不动
                'need'   => $p['series'] === '' || pmNorm($p['series']) !== pmNorm($c['series']),
            ];
        }

        // 过滤显示
        $hitFilter = true;
        if ($keyword !== '') {
            $kw = mb_strtolower($keyword, 'UTF-8');
            $hitFilter = mb_strpos(mb_strtolower($p['name'], 'UTF-8'), $kw) !== false
                || mb_strpos(mb_strtolower((string)$p['common_name'], 'UTF-8'), $kw) !== false
                || mb_strpos(mb_strtolower($p['brand'], 'UTF-8'), $kw) !== false
                || mb_strpos(mb_strtolower($p['series'], 'UTF-8'), $kw) !== false;
        }
        if (!$hitFilter) continue;
        if ($filter === 'missing_brand' && !in_array('missing_brand', $flags, true)) continue;
        if ($filter === 'missing_series' && !in_array('missing_series', $flags, true)) continue;
        if ($filter === 'mismatch' && !array_intersect(['brand_mismatch', 'series_mismatch', 'series_variant', 'multi_candidate'], $flags)) continue;
        if ($filter === 'no_source' && !in_array('no_source', $flags, true)) continue;
        if ($filter === 'all' && !$bad) continue; // 默认只看有问题的行
        $problems[] = $row;
    }

    // 分组只保留「同系列存在 ≥2 种当前写法」的
    foreach ($groupMap as &$g) {
        $seen = [];
        foreach ($g['products'] as $pp) {
            $s = trim((string)$pp['series']);
            if ($s === '') continue;
            $seen[pmNorm($s)] = $s; // 按实际文字归组：只差系列字/空格也能进“同系列写法不一致”
        }
        $g['variants'] = array_values($seen);
        if (count($g['variants']) < 2) continue;
        $g['products'] = array_values($g['products']);
        $groups[] = $g;
    }
    unset($g);
    usort($groups, fn($a, $b) => count($b['products']) - count($a['products']) ?: strcmp($a['series'], $b['series']));

    success([
        'store'      => ['id' => (int)$storeId, 'name' => (string)$storeName->fetchColumn()],
        'catalog'    => ['updated_at' => pmCatalogUpdatedAt(), 'ips' => count($cat['ips'] ?? []), 'items' => count($cat['items'] ?? [])],
        'stats'      => $stats,
        'problems'   => $problems,
        'groups'     => $groups,
        'datalist'   => ['brands' => array_values($dlBrands), 'series' => array_values($dlSeries)],
    ]);
} catch (Exception $e) {
    logError($e->getMessage(), 'product_meta_scan', ['store_id' => $storeId ?? null]);
    error('扫描失败: ' . $e->getMessage(), 500);
}
