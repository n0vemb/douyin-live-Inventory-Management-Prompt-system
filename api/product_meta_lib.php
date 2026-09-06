<?php
/**
 * product_meta_lib.php — 商品品牌/IP · 系列完善工具的共享逻辑
 * 数据源：
 *   1) ysjp（ysjp.xyz/edf）目录快照：data/product_meta_catalog.json
 *      （items：娃名 → ip + ysjp 原始系列 + 规范系列「去IP前缀保留代次」）
 *   2) 千岛搜索（api.qiandao.com），仅作为 ysjp 无匹配时的兜底候选
 */

function pmCatalogPath() {
    return __DIR__ . '/../data/product_meta_catalog.json';
}

/** 加载 ysjp 目录（每进程缓存） */
function pmLoadCatalog() {
    static $cat = null;
    if ($cat !== null) return $cat;
    $path = pmCatalogPath();
    if (!is_file($path)) return null;
    $cat = json_decode((string)file_get_contents($path), true);
    if (!is_array($cat)) { $cat = []; return null; }
    // 名称索引：原样名 + 规范化名 → 候选列表 [{ip,ip_name,series,src}]
    if (empty($cat['__idx'])) {
        $idx = [];
        $seriesIdx = [];
        foreach (($cat['items'] ?? []) as $it) {
            $n = (string)($it['n'] ?? '');
            if ($n === '') continue;
            $ip = (string)($it['ip'] ?? '');
            $ipName = (string)($cat['ips'][$ip]['name'] ?? $ip);
            $c = [
                'ip'       => $ip,
                'ip_name'  => $ipName,
                'series'   => (string)($it['s'] ?? ''),
                'src'      => (string)($it['src'] ?? ''),
            ];
            $idx['raw'][$n][] = $c;
            $nn = pmNorm($n);
            if ($nn !== '') $idx['norm'][$nn][] = $c;
        }
        // 系列级索引：按 ysjp 规范系列名回查（供商品名未匹配时兜底）
        foreach (($cat['ips'] ?? []) as $ip => $iv) {
            foreach (($iv['series'] ?? []) as $src => $disp) {
                $seriesIdx[] = [
                    'ip'      => $ip,
                    'ip_name' => (string)($iv['name'] ?? $ip),
                    'src'     => $src,
                    'series'  => $disp,
                ];
            }
        }
        $cat['__idx'] = $idx;
        $cat['__series'] = $seriesIdx;
    }
    return $cat;
}

function pmCatalogUpdatedAt() {
    $cat = pmLoadCatalog();
    return (string)($cat['updated_at'] ?? '');
}

/** 宽松规范化：NFKC + 小写 + 去空白/常见标点/连字符 */
function pmNorm($s) {
    $s = mb_strtolower((string)$s, 'UTF-8');
    $s = strtr($s, ['×' => 'x', '✕' => 'x', '＊' => '*', 'Ｏ' => 'o']);
    $s = preg_replace('/dimo0/u', 'dimoo', $s); // 常见手误：DIMO0 WORLD
    $s = preg_replace('/[\s\-_－—、，,。.！!？?：:；;（）()【】\[\]『』「」\'\"`·・\/\\\\]+/u', '', $s);
    return $s;
}

/** 娃名标准化：去掉「冰箱贴/挂件」等周边前缀与隐藏款标记（同补图工具口径） */
function pmNormItemName($n) {
    $n = trim((string)$n);
    $n = str_replace(['（', '）'], ['(', ')'], $n);
    $n = preg_replace('/\(隐藏款\)/u', '', $n);
    $n = preg_replace('/\(大隐藏款\)/u', '', $n);
    $n = preg_replace('/\(小隐藏款\)/u', '', $n);
    $n = preg_replace('/^(冰箱贴夹子|冰箱贴|手机挂绳|手机支架|钥匙挂件|数据线挂件|卡套|零钱包|香薰挂件|挂件|洞洞装饰扣|装饰扣|萌粒)[\-－—]?/u', '', $n);
    $n = str_replace('特别款', '', $n);
    return trim($n);
}

function pmCandidateKey($c) {
    return $c['ip'] . '|' . $c['src'] . '|' . $c['series'];
}

function pmDedupCands($cands) {
    $out = []; $seen = [];
    foreach ($cands as $c) {
        $k = pmCandidateKey($c);
        if (isset($seen[$k])) continue;
        $seen[$k] = 1;
        $out[] = $c;
    }
    return $out;
}

