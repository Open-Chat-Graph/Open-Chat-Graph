<?php
declare(strict_types=1);

/**
 * LINE公式API モックサーバー（シンプル版）
 *
 * JSONベースで本物のAPIと同じ挙動をシミュレート:
 * - 約10万件のルームデータ
 * - 10%: タイトル・説明文変化
 * - 40%: メンバー数増減
 * - 70%: 既存固定ルーム（順位変動）
 * - 30%: 新規急上昇ルーム
 */

// データファイル
$rankingDataFile = '/app/data/ranking.json';
$risingDataFile = '/app/data/rising.json';

// 時間ベースのシード値（10分ごとに変化）
$crawlCycle = (int)(time() / 600); // 600秒 = 10分

// ランダムテキスト生成
function generateRandomTitle(int $seed): string
{
    mt_srand($seed);

    $templates = [
        '%s好き集まれ！',
        '%s部屋',
        '%s雑談',
        '%sファン',
        '%s初心者歓迎',
        '%s攻略',
        '%sまったり',
        '%sガチ勢',
        '%s情報交換',
        '%sコミュニティ',
    ];

    $topics = [
        'ゲーム', 'アニメ', 'マンガ', 'スポーツ', '音楽', '映画', 'グルメ', '旅行',
        'ファッション', '美容', 'ペット', '車', 'バイク', 'カメラ', '釣り', '料理',
        '筋トレ', 'ヨガ', 'ダンス', 'ギター', 'ピアノ', '英語', '資格', 'プログラミング',
        '副業', '投資', '仮想通貨', 'NFT', 'メタバース', 'AI', 'ガジェット', 'スマホ',
    ];

    $template = $templates[array_rand($templates)];
    $topic = $topics[array_rand($topics)];

    return sprintf($template, $topic);
}

function generateRandomDescription(int $seed): string
{
    mt_srand($seed);

    $templates = [
        '%sについて語り合いましょう！初心者から上級者まで大歓迎です。',
        '%sが好きな人集まれ！気軽に参加してください。',
        '%sの情報交換や雑談をするグループです。みんなで楽しく話しましょう！',
        '%sに関する質問・相談・攻略などなんでもOK！まったりやってます。',
        '%s仲間を探してます。一緒に楽しみましょう！',
    ];

    $topics = [
        'ゲーム', 'アニメ', 'マンガ', 'スポーツ', '音楽', '映画', 'グルメ', '旅行',
        'ファッション', '美容', 'ペット', '趣味', '勉強', '仕事', 'ビジネス',
    ];

    $template = $templates[array_rand($templates)];
    $topic = $topics[array_rand($topics)];

    return sprintf($template, $topic);
}

