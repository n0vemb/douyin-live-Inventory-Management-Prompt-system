<?php
/**
 * product_meta_candidates.php — 单商品候选：ysjp 目录 + 千岛兜底
 * POST { product_id, use_qiandao?: 0|1 }
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/product_meta_lib.php';

$pdo = getDB();
requireAuth();
$storeId = getStoreId();
if (!$storeId) error('请先选择店铺');
if (!in_array($_SESSION['role'] ?? '', ['store_admin', 'super_admin'], true)) error('无权限', 403);

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$productId = (int)($input['product_id'] ?? 0);
if (!$productId) error('缺少商品ID');
$useQiandao = !empty($input['use_qiandao']);

try {
    $cat = pmLoadCatalog();
    if (!$cat) error('ysjp 目录数据缺失');
    $stmt = $pdo->prepare("SELECT id, name, common_name, COALESCE(brand,'') brand, COALESCE(series,'') series, image_url
                           FROM products WHERE id = ? AND store_id = ?");
    $stmt->execute([$productId, $storeId]);
    $p = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$p) error('商品不存在或不属于当前店铺');

    $m = pmMatchProduct($p, $cat);
    $ysjpCands = [];
    foreach ($m['cands'] as $c) {
        $c['source'] = 'ysjp';
        $ysjpCands[] = $c;
    }
    $qiandaoCands = [];
    $qiandaoError = '';
    if ($useQiandao) {
        try {
            $items = [];
            foreach (pmQiandaoQueryTerms($p['name']) as $term) {
                $items = pmQiandaoSearch($term, 10);
                if ($items) break;
            }
            $qiandaoCands = pmQiandaoToCands($items, $cat);
        } catch (Exception $e) {
            $qiandaoError = $e->getMessage();
        }
    }

    success([
        'product'   => [
            'id' => (int)$p['id'], 'name' => $p['name'], 'common_name' => $p['common_name'] ?: '',
            'brand' => $p['brand'], 'series' => $p['series'], 'image_url' => $p['image_url'] ?: '',
        ],
        'matched'   => $m['matched'],
        'unique'    => $m['unique'],
        'ysjp_cands'  => $ysjpCands,
        'qiandao_cands' => $qiandaoCands,
        'qiandao_error' => $qiandaoError,
    ]);
} catch (Exception $e) {
    logError($e->getMessage(), 'product_meta_candidates', ['product_id' => $productId]);
    error('查询候选失败: ' . $e->getMessage(), 500);
}