/** 名称匹配：原样精确 → 规范化精确 → 双向包含（模糊） */
function pmMatchByName($name, $cat) {
    $raw = trim((string)$name);
    $stripped = pmNormItemName($raw);
    $idx = $cat['__idx'] ?? [];
    $cands = [];
    foreach (array_unique([$raw, $stripped]) as $n) {
        if ($n === '') continue;
        if (!empty($idx['raw'][$n])) {
            $cands = array_merge($cands, $idx['raw'][$n]);
            break;
        }
    }
    if (!$cands) {
        foreach (array_unique([$raw, $stripped]) as $n) {
            $nn = pmNorm($n);
            if ($nn === '') continue;
            if (!empty($idx['norm'][$nn])) {
                $cands = array_merge($cands, $idx['norm'][$nn]);
                break;
            }
        }
    }
    if (!$cands && $raw !== '') {
        $nn = pmNorm($raw);
        if ($nn !== '') {
            foreach (($idx['norm'] ?? []) as $kn => $lst) {
                if ($kn === $nn) continue;
                $pos = mb_stripos($kn, $nn);
                $rev = $pos === false ? mb_stripos($nn, $kn) : -1;
                if ($pos !== false) {
                    // 目录名比商品名长（商品名是目录名的子串）
                    $cands = array_merge($cands, $lst);
                } elseif ($rev !== false) {
                    // 商品名比目录名长：排除“二代鸟人”→“鸟人”这类带代次前缀的误配
                    $before = mb_substr($nn, 0, $rev);
                    $after  = mb_substr($nn, $rev + mb_strlen($kn));
                    if ($before !== '' && preg_match('/(第?[\d一二三四五六七八九十百]+代)$/u', $before)) continue;
                    if ($after !== '' && preg_match('/^(第?[\d一二三四五六七八九十百]+代)/u', $after)) continue;
                    $cands = array_merge($cands, $lst);
                }
            }
        }
    }
    return pmDedupCands($cands);
}

/** 系列核心（比较用）：取到“系列”字前 + 去掉代次/周边类型词 */
function pmSeriesCore($s) {
    $s = pmNorm((string)$s);
    $s = strtr($s, ['小王子' => 'lepetitprince']); // Hirono × Le Petit Prince 中英写法互通
    $pos = mb_strpos($s, '系列');
    if ($pos !== false) $s = mb_substr($s, 0, $pos);
    // 去掉代次表达：第10代 / 10代 / 第一代 / 一代 / 初代 都视为同一代
    $s = preg_replace('/第?\s*[\d一二三四五六七八九十百]+代|初代/u', '', $s);
    $s = preg_replace('/(套装|盒子?|手办|盲盒|周边|萌粒|冰箱贴|夹子|挂件|吊卡|卡套|挂绳|数据线|挂链|支架|摇摇乐|徽章|生日牌)$/u', '', $s);
    return trim($s);
}

/** 系列名宽松比较：核心互相包含即视为同一系列 */
function pmSeriesSimilar($a, $b) {
    $na = pmSeriesCore($a);
    $nb = pmSeriesCore($b);
    if ($na === '' || $nb === '') return false;
    return $na === $nb || mb_stripos($na, $nb) !== false || mb_stripos($nb, $na) !== false;
}

/** 系列名等价：去掉末尾「系列/套装/盒」等类型词后完全一致才算同一写法
 *  例：第7代 慢下来 == 第7代 慢下来系列（不再提示）
 *      自在生长系列 != 第2代 自在生长系列（代次缺失仍提示统一）
 */
function pmSeriesSameText($a, $b) {
    $na = pmSeriesSameKey($a);
    $nb = pmSeriesSameKey($b);
    return $na !== '' && $na === $nb;
}

/** 系列等价比对键：去掉末尾类型词（系列/套装/盒） */
function pmSeriesSameKey($s) {
    $s = pmNorm((string)$s);
    return trim((string)preg_replace('/(系列|套装|盒子?)$/u', '', $s));
}

