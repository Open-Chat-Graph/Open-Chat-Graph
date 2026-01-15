<?php
declare(strict_types=1);

/**
 * LINE公式API モックサーバー（固定データ版）
 *
 * カテゴリ別クローリングテスト専用のモックAPI（CI高速テスト用）
 *
 * ## テスト仕様
 *
 * ### データ件数
 * - 各カテゴリごとに急上昇:80件、ランキング:80件を返す（固定）
 * - 日本語: 24時間分のテストデータ（23:30開始、翌23:30まで）
 * - 日本語以外（繁体字/タイ語）: 1時間分のテストデータ
 *
 * ### ルーム構成（hourIndex=0の場合）
 *
 * #### カテゴリ0（すべて/全部/ทั้งหมด）
 * - 急上昇のみルーム: 16件（1時間目のみ、2時間目以降は消える）
 * - 変動ルーム: 8件（内容・人数・順位が時間経過で変化）
 * - 静的ルーム: 56件（hourIndex=0で完全固定）
 *
 * #### カテゴリ1以降
 * - 人数固定ルーム: 16件（1時間目のみ出現、2時間目以降は消える）
 *   - メンバー数5: 8件
 *   - メンバー数10: 4件
 *   - メンバー数20: 4件
 * - 変動ルーム: 8件（内容・人数・順位が時間経過で変化）
 * - 静的ルーム: 56件（hourIndex=0で完全固定）
 *
 * ### ルーム構成（hourIndex>=1の場合）
 * - 新規ルーム: 1件（hourIndexベース、人数10固定、タイトル: 【新規】）
 * - 変動ルーム: 8件（hourIndex=0ベース、人数・順位が決定論的に変化、タイトル: 【変動】）
 * - 静的ルーム: 71件（hourIndex=0で完全固定、内容・人数不変）
 *
 * ### ルームIDの生成ルール
 * - カテゴリID + 時間 + ルームインデックスで決定的にEMIDを生成
 * - 同じ条件では常に同じEMIDが生成される（再現性）
 */

// メモリ制限を上げる
ini_set('memory_limit', '2G');

// hourIndexをファイルから読み込む（test-category-crawl.shが書き込む）
$hourIndexFile = '/app/data/hour_index.txt';
if (file_exists($hourIndexFile)) {
    $hourIndex = (int)trim(file_get_contents($hourIndexFile));
} else {
    $hourIndex = 0;
}

$currentTime = time();

// 人気タグ（10種類をハードコード）
$popularTags = [
    'ja' => [
        'ポケモン', 'スプラトゥーン', 'フォートナイト', 'APEX', 'プロ野球',
        'ダイエット', 'メイク', 'K-POP', 'アニメ', 'カフェ'
    ],
    'tw' => [
        '寶可夢', '動森', '原神', '英雄聯盟', '音樂', '電影', '美食',
        '流行', '旅遊', '健康'
    ],
    'th' => [
        'เกม', 'ROV', 'PUBG', 'เพลง', 'K-POP', 'ท่องเที่ยว',
        'อาหาร', 'กีฬา', 'อนิเมะ', 'การถ่ายภาพ'
    ]
];

// リクエストヘッダーから言語を判定
function getLanguageFromHeaders(): string
{
    $headers = getallheaders();
    if (isset($headers['x-lal'])) {
        $lang = strtolower($headers['x-lal']);
        return in_array($lang, ['tw', 'th']) ? $lang : 'ja';
    }
    return 'ja';
}

$language = getLanguageFromHeaders();

// 言語別のカテゴリID配列
function getCategoryIds(string $language): array
{
    return match ($language) {
        'tw' => [0, 17, 42, 35, 20, 11, 18, 6, 14, 4, 23, 43, 12, 40, 16, 2, 44, 5, 22, 27, 34],
        'th' => [0, 17, 33, 10, 18, 28, 16, 14, 34, 2, 8, 22, 12, 19, 27, 40, 37, 11, 35, 30, 20, 24],
        default => [0, 17, 16, 26, 7, 22, 40, 33, 8, 20, 11, 5, 2, 12, 23, 6, 28, 19, 18, 27, 37, 30, 29, 24, 41],
    };
}