// データ読み込み・初期化
function loadOrInitializeData(string $dataFile, int $count): array
{
    if (file_exists($dataFile)) {
        $json = file_get_contents($dataFile);
        return json_decode($json, true) ?? [];
    }

    // 初期データ生成
    $rooms = [];
    $categories = [0, 2, 5, 6, 7, 8, 11, 12, 16, 17, 18, 19, 20, 22, 23, 24, 26, 27, 28, 29, 30, 33, 37, 40, 41];

    // 90%は固定EMID、10%はランダムEMID
    $fixedCount = (int)($count * 0.9);
    $randomCount = $count - $fixedCount;

    // 固定EMID（シードベース生成 - 常に同じEMIDになる）
    for ($i = 0; $i < $fixedCount; $i++) {
        $categoryId = $categories[$i % count($categories)];

        // シードベースで固定EMIDを生成
        mt_srand($i + 1000);
        $emidSeed = mt_rand();
        $emid = substr(md5((string)$emidSeed), 0, 32);
        $imageHash = substr(md5((string)($emidSeed + 1)), 0, 64);

        $rooms[] = [
            'emid' => $emid,
            'name' => generateRandomTitle($emidSeed),
            'desc' => generateRandomDescription($emidSeed + 100),
            'profileImageObsHash' => $imageHash,
            'memberCount' => rand(100, 10000),
            'category' => $categoryId,
            'emblem' => rand(0, 1),
            'joinMethodType' => rand(0, 1),
            'createdAt' => time() - rand(0, 365 * 24 * 3600),
        ];
    }

    // ランダムEMID（新規ルーム）
    for ($i = 0; $i < $randomCount; $i++) {
        $categoryId = $categories[array_rand($categories)];
        $emid = bin2hex(random_bytes(16));
        $randomSeed = rand();

        $rooms[] = [
            'emid' => $emid,
            'name' => generateRandomTitle($randomSeed),
            'desc' => generateRandomDescription($randomSeed + 100),
            'profileImageObsHash' => bin2hex(random_bytes(32)),
            'memberCount' => rand(10, 1000),
            'category' => $categoryId,
            'emblem' => rand(0, 1),
            'joinMethodType' => rand(0, 1),
            'createdAt' => time() - rand(0, 7 * 24 * 3600), // 1週間以内
        ];
    }

    // 保存
    file_put_contents($dataFile, json_encode($rooms, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    return $rooms;
}

// データを動的に変化させる
function simulateDataChanges(array $rooms, int $seed): array
{
    mt_srand($seed);

    foreach ($rooms as &$room) {
        // 40%: メンバー数増減（1時間で10~100人程度 = 10分あたり2~17人）
        if (mt_rand(1, 100) <= 40) {
            $change = mt_rand(-20, 20); // ±20人/10分
            $room['memberCount'] += $change;
            $room['memberCount'] = max(1, $room['memberCount']);
        }

        // 10%: タイトル変化
        if (mt_rand(1, 100) <= 10) {
            $room['name'] .= ' 🔥';
        }
    }

    return $rooms;
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

        // データ読み込み（RANKINGは1万件、RISINGは1千件）※テスト用に削減
        if ($sort === 'RANKING') {
            $allRooms = loadOrInitializeData($rankingDataFile, 10000);
        } else {
            $allRooms = loadOrInitializeData($risingDataFile, 1000);
        }

        // カテゴリでフィルター
        $categoryRooms = array_filter($allRooms, fn($r) => $r['category'] === $categoryId);
        $categoryRooms = array_values($categoryRooms);

        // データ変化をシミュレート
        $categoryRooms = simulateDataChanges($categoryRooms, $crawlCycle + $categoryId);

        // ランキングソート（メンバー数順）
        usort($categoryRooms, fn($a, $b) => $b['memberCount'] - $a['memberCount']);

        // シャッフル（30%を新規急上昇としてランダム挿入）
        if ($sort === 'RISING') {
            mt_srand($hourSeed);
            shuffle($categoryRooms);
        }

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

        $response = [
            'squaresByCategory' => [
                [
                    'category' => ['id' => $categoryId],
                    'squares' => $squares,
                    'subcategories' => [],
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

        // 両方のデータセットから検索
        $allRooms = array_merge(
            loadOrInitializeData($rankingDataFile, 10000),
            loadOrInitializeData($risingDataFile, 1000)
        );

        $room = null;
        foreach ($allRooms as $r) {
            if ($r['emid'] === $emid) {
                $room = $r;
                break;
            }
        }

        if (!$room) {
            http_response_code(404);
            echo json_encode(['error' => 'Square not found']);
            exit;
        }

        echo json_encode([
            'squares' => [
                [
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
                    'rank' => 0,
                    'memberCount' => $room['memberCount'],
                    'latestMessageCreatedAt' => time() * 1000,
                    'createdAt' => $room['createdAt'] * 1000,
                ]
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 招待ページHTML
    if (preg_match('#^/(jp|tw|th)?/?ti/g2/([a-zA-Z0-9_-]+)$#', $requestUri, $matches)) {
        $emid = $matches[2];

        // 両方のデータセットから検索
        $allRooms = array_merge(
            loadOrInitializeData($rankingDataFile, 10000),
            loadOrInitializeData($risingDataFile, 1000)
        );

        $room = null;
        foreach ($allRooms as $r) {
            if ($r['emid'] === $emid) {
                $room = $r;
                break;
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
<img src='https://obs.line-scdn.net/{$room['profileImageObsHash']}' style='width:100px;height:100px;border-radius:50%;'>
<h1 class='MdMN04Txt'>{$room['name']}</h1>
<p class='MdMN05Txt'>メンバー数: " . number_format($room['memberCount']) . "</p>
<p class='MdMN06Desc'>{$room['desc']}</p>
</div></body></html>";
        exit;
    }

    // 画像CDN
    if (preg_match('#^/([a-zA-Z0-9_-]+)(/preview\.[0-9x]+)?$#', $requestUri, $matches)) {
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