/** 商品名没匹配到时，按「当前系列名」回查 ysjp 系列目录 */
function pmSeriesCandidatesFor($p, $cat) {
    $series = trim((string)($p['series'] ?? ''));
    if ($series === '') return [];
    $all = $cat['__series'] ?? [];
    if (!$all) return [];

    $brandNorm = pmNorm(trim((string)($p['brand'] ?? '')));
    $restrictIp = '';
    foreach (($cat['ips'] ?? []) as $ip => $iv) {
        if (pmNorm((string)($iv['name'] ?? '')) === $brandNorm && $brandNorm !== '') {
            $restrictIp = $ip;
            break;
        }
    }
    $out = [];
    foreach ($all as $c) {
        if ($restrictIp !== '' && $c['ip'] !== $restrictIp) continue;
        $same = pmSeriesSameText($series, $c['series']);
        $sim  = !$same && pmSeriesSimilar($series, $c['series']);
        $core = pmSeriesCore($series);
        $needle = $core === '' ? pmNorm($series) : $core;
        $strong = $same || ($sim && mb_strlen($needle) >= 2);
        if (!$strong) continue;
        $out[] = $c;
    }
    // 多命中按“系列字面相同 → 代次/核心最相似”排序
    usort($out, function ($a, $b) use ($series) {
        $sa = pmSeriesSameText($series, $a['series']) ? 2 : (pmSeriesSimilar($series, $a['series']) ? 1 : 0);
        $sb = pmSeriesSameText($series, $b['series']) ? 2 : (pmSeriesSimilar($series, $b['series']) ? 1 : 0);
        if ($sa !== $sb) return $sb - $sa;
        return strlen($a['series']) <=> strlen($b['series']);
    });
    return pmDedupCands(array_slice($out, 0, 5));
}

/**
 * 单商品匹配：名称 → 候选；再用当前品牌/系列消歧。
 * @return array{matched:bool,cands:array,unique:bool}
 */
function pmMatchProduct($p, $cat) {
    $cands = pmMatchByName((string)($p['name'] ?? ''), $cat);
    // 商品名匹配不到时，用当前系列名回查目录（同源系列仍能识别）
    if (!$cands) $cands = pmSeriesCandidatesFor($p, $cat);
    // 商品名命中多个系列但都与当前系列对不上（如“蘑菇”→DIMOO/PUCKY）：
    // 再用系列名回查补上正确候选，交给下方系列消歧
    if ($cands && count($cands) > 1 && trim((string)($p['series'] ?? '')) !== '') {
        $series = trim((string)$p['series']);
        $nameCandsMatchSeries = false;
        foreach ($cands as $c) {
            if (pmSeriesSimilar($series, $c['series']) || pmSeriesSameText($series, $c['series'])) {
                $nameCandsMatchSeries = true;
                break;
            }
        }
        if (!$nameCandsMatchSeries) {
            $extra = pmSeriesCandidatesFor($p, $cat);
            if ($extra) $cands = array_merge($cands, $extra);
        }
    }
    if (!$cands) return ['matched' => false, 'cands' => [], 'unique' => false];

    // 多候选：先用当前品牌（IP 名）消歧
    $brand = trim((string)($p['brand'] ?? ''));
    if (count($cands) > 1 && $brand !== '') {
        $bn = pmNorm($brand);
        $filt = array_values(array_filter($cands, fn($c) => pmNorm($c['ip_name']) === $bn));
        if ($filt) $cands = $filt;
    }
    // 仍多：用当前系列消歧
    $series = trim((string)($p['series'] ?? ''));
    if (count($cands) > 1 && $series !== '') {
        $filt = array_values(array_filter($cands, fn($c) =>
            pmSeriesSimilar($c['series'], $series) || pmSeriesSimilar($c['src'], $series)));
        if ($filt) $cands = $filt;
    }
    $cands = pmDedupCands($cands);
    return ['matched' => true, 'cands' => $cands, 'unique' => count($cands) === 1];
}

/** 品牌（IP 名）是否与候选一致（宽松，忽略大小写与空格） */
function pmBrandMatches($cur, $cand) {
    $cur = trim((string)$cur);
    if ($cur === '' || empty($cand['ip_name'])) return false;
    return pmNorm($cur) === pmNorm($cand['ip_name']);
}

/* ---------------- 千岛兜底 ---------------- */

/** 千岛 echotechoss:// 图片协议 → 可访问 CDN 地址 */
function pmQiandaoImageUrl($img) {
    $img = (string)$img;
    if (strpos($img, 'echotechoss://user-treasure-v2.image/') === 0) {
        return 'https://treasure.qiandaocdn.com/treasure/images/' . substr($img, strlen('echotechoss://user-treasure-v2.image/'));
    }
    if (strpos($img, 'echotechoss://interior-admin-v2.image/') === 0) {
        return 'https://public.qiandaocdn.com/interior/images/' . substr($img, strlen('echotechoss://interior-admin-v2.image/'));
    }
    if (strpos($img, 'http') === 0) return $img;
    return '';
}