// カテゴリ名を取得
function getCategoryName(int $categoryId, string $language): string
{
    $names = [
        'ja' => [
            0 => 'すべて', 17 => 'ゲーム', 16 => 'スポーツ', 26 => '芸能人・有名人',
            7 => '同世代', 22 => 'アニメ・漫画', 40 => '金融・ビジネス', 33 => '音楽',
            8 => '地域・暮らし', 20 => 'ファッション・美容', 11 => '研究・学習', 5 => '働き方・仕事',
            2 => '学校・同窓会', 12 => '料理・グルメ', 23 => '健康', 6 => '団体',
            28 => '妊活・子育て', 19 => '乗り物', 18 => '旅行', 27 => '動物・ペット',
            37 => '写真', 30 => '映画・舞台', 29 => '本', 24 => 'TV・VOD', 41 => 'イラスト',
        ],
        'tw' => [
            0 => '全部', 17 => '遊戲', 42 => '娛樂', 35 => '其他', 20 => '流行／美妝',
            11 => '學習', 18 => '旅遊', 6 => '團體／組織', 14 => '興趣', 4 => '家庭／親子',
            23 => '健康', 43 => '心情', 12 => '美食', 40 => '金融／商業', 16 => '運動／健身',
            2 => '學校／校友', 44 => '工作', 5 => '公司／企業', 22 => '動畫／漫畫', 27 => '寵物', 34 => '科技',
        ],
        'th' => [
            0 => 'ทั้งหมด', 17 => 'เกม', 33 => 'เพลง', 10 => 'แฟนคลับ', 18 => 'ท่องเที่ยว',
            28 => 'เด็ก', 16 => 'กีฬา', 14 => 'งานอดิเรก', 34 => 'เทคโนโลยี', 2 => 'โรงเรียน',
            8 => 'ท้องถิ่น', 22 => 'อนิเมะ & การ์ตูน', 12 => 'อาหาร', 19 => 'รถยนต์', 27 => 'สัตว์เลี้ยง',
            40 => 'การเงิน & ธุรกิจ', 37 => 'การถ่ายภาพ', 11 => 'การศึกษา', 35 => 'อื่นๆ',
            30 => 'ภาพยนตร์', 20 => 'แฟชั่น & บิวตี้', 24 => 'รายการทีวี',
        ],
    ];

    return $names[$language][$categoryId] ?? "カテゴリ{$categoryId}";
}

// EMIDを生成（カテゴリ + 時間 + インデックスで決定的に生成）
function generateEmid(int $categoryId, int $hourIndex, int $roomIndex, string $type = 'normal'): string
{
    $seed = "{$categoryId}-{$hourIndex}-{$roomIndex}-{$type}";
    return substr(md5($seed), 0, 32);
}

// 画像ハッシュを生成
function generateImageHash(string $emid): string
{
    return substr(md5($emid . '-image'), 0, 64);
}

// ランダムにタグを取得（1〜3個）
function getRandomTags(array $tags, string $emid): array
{
    if (empty($tags)) {
        return [];
    }

    // EMIDベースでシード値を設定（決定論的）
    mt_srand(crc32($emid . '-tags'));

    // 1〜3個のタグをランダムに選択
    $count = mt_rand(1, 3);
    $selectedTags = [];
    $availableTags = $tags;

    for ($i = 0; $i < $count && !empty($availableTags); $i++) {
        $index = mt_rand(0, count($availableTags) - 1);
        $selectedTags[] = $availableTags[$index];
        array_splice($availableTags, $index, 1);
    }

    mt_srand();
    return $selectedTags;
}

