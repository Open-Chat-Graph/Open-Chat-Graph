<?php
declare(strict_types=1);

/**
 * 実際のクローリングデータを調査するスクリプト
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../shared/MimimalCMS_HelperFunctions.php';

echo "実際のクローリングデータ調査\n";
echo "=====================================\n\n";

// ランキングデータを確認
$rankingFile = '/home/user/oc-review-dev/storage/ja/ranking_position/ranking/17.dat';
echo "ファイル: {$rankingFile}\n";
echo "サイズ: " . filesize($rankingFile) . " bytes\n\n";

$data = getUnserializedFile($rankingFile);

if ($data === false) {
    echo "エラー: ファイルの読み込みに失敗\n";
    exit(1);
}

echo "データ型: " . gettype($data) . "\n";
echo "データ構造:\n";
print_r(array_slice($data, 0, 3)); // 最初の3件を表示

echo "\n総件数: " . count($data) . "\n";

// 急上昇データも確認
echo "\n=====================================\n";
$risingFile = '/home/user/oc-review-dev/storage/ja/ranking_position/rising/8.dat';
echo "ファイル: {$risingFile}\n";

if (file_exists($risingFile)) {
    $risingData = getUnserializedFile($risingFile);
    echo "急上昇データ件数: " . count($risingData) . "\n";
    echo "サンプル:\n";
    print_r(array_slice($risingData, 0, 2));
}
