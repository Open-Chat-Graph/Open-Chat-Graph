<?php
declare(strict_types=1);

/**
 * LINE公式API モックデータ生成スクリプト
 *
 * 実際のDBデータから抽出してモックAPIのテストデータを生成
 *
 * 使い方:
 * docker-compose exec app php /var/www/html/docker/line-mock-api/generate-test-data.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../local-secrets.php';

use Shadow\DB;

// データ出力ディレクトリ
$outputDir = __DIR__ . '/../../storage/dev-mock-data';
$categoriesDir = "{$outputDir}/categories/ja";
$squaresDir = "{$outputDir}/squares/ja";
$imagesDir = "{$outputDir}/images";

// ディレクトリ作成
foreach ([$categoriesDir, $squaresDir, $imagesDir] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}

echo "LINE公式API モックデータ生成開始\n";
echo "=====================================\n\n";

// DB接続
DB::connect();

// カテゴリIDリスト（実際のLINE OpenChatカテゴリ）
$categories = [
    0 => '全体',
    2 => 'ファッション・美容',
    5 => 'エンタメ',
    6 => '趣味',
    7 => 'アニメ・漫画',
    8 => '地域・暮らし',
    11 => 'グルメ',
    12 => 'ニュース・社会',
    16 => 'スポーツ',
    17 => 'ゲーム',
    18 => '雑談',
    19 => 'ファン',
    20 => '勉強・資格',
    22 => '乗り物',
    23 => '音楽',
    24 => '質問・相談',
    26 => '芸能人・有名人',
    27 => 'イベント・キャンペーン',
    28 => 'ビジネス',
    29 => 'IT・テクノロジー',
    30 => 'スピリチュアル・占い',
    33 => '学校',
    37 => 'プログラミング',
    40 => 'マンガ・小説',
    41 => 'ペット・動物',
];

// 1. ランキングデータ生成（各カテゴリごと）
echo "1. ランキングデータ生成中...\n";

foreach ($categories as $categoryId => $categoryName) {
    echo "  カテゴリ {$categoryId}: {$categoryName}\n";

    // 最新のランキングデータを取得（約8000-9000件）
    $stmt = DB::$pdo->prepare("
        SELECT
            r.open_chat_id,
            r.position,
            oc.name,
            oc.img_url,
            oc.description,
            oc.member,
            oc.emid,
            oc.category,
            oc.join_method_type
        FROM ocgraph_ranking.ranking r
        INNER JOIN ocgraph_ocreview.open_chat oc ON r.open_chat_id = oc.id
        WHERE r.category = ? AND r.time = (
            SELECT MAX(time) FROM ocgraph_ranking.ranking WHERE category = ?
        )
        ORDER BY r.position ASC
        LIMIT 200
    ");
    $stmt->execute([$categoryId, $categoryId]);
    $rankingData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rankingData)) {
        echo "    ⚠ データなし、スキップ\n";
        continue;
    }

    // ランキング形式に変換
    $squares = [];
    foreach ($rankingData as $row) {
        $squares[] = [
            'id' => (string)$row['open_chat_id'],
            'name' => $row['name'],
            'image' => $row['img_url'],
            'description' => $row['description'],
            'memberCount' => (int)$row['member'],
            'iconImage' => [
                'hash' => basename($row['img_url'])
            ],
            'joinMethodType' => (int)$row['join_method_type'],
            'category' => (int)$row['category'],
            'emid' => $row['emid'],
        ];

        // スクエア詳細データも生成
        $squareFile = "{$squaresDir}/{$row['emid']}.json";
        if (!file_exists($squareFile)) {
            file_put_contents($squareFile, json_encode($squares[count($squares) - 1], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }
    }

    // RANKINGデータ保存
    $rankingFile = "{$categoriesDir}/category_{$categoryId}_RANKING.json";
    file_put_contents($rankingFile, json_encode([
        'squares' => $squares,
        'subcategories' => []
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    echo "    ✓ RANKING: " . count($squares) . "件\n";

    // 急上昇データを取得
    $stmt = DB::$pdo->prepare("
        SELECT
            r.open_chat_id,
            r.position,
            oc.name,
            oc.img_url,
            oc.description,
            oc.member,
            oc.emid,
            oc.category,
            oc.join_method_type
        FROM ocgraph_ranking.rising r
        INNER JOIN ocgraph_ocreview.open_chat oc ON r.open_chat_id = oc.id
        WHERE r.category = ? AND r.time = (
            SELECT MAX(time) FROM ocgraph_ranking.rising WHERE category = ?
        )
        ORDER BY r.position ASC
        LIMIT 120
    ");
    $stmt->execute([$categoryId, $categoryId]);
    $risingData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($risingData)) {
        $risingSquares = [];
        foreach ($risingData as $row) {
            $risingSquares[] = [
                'id' => (string)$row['open_chat_id'],
                'name' => $row['name'],
                'image' => $row['img_url'],
                'description' => $row['description'],
                'memberCount' => (int)$row['member'],
                'iconImage' => [
                    'hash' => basename($row['img_url'])
                ],
                'joinMethodType' => (int)$row['join_method_type'],
                'category' => (int)$row['category'],
                'emid' => $row['emid'],
            ];
        }

        // RISINGデータ保存
        $risingFile = "{$categoriesDir}/category_{$categoryId}_RISING.json";
        file_put_contents($risingFile, json_encode([
            'squares' => $risingSquares,
            'subcategories' => []
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        echo "    ✓ RISING: " . count($risingSquares) . "件\n";
    }
}

echo "\n2. サブカテゴリデータ生成中...\n";

// サブカテゴリJSONファイルが存在する場合は読み込み
$subcategoriesFile = __DIR__ . '/../../storage/ja/open_chat_sub_categories/subcategories.json';
if (file_exists($subcategoriesFile)) {
    $subcategoriesData = json_decode(file_get_contents($subcategoriesFile), true);

    // カテゴリごとのサブカテゴリを各ランキングファイルに追加
    foreach ($categories as $categoryId => $categoryName) {
        $subcats = array_filter($subcategoriesData ?? [], function($item) use ($categoryId) {
            return isset($item['categoryId']) && $item['categoryId'] === $categoryId;
        });

        if (!empty($subcats)) {
            // RANKINGファイルを更新
            $rankingFile = "{$categoriesDir}/category_{$categoryId}_RANKING.json";
            if (file_exists($rankingFile)) {
                $data = json_decode(file_get_contents($rankingFile), true);
                $data['subcategories'] = array_values($subcats);
                file_put_contents($rankingFile, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                echo "  カテゴリ {$categoryId}: サブカテゴリ " . count($subcats) . "件追加\n";
            }
        }
    }
}

echo "\n3. 統計情報\n";
echo "=====================================\n";
echo "生成されたファイル:\n";
echo "  カテゴリRANKINGファイル: " . count(glob("{$categoriesDir}/category_*_RANKING.json")) . "件\n";
echo "  カテゴリRISINGファイル: " . count(glob("{$categoriesDir}/category_*_RISING.json")) . "件\n";
echo "  スクエア詳細ファイル: " . count(glob("{$squaresDir}/*.json")) . "件\n";

echo "\n✓ テストデータ生成完了\n";
echo "\n次のステップ:\n";
echo "1. docker-compose -f docker-compose.dev.yml up -d\n";
echo "2. DEV環境でCRON処理をテスト実行\n";