// ルームデータを生成
function generateRoom(int $categoryId, int $hourIndex, int $roomIndex, string $type, string $language, int $currentTime, int $actualHourIndex = 0, array $tags = [], int $emblem = 0, int $joinMethodType = 0): array
{
    $emid = generateEmid($categoryId, $hourIndex, $roomIndex, $type);
    $categoryName = getCategoryName($categoryId, $language);

    // ランダムにタグを取得
    $selectedTags = getRandomTags($tags, $emid);
    $tagsText = !empty($selectedTags) ? ' #' . implode(' #', $selectedTags) : '';

    if ($type === 'fixed-5' || $type === 'fixed-10' || $type === 'fixed-20') {
        // 人数固定ルーム（1時間目のみ出現）
        $memberCount = (int)explode('-', $type)[1];
        $name = match ($language) {
            'tw' => "人數不變的房間 (固定{$memberCount}人){$tagsText}",
            'th' => "ห้องที่จำนวนสมาชิกคงที่ (คงที่{$memberCount}คน){$tagsText}",
            default => "人数の変動がない部屋 (固定{$memberCount}人){$tagsText}",
        };
        $desc = match ($language) {
            'tw' => "此房間的成員數量始終固定，不會變動。這是測試用的固定人數房間。{$tagsText}",
            'th' => "จำนวนสมาชิกในห้องนี้คงที่เสมอและไม่เปลี่ยนแปลง นี่เป็นห้องจำนวนคงที่สำหรับการทดสอบ{$tagsText}",
            default => "このルームのメンバー数は常に固定されており、変動しません。テスト用の固定人数ルームです。{$tagsText}",
        };
    } elseif ($type === 'rising-only') {
        // 急上昇のみに登場（カテゴリ0専用、1時間目のみ）
        mt_srand(crc32($emid));
        $memberCount = mt_rand(100, 1000);
        mt_srand();

        $name = match ($language) {
            'tw' => "僅在熱門排行榜的房間 #{$roomIndex}{$tagsText}",
            'th' => "ห้องที่ปรากฏเฉพาะในรายการยอดนิยม #{$roomIndex}{$tagsText}",
            default => "急上昇のみの部屋 #{$roomIndex}{$tagsText}",
        };
        $desc = match ($language) {
            'tw' => "此房間僅出現在{$categoryName}類別的熱門排行榜中，不會出現在排名中。{$tagsText}",
            'th' => "ห้องนี้ปรากฏเฉพาะในรายการยอดนิยมของหมวดหมู่{$categoryName} และไม่ปรากฏในอันดับ{$tagsText}",
            default => "このルームは{$categoryName}カテゴリの急上昇のみに登場し、ランキングには登場しません。{$tagsText}",
        };
    } elseif ($type === 'new-room') {
        // 新規ルーム（hourIndexごとに異なるルームが出現）
        $memberCount = 10; // 固定10人
        $name = match ($language) {
            'tw' => "【新增】第{$actualHourIndex}小時{$tagsText}",
            'th' => "【ใหม่】ชั่วโมงที่{$actualHourIndex}{$tagsText}",
            default => "【新規】{$actualHourIndex}時間目{$tagsText}",
        };
        $desc = match ($language) {
            'tw' => "這是第{$actualHourIndex}小時出現的新房間（測試用）。{$tagsText}",
            'th' => "นี่คือห้องใหม่ที่ปรากฏในชั่วโมงที่{$actualHourIndex}（สำหรับการทดสอบ）{$tagsText}",
            default => "これは{$actualHourIndex}時間目に出現した新規ルームです（テスト用）。{$tagsText}",
        };
    } elseif ($type === 'changing') {
        // 変動ルーム（内容・人数・順位が時間経過で変化）
        // 基礎メンバー数（EMIDベースで決定的に生成）
        mt_srand(crc32($emid));
        $baseMemberCount = mt_rand(500, 3000);
        mt_srand();

        // 決定論的に人数を上下（sin波で±20%変動）
        $variation = (int)($baseMemberCount * 0.2 * sin($actualHourIndex / 3.0));
        $memberCount = max(50, $baseMemberCount + $variation);

        $name = match ($language) {
            'tw' => "【變動】房間 #{$roomIndex}（第{$actualHourIndex}小時）{$tagsText}",
            'th' => "【เปลี่ยนแปลง】ห้อง #{$roomIndex}（ชั่วโมงที่{$actualHourIndex}）{$tagsText}",
            default => "【変動】ルーム #{$roomIndex}（{$actualHourIndex}時間目）{$tagsText}",
        };
        $desc = match ($language) {
            'tw' => "此房間的成員數和排名會隨時間變化。當前成員數: " . number_format($memberCount) . "人{$tagsText}",
            'th' => "จำนวนสมาชิกและอันดับของห้องนี้เปลี่ยนแปลงตามเวลา สมาชิกปัจจุบัน: " . number_format($memberCount) . "คน{$tagsText}",
            default => "このルームのメンバー数と順位は時間経過で変化します。現在のメンバー数: " . number_format($memberCount) . "人{$tagsText}",
        };
    } else {
        // 静的ルーム（完全固定、hourIndex=0で生成）
        // 基礎メンバー数（EMIDベースで決定的に生成）
        mt_srand(crc32($emid));
        $memberCount = mt_rand(50, 5000);
        mt_srand();

        $name = match ($language) {
            'tw' => "{$categoryName} 房間 #{$roomIndex}{$tagsText}",
            'th' => "ห้อง{$categoryName} #{$roomIndex}{$tagsText}",
            default => "{$categoryName}ルーム #{$roomIndex}{$tagsText}",
        };
        $desc = match ($language) {
            'tw' => "這是{$categoryName}類別的測試房間。索引: {$roomIndex}{$tagsText}",
            'th' => "นี่คือห้องทดสอบของหมวดหมู่{$categoryName} ดัชนี: {$roomIndex}{$tagsText}",
            default => "これは{$categoryName}カテゴリのテストルームです。インデックス: {$roomIndex}{$tagsText}",
        };
    }

    return [
        'emid' => $emid,
        'name' => $name,
        'desc' => $desc,
        'profileImageObsHash' => generateImageHash($emid),
        'memberCount' => $memberCount,
        'category' => $categoryId,
        'emblem' => $emblem,
        'joinMethodType' => $joinMethodType,
        'createdAt' => $currentTime - rand(0, 365 * 24 * 3600),
        'type' => $type,
    ];
}

