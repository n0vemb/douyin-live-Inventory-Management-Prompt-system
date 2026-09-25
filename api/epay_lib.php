<?php
/**
 * epay_lib.php — 易支付（epay）签名 / 验签 / 请求封装
 *
 * 协议（平台文档）：参数按 ASCII 排序后拼成 key=value&... 链（去掉 sign、sign_type、
 * 以及空值），末尾直接拼商户密钥，MD5 小写即签名。
 *   下单   POST {api_url}/mapi.php   （API 模式，返回二维码链接）
 *          POST {api_url}/submit.php （页面跳转模式，浏览器跳收银台）
 *   回调   GET  notify_url / return_url，参数含 trade_status，TRADE_SUCCESS 才算成功
 *   查询   POST {api_url}/api/findorder —— 本平台实测不可用（返回「不是有效订单号」），
 *          故一律以异步回调为准，不做主动查询兜底。
 *   实测   下单接口(含 submit.php)耗时极不稳定（不是限流，也不是我们的参数问题）：
 *          静置后首次约 0.6s 返回，连续请求 15~30s 超时都可能；
 *          也遇到过第 4 次尝试 14.8s 才成功。属平台/通道侧问题——对照实验里换 UA、换中英文商品名、
 *          换金额、换裸域、换签名对错，表现都一致，与我们的请求参数无关。
 *          故调用侧要控频、超时给足(≥30s)、失败不要立刻重试。
 *          ⚠ 超时后不要直接换 out_trade_no 重开单：平台侧可能已建单，回调会带着旧单号回来。
 *          www.ezfpy.cn 与 ezfpy.cn 指向同一 IP，表现一致。
 *
 *   查单补充 文档的 POST {api_url}/api/findorder（order_no + type=1商户单号/2平台单号）实测恒返回
 *          「此订单号不是有效订单号」——带不带 pid/sign、GET/POST、换任意真实单号都一样；
 *          官方 PHP demo 里的老式查单 GET {api_url}/api.php?act=order&pid=&key=&out_trade_no=
 *          该路径已 404 下线。两条查单路径都不可用，只能靠异步回调。
 *
 *   通道   平台帮助中心：付款到账但平台显示未支付/回调失败，多为通道密钥(RSA2 公钥/私钥)多打或
 *          漏打一个空格所致（平台侧配置问题，与我们接口无关）；「支付宝个人版」通道用的是商户
 *          本人支付宝账号，需扫码授权且保持在线，通道掉线时下单会长时间无响应。
 *
 * ⚠ 联调用途：正式接入收银台前不要把本文件用于真实收款。
 */

/** 联调配置（单行表 id=1） */
function epayTestConfig(PDO $pdo, $createIfMissing = false) {
    $row = $pdo->query('SELECT * FROM epay_test_config WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
    if ($row) return $row;
    if ($createIfMissing) {
        $pdo->exec("INSERT INTO epay_test_config (id, api_url, pid, mch_key) VALUES (1, 'https://www.ezfpy.cn', '', '')");
    }
    return ['id' => 1, 'api_url' => 'https://www.ezfpy.cn', 'pid' => '', 'mch_key' => '', 'sign_type' => 'MD5', 'enabled' => 0];
}

/**
 * 易支付签名。
 * @param array  $params 参与签名的参数（sign/sign_type 由本函数自动剔除）
 * @param string $key    商户密钥
 */
function epaySign(array $params, $key) {
    $pairs = [];
    foreach ($params as $k => $v) {
        if ($k === 'sign' || $k === 'sign_type') continue;
        if ($v === null || $v === '') continue;
        if (is_array($v)) continue;
        $pairs[$k] = (string)$v;
    }
    ksort($pairs, SORT_STRING);
    $parts = [];
    foreach ($pairs as $k => $v) $parts[] = $k . '=' . $v;
    return strtolower(md5(implode('&', $parts) . $key));
}

/** 回调验签：参数来自 $_GET/$_POST 原样 */
function epayVerify(array $params, $key) {
    $given = (string)($params['sign'] ?? '');
    if ($given === '') return false;
    return hash_equals(epaySign($params, $key), strtolower($given));
}

/** 金额格式化：签的字符串必须和发出去的一模一样 */
function epayMoney($amount) {
    return number_format((float)$amount, 2, '.', '');
}

/** GET，返回 [httpCode, body, error] */
function epayHttpGet($url, $timeout = 10) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148',
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($body === false) return [0, '', $err ?: 'curl 请求失败'];
    return [$code, (string)$body, ''];
}

