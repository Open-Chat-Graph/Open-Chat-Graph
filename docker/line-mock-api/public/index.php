<?php
declare(strict_types=1);

/**
 * LINE公式API モックサーバー - ルーター
 *
 * 環境変数 MOCK_API_TYPE により適切な実装を読み込む:
 * - fixed: カテゴリ別クローリングテスト用（固定データ、hourIndexベース）
 * - dynamic（デフォルト）: リアル挙動シミュレーション（大量データ、時間ベース変化）
 */

$apiType = $_ENV['MOCK_API_TYPE'] ?? getenv('MOCK_API_TYPE') ?: 'dynamic';

if ($apiType === 'fixed') {
    require __DIR__ . '/fixed.php';
} else {
    require __DIR__ . '/dynamic.php';
}