// ランキング/急上昇データを生成
function generateCategoryData(int $categoryId, string $sort, int $hourIndex, string $language, int $currentTime, array $tags = []): array
{
    $rooms = [];

    if ($hourIndex === 0) {
        // 1時間目の構成
        if ($categoryId === 0 && $sort === 'RISING') {
            // カテゴリ0急上昇: rising-onlyルーム16件 + 変動ルーム8件 + 静的ルーム56件 = 80件
            for ($i = 0; $i < 16; $i++) {
                $rooms[] = generateRoom($categoryId, 0, $i, 'rising-only', $language, $currentTime, 0, $tags);
            }
            for ($i = 16; $i < 24; $i++) {
                $rooms[] = generateRoom($categoryId, 0, $i, 'changing', $language, $currentTime, 0, $tags);
            }
            for ($i = 24; $i < 80; $i++) {
                $rooms[] = generateRoom($categoryId, 0, $i, 'static', $language, $currentTime, 0, $tags);
            }
        } elseif ($categoryId === 0 && $sort === 'RANKING') {
            // カテゴリ0ランキング: 変動ルーム8件 + 静的ルーム72件 = 80件
            for ($i = 0; $i < 8; $i++) {
                $rooms[] = generateRoom($categoryId, 0, $i, 'changing', $language, $currentTime, 0, $tags);
            }
            for ($i = 8; $i < 80; $i++) {
                $rooms[] = generateRoom($categoryId, 0, $i, 'static', $language, $currentTime, 0, $tags);
            }
        } else {
            // カテゴリ1以降: fixed-5/10/20ルーム16件 + 変動ルーム8件 + 静的ルーム56件 = 80件
            for ($i = 0; $i < 8; $i++) {
                // fixed-5ルームに特別な値を設定
                $emblem = ($i === 0) ? 1 : (($i === 1) ? 2 : 0);
                $joinMethodType = ($i === 2) ? 1 : (($i === 3) ? 2 : 0);
                $rooms[] = generateRoom($categoryId, 0, $i, 'fixed-5', $language, $currentTime, 0, $tags, $emblem, $joinMethodType);
            }
            for ($i = 8; $i < 12; $i++) {
                $rooms[] = generateRoom($categoryId, 0, $i, 'fixed-10', $language, $currentTime, 0, $tags);
            }
            for ($i = 12; $i < 16; $i++) {
                $rooms[] = generateRoom($categoryId, 0, $i, 'fixed-20', $language, $currentTime, 0, $tags);
            }
            for ($i = 16; $i < 24; $i++) {
                $rooms[] = generateRoom($categoryId, 0, $i, 'changing', $language, $currentTime, 0, $tags);
            }
            for ($i = 24; $i < 80; $i++) {
                $rooms[] = generateRoom($categoryId, 0, $i, 'static', $language, $currentTime, 0, $tags);
            }
        }
    } else {
        // 2時間目以降: 新規ルーム1件 + 変動ルーム8件 + 静的ルーム71件 = 80件
        // 新規ルーム1件（hourIndexベース、インデックス0）
        $rooms[] = generateRoom($categoryId, $hourIndex, 0, 'new-room', $language, $currentTime, $hourIndex, $tags);

        // 変動ルーム8件（インデックス1-8、hourIndex=0ベースでEMID固定、人数のみ変化）
        for ($i = 1; $i <= 8; $i++) {
            // 変動ルームに特別な値を設定
            $emblem = ($i === 1) ? 1 : (($i === 2) ? 2 : 0);
            $joinMethodType = ($i === 3) ? 1 : (($i === 4) ? 2 : 0);
            $rooms[] = generateRoom($categoryId, 0, $i, 'changing', $language, $currentTime, $hourIndex, $tags, $emblem, $joinMethodType);
        }

        // 静的ルーム71件（インデックス9-79、hourIndex=0で完全固定）
        for ($i = 9; $i < 80; $i++) {
            $rooms[] = generateRoom($categoryId, 0, $i, 'static', $language, $currentTime, 0, $tags);
        }
    }

    return $rooms;
}