/** POST 表单（application/x-www-form-urlencoded），返回 [httpCode, body, error] */
function epayHttpPost($url, array $params, $timeout = 20) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($params),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_USERAGENT => 'PPMart-Epay-Test/1.0',
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($body === false) return [0, '', $err ?: 'curl 请求失败'];
    return [$code, (string)$body, ''];
}

/** 写联调日志（失败不阻断主流程） */
function epayTestLog(PDO $pdo, $outTradeNo, $direction, $endpoint, $httpCode, $ok, $note, $payload) {
    try {
        if (is_array($payload)) $payload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (is_string($payload) && strlen($payload) > 20000) $payload = substr($payload, 0, 20000) . '...(截断)';
        $stmt = $pdo->prepare(
            'INSERT INTO epay_test_logs (out_trade_no, direction, endpoint, http_code, ok, note, payload)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $outTradeNo !== '' ? (string)$outTradeNo : null,
            $direction === 'in' ? 'in' : 'out',
            $endpoint !== '' ? mb_substr((string)$endpoint, 0, 255) : null,
            $httpCode !== null ? (int)$httpCode : null,
            $ok ? 1 : 0,
            (string)$note !== '' ? mb_substr((string)$note, 0, 255) : null,
            $payload === '' ? null : $payload,
        ]);
    } catch (Exception $e) {
        // 日志失败不影响业务
    }
}

/** 生成商户订单号：EP + 时间 + 随机 */
function epayNewOutTradeNo() {
    return 'EP' . date('ymdHis') . str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
}

// ============================================================
// 收银台正式接入（店铺级配置）
// ============================================================

/** 站点自身绝对地址（拼 notify_url / return_url 用） */
function epaySelfUrl($path) {
    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    if ($host === '') $host = 'localhost';
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    return ($https ? 'https://' : 'http://') . $host . '/' . ltrim((string)$path, '/');
}

/**
 * 收银台易支付配置（按店铺读 stores）。
 * mode=epay 才走易支付；ready 表示商户号与密钥都填了。
 */