/** 千岛 SPU 搜索（返回候选原始 items） */
function pmQiandaoSearch($q, $max = 8) {
    $url = 'https://api.qiandao.com/plast/search/spu?' . http_build_query([
        'q' => $q, 'start-index' => 0, 'max-results' => min(10, max(1, $max)),
        'origin' => 'history', 'scene' => 'qiandao_web', 'channelId' => '',
    ]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => [
            'Referer: https://www.qiandao.com/',
            'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
        ],
    ]);
    $out = curl_exec($ch);
    if ($out === false) return [];
    $j = json_decode((string)$out, true);
    return (array)($j['data']['items'] ?? []);
}

/**
 * 千岛搜索结果解析成建议候选。
 * key_property 形如「当我三岁时系列 / Molly / 手办 / 盲盒」→ 系列=第0段，IP=第1段
 */
function pmQiandaoToCands($items, $cat) {
    // 千岛 IP 写法别名 → ysjp IP slug（其余名称若与 ysjp IP 名大小写一致也自动命中）
    $ipAlias = [
        'hirono'    => ['hirono小野', '小野 hirono', '小野'],
        'sweetbean' => ['小甜豆'],
        'labubu'    => ['the monsters'],
        'molly'     => ['molly娃娃'],
    ];
    $ipSlug = [];
    foreach (($cat['ips'] ?? []) as $slug => $iv) {
        $ipSlug[pmNorm($iv['name'] ?? '')] = $slug;
    }
    $cands = [];
    foreach ($items as $it) {
        $name = trim((string)($it['name'] ?? ''));
        if ($name === '') continue;
        $kp = array_values(array_filter(array_map('trim', explode('/', (string)($it['key_property'] ?? '')))));
        // 找 IP 段：千岛可能放在第 0/1 段，先匹配已知 IP 名/别名
        $ipSlugFound = '';
        $ipPos = -1;
        foreach ($kp as $si => $seg) {
            $sn = pmNorm($seg);
            if (isset($ipSlug[$sn])) { $ipSlugFound = $ipSlug[$sn]; $ipPos = $si; break; }
            foreach ($ipAlias as $slug => $als) {
                foreach ($als as $al) {
                    if ($sn === pmNorm($al)) { $ipSlugFound = $slug; $ipPos = $si; break 2; }
                }
            }
            if ($ipSlugFound !== '') break;
        }
        $ipName = $ipSlugFound !== '' ? (string)($cat['ips'][$ipSlugFound]['name'] ?? '') : '';

        // 系列：优先取 item 名（千岛系列 SPU 时 name 即系列），否则取 IP 段前的描述
        $series = '';
        if (mb_strpos($name, '系列') !== false) {
            $series = $name;
        } else {
            $pre = $ipPos >= 0 ? array_slice($kp, 0, $ipPos) : array_slice($kp, 0, 1);
            $series = implode('', $pre);
        }
        // 清理：系列字后不再要类型词（如「系列手办」/「系列 二合一数据线」）
        if (mb_strpos($series, '系列') !== false) {
            $series = mb_substr($series, 0, mb_strpos($series, '系列') + 2);
        }
        $series = trim((string)preg_replace('/(手办|盲盒|周边|数据线|挂链|挂绳|挂件|徽章|冰箱贴|摇摇乐|夹子)$/u', '', trim($series)), ' -');
        if ($series === '' && mb_strpos($name, '系列') === false) $series = $name;
        $cands[] = [
            'ip'       => '',
            'ip_name'  => $ipName,
            'series'   => $series,
            'src'      => '',
            'source'   => 'qiandao',
            'name'     => $name,
            'image'    => pmQiandaoImageUrl((string)($it['image'] ?? '')),
        ];
    }
    return pmDedupCands(array_values(array_filter($cands, fn($c) => $c['series'] !== '' || $c['ip_name'] !== '')));
}

/** 千岛搜索词：去掉周边前缀，原始名兜底 */
function pmQiandaoQueryTerms($name) {
    $n = preg_replace('/^(冰箱贴夹子|冰箱贴|手机挂绳|手机支架|钥匙挂件|数据线挂件|卡套|零钱包|香薰挂件|挂件|洞洞装饰扣|装饰扣)[\-－—]/u', '', $name);
    $terms = $n === $name ? [$name] : [$n, $name];
    return array_values(array_unique(array_filter(array_map('trim', $terms))));
}