// サブカテゴリデータを生成
function generateSubcategories(int $categoryId, string $language): array
{
    if ($categoryId === 0) {
        return [];
    }

    $subcategoriesData = [
        'ja' => [
            17 => ['ポケポケ', 'ブレインロット', 'ポケモンza', 'フォートナイト', 'ポケモンGO'],
            16 => ['プロ野球', '陸上', 'テニス', '卓球', 'ゴルフ'],
        ],
        'tw' => [
            17 => ['寶可夢', '動森', '原神', '英雄聯盟', 'APEX'],
            42 => ['音樂', '電影', '綜藝', '偶像', 'K-POP'],
        ],
        'th' => [
            17 => ['ROV', 'PUBG', 'Free Fire', 'Minecraft', 'Roblox'],
            33 => ['K-POP', 'T-POP', 'BTS', 'BLACKPINK', 'แร็พ'],
        ],
    ];

    $subcategoryNames = $subcategoriesData[$language][$categoryId] ?? [];
    if (empty($subcategoryNames)) {
        return [];
    }

    $subcategories = [];
    foreach ($subcategoryNames as $index => $name) {
        $subcategories[] = [
            'id' => ($categoryId * 1000) + $index + 1,
            'subcategory' => $name,
            'categoryId' => $categoryId,
        ];
    }

    return $subcategories;
}