function epayStoreConfig(PDO $pdo, $storeId) {
    $stmt = $pdo->prepare(
        'SELECT id, name, pos_pay_mode, epay_api_url, epay_pid, epay_mch_key, epay_sign_type FROM stores WHERE id = ?'
    );
    $stmt->execute([(int)$storeId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $apiUrl = rtrim(trim((string)($row['epay_api_url'] ?? '')), '/');
    $pid = trim((string)($row['epay_pid'] ?? ''));
    $key = trim((string)($row['epay_mch_key'] ?? ''));
    return [
        'store_id'   => (int)$storeId,
        'store_name' => (string)($row['name'] ?? ''),
        'mode'       => (($row['pos_pay_mode'] ?? 'static') === 'epay') ? 'epay' : 'static',
        'api_url'    => $apiUrl !== '' ? $apiUrl : 'https://www.ezfpy.cn',
        'pid'        => $pid,
        'mch_key'    => $key,
        'sign_type'  => 'MD5',
        'ready'      => ($pid !== '' && $key !== ''),
    ];
}

/**
 * 店铺静态收款码（门店覆盖优先），返回绝对 URL 的 ['wx' => .., 'ali' => ..]。
 *
 * 收银台页面在加载时就把收款方式写进了 HTML：后台把「易支付」切回「静态收款码」后，
 * 已经打开的页面仍会来要易支付码，这里把静态码一并回传，让它就地切成静态码流程。
 */
function epayStaticQrUrls(PDO $pdo, $storeId, $shopId = 0) {
    $out = ['wx' => '', 'ali' => ''];
    try {
        $stmt = $pdo->prepare(
            "SELECT COALESCE(NULLIF(sh.offline_pay_qr_wx, ''), s.offline_pay_qr_wx) AS qr_wx,
                    COALESCE(NULLIF(sh.offline_pay_qr_ali, ''), s.offline_pay_qr_ali) AS qr_ali
             FROM stores s LEFT JOIN shops sh ON sh.id = ?
             WHERE s.id = ?"
        );
        $stmt->execute([(int)$shopId, (int)$storeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        foreach (['wx' => 'qr_wx', 'ali' => 'qr_ali'] as $k => $col) {
            $path = trim((string)($row[$col] ?? ''));
            if ($path === '') continue;
            $out[$k] = preg_match('#^https?://#i', $path) ? $path : epaySelfUrl($path);
        }
    } catch (Exception $e) {
        // 查询失败不影响主流程：前端拿不到静态码会退回原提示
    }
    return $out;
}

/** 收银台支付方式（wechat/alipay）→ 易支付 type */
function epayPayType($method) {
    if ($method === 'alipay') return 'alipay';
    if ($method === 'wechat' || $method === 'wxpay') return 'wxpay';
    return '';
}

/** 正式收银台的出入站日志（epay_logs）；日志失败不影响业务 */
function epayLog(PDO $pdo, $storeId, $outTradeNo, $direction, $endpoint, $httpCode, $ok, $note, $payload = '') {
    try {
        if (is_array($payload)) $payload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (is_string($payload) && strlen($payload) > 20000) $payload = substr($payload, 0, 20000) . '...(截断)';
        $stmt = $pdo->prepare(
            'INSERT INTO epay_logs (store_id, out_trade_no, direction, endpoint, http_code, ok, note, payload)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $storeId ? (int)$storeId : null,
            $outTradeNo !== '' ? (string)$outTradeNo : null,
            $direction === 'in' ? 'in' : 'out',
            $endpoint !== '' ? mb_substr((string)$endpoint, 0, 255) : null,
            $httpCode !== null ? (int)$httpCode : null,
            $ok ? 1 : 0,
            (string)$note !== '' ? mb_substr((string)$note, 0, 255) : null,
            $payload === '' ? null : $payload,
        ]);
    } catch (Exception $e) {
        // 忽略：日志表未迁移或写失败都不应影响收款
    }
}

/** 易支付下单响应里的业务码：平台实测成功返回 1，文档写 200，两个都认 */
function epayRespOk($json) {
    return is_array($json) && in_array((int)($json['code'] ?? 0), [1, 200], true);
}

/**
 * 把平台给的收款码变成「一张真实可访问的 PNG 地址」。
 *
 * 微信通道只给 wxp:// 码串（没有可点链接），前端本地渲染成 data:URL 的话，
 * 微信内置浏览器长按不会出现「识别图中二维码」——微信只认能重新抓取到的网络图片。
 * 所以这里由服务端出图并缓存到 data/pos_qr/，前端拿到的就是自己域名下的 png。
 *
 * 返回可直接放进 <img src> 的绝对地址；出图失败返回 ''（前端退回本地渲染）。
 */
function epayQrImageUrl($code) {
    $code = trim((string)$code);
    if ($code === '') return '';
    // 只处理「码串」这种没有链接的：http 开头的那些是付款链接（支付宝），由收银台当按钮用
    if (preg_match('#^https?://#i', $code)) return '';

    $rel = 'data/pos_qr/' . md5($code) . '.png';
    $abs = dirname(__DIR__) . '/' . $rel;
    if (!is_file($abs) || filesize($abs) < 100) {
        $src = 'https://minico.qq.com/qrcode/get?type=2&r=2&size=350&text=' . rawurlencode($code);
        list($httpCode, $body, $err) = epayHttpGet($src, 10);
        // 只接受真正的 PNG：失败时别把错误页当成图缓存下来，否则以后一直是坏图
        if ($err !== '' || $httpCode !== 200 || strncmp($body, "\x89PNG", 4) !== 0) return '';
        $dir = dirname($abs);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return '';
        $tmp = $abs . '.' . getmypid() . '.tmp';
        if (@file_put_contents($tmp, $body) === false) return '';
        @chmod($tmp, 0644);
        if (!@rename($tmp, $abs)) { @unlink($tmp); return ''; }
    }
    return epaySelfUrl($rel);
}