// レスポンスヘッダー
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$requestUri = $_SERVER['REQUEST_URI'];

try {
    // robots.txt
    if ($requestUri === '/robots.txt') {
        header('Content-Type: text/plain');
        echo "User-agent: *\nAllow: /\n";
        exit;
    }

    // カテゴリランキングAPI・急上昇API
    if (preg_match('#^/api/category/(\d+)\?sort=(RANKING|RISING)&limit=(\d+)(?:&ct=(.*))?$#', $requestUri, $matches)) {
        $categoryId = (int)$matches[1];
        $sort = $matches[2];
        $limit = (int)$matches[3];
        $ct = isset($matches[4]) ? urldecode($matches[4]) : '';

        // デバッグログ
        $debugLog = sprintf(
            "[%s] %s Request - Category: %d, Sort: %s, Limit: %d, CT: %s, Language: %s, HourIndex: %d\n",
            date('Y-m-d H:i:s'),
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $categoryId,
            $sort,
            $limit,
            $ct === '' ? 'empty' : $ct,
            $language,
            $hourIndex
        );
        error_log($debugLog, 3, '/app/data/debug-fixed.log');

        // データ生成（言語に応じたタグを渡す）
        $tags = $popularTags[$language] ?? $popularTags['ja'];
        $categoryRooms = generateCategoryData($categoryId, $sort, $hourIndex, $language, $currentTime, $tags);

        // ページネーション
        $start = $ct === '' ? 0 : (int)$ct;
        $end = $start + $limit;
        $pageRooms = array_slice($categoryRooms, $start, $limit);

        // LINE API形式に変換
        $squares = [];
        $rank = $start + 1;
        foreach ($pageRooms as $room) {
            $squares[] = [
                'square' => [
                    'emid' => $room['emid'],
                    'name' => $room['name'],
                    'desc' => $room['desc'],
                    'profileImageObsHash' => $room['profileImageObsHash'],
                    'emblems' => $room['emblem'] > 0 ? [$room['emblem']] : [],
                    'joinMethodType' => $room['joinMethodType'],
                    'squareState' => 0,
                    'badges' => [],
                    'invitationURL' => "https://line.me/ti/g2/{$room['emid']}",
                ],
                'rank' => $rank++,
                'memberCount' => $room['memberCount'],
                'latestMessageCreatedAt' => time() * 1000,
                'createdAt' => $room['createdAt'] * 1000,
            ];
        }

        // サブカテゴリデータを生成
        $subcategories = generateSubcategories($categoryId, $language);

        $response = [
            'squaresByCategory' => [
                [
                    'category' => ['id' => $categoryId],
                    'squares' => $squares,
                    'subcategories' => $subcategories,
                ]
            ]
        ];

        // 次のページがあれば継続トークン
        if ($end < count($categoryRooms)) {
            $response['continuationTokenMap'] = [(string)$categoryId => (string)$end];
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // スクエア詳細API
    if (preg_match('#^/api/square/([a-zA-Z0-9_-]+)\?limit=1$#', $requestUri, $matches)) {
        $emid = $matches[1];

        // すべてのカテゴリとすべての時間からルームを検索
        $room = null;
        $categoryIds = getCategoryIds($language);

        // 最大24時間または1時間分を検索
        $maxHours = $language === 'ja' ? 24 : 1;
        $tags = $popularTags[$language] ?? $popularTags['ja'];

        foreach ($categoryIds as $categoryId) {
            for ($h = 0; $h < $maxHours; $h++) {
                // ランキングとソート両方から検索
                foreach (['RANKING', 'RISING'] as $sort) {
                    $rooms = generateCategoryData($categoryId, $sort, $h, $language, $currentTime, $tags);
                    foreach ($rooms as $r) {
                        if ($r['emid'] === $emid) {
                            $room = $r;
                            break 4;
                        }
                    }
                }
            }
        }

        if (!$room) {
            http_response_code(404);
            echo json_encode(['error' => 'Square not found']);
            exit;
        }

        // invitationTicket生成
        $invitationTicket = substr($room['emid'], 0, 10);

        echo json_encode([
            'square' => [
                'squareEmid' => $room['emid'],
                'name' => $room['name'],
                'desc' => $room['desc'],
                'profileImageObsHash' => $room['profileImageObsHash'],
                'memberCount' => $room['memberCount'],
                'joinMethodType' => $room['joinMethodType'],
            ],
            'recommendedSquares' => [],
            'noteCount' => 0,
            'productKey' => 'square-seo-fixed',
            'invitationTicket' => $invitationTicket,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 招待ページHTML
    if (preg_match('#^/(jp|tw|th)?/?ti/g2/([a-zA-Z0-9_-]+)$#', $requestUri, $matches)) {
        $langPrefix = $matches[1] ?? 'jp';
        $emid = $matches[2];

        $pageLang = match($langPrefix) {
            'tw' => 'tw',
            'th' => 'th',
            default => 'ja',
        };

        // すべてのカテゴリとすべての時間からルームを検索
        $room = null;
        $categoryIds = getCategoryIds($pageLang);
        $maxHours = $pageLang === 'ja' ? 24 : 1;
        $tags = $popularTags[$pageLang] ?? $popularTags['ja'];

        foreach ($categoryIds as $categoryId) {
            for ($h = 0; $h < $maxHours; $h++) {
                foreach (['RANKING', 'RISING'] as $sort) {
                    $rooms = generateCategoryData($categoryId, $sort, $h, $pageLang, $currentTime, $tags);
                    foreach ($rooms as $r) {
                        if ($r['emid'] === $emid) {
                            $room = $r;
                            break 4;
                        }
                    }
                }
            }
        }

        if (!$room) {
            http_response_code(404);
            echo '<html><body>Not Found</body></html>';
            exit;
        }

        header('Content-Type: text/html; charset=utf-8');
        echo "<!DOCTYPE html>
<html><head><meta charset='UTF-8'><title>{$room['name']}</title></head>
<body style='font-family:sans-serif;padding:20px;'>
<div style='max-width:600px;margin:0 auto;'>
<img src='https://line-mock-api/obs/{$room['profileImageObsHash']}' class='mdMN01Img' style='width:100px;height:100px;border-radius:50%;'>
<h1 class='MdMN04Txt'>{$room['name']}</h1>
<p class='MdMN05Txt'>メンバー数: " . number_format($room['memberCount']) . "</p>
<p class='MdMN06Desc'>{$room['desc']}</p>
</div></body></html>";
        exit;
    }

    // 画像CDN（/obs/画像ハッシュ または /画像ハッシュ の両方に対応）
    if (preg_match('#^/(?:obs/)?([a-zA-Z0-9_-]{30,})(/preview)?$#', $requestUri, $matches)) {
        $imageHash = $matches[1];

        header('Content-Type: image/jpeg');
        header('Cache-Control: public, max-age=31536000');

        // ハッシュから一貫性のある画像生成
        $seed = crc32($imageHash);
        mt_srand($seed);

        $r = mt_rand(150, 255);
        $g = mt_rand(150, 255);
        $b = mt_rand(150, 255);

        $img = imagecreatetruecolor(100, 100);
        $bgColor = imagecolorallocate($img, $r, $g, $b);
        imagefill($img, 0, 0, $bgColor);

        ob_start();
        imagejpeg($img, null, 80);
        echo ob_get_clean();
        exit;
    }

    // 404
    http_response_code(404);
    echo json_encode(['error' => 'Not Found', 'uri' => $requestUri]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
