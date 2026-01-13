#!/bin/bash

# 引数チェック（-yes または -y が指定されているか）
auto_confirm=false
if [[ "$1" == "-yes" || "$1" == "-y" ]]; then
    auto_confirm=true
fi

# 確認メッセージを表示
if [ "$auto_confirm" = false ]; then
    echo "=========================================="
    echo "警告: データベースとストレージの初期化"
    echo "=========================================="
    echo ""
    echo "このスクリプトは以下の処理を実行します:"
    echo "  1. local-secrets.php 設定ファイルの上書き"
    echo "  2. storage内の既存データをすべて削除"
    echo "  3. テンプレートファイルとサンプルDBをstorageにコピー"
    echo "  4. MySQLデータベースの初期化"
    echo "  5. composerの依存関係をインストール"
    echo ""
    echo "重要: このスクリプトを実行すると、以下のデータがすべて削除されます:"
    echo "  - MySQLデータベースの全データ"
    echo "  - storage内のSQLiteデータベース、キャッシュ、ランキングデータ"
    echo ""
    read -p "続行しますか? (yes/no): " response
    echo ""

    # 小文字に変換
    response=$(echo "$response" | tr '[:upper:]' '[:lower:]')

    # レスポンスをチェック
    if [[ "$response" != "yes" && "$response" != "y" ]]; then
        echo "セットアップをキャンセルしました。"
        exit 0
    fi
fi

echo "セットアップを開始します..."
echo ""

cat << 'EOF' > local-secrets.php
<?php

use App\Config\SecretsConfig;
use Shared\MimimalCmsConfig;
use App\Config\AppConfig;

AppConfig::$disableAds = false;
AppConfig::$disableStaticDataFile = false;

AppConfig::$isDevlopment = true;
AppConfig::$isMockEnvironment = false;

AppConfig::$isStaging = false;
AppConfig::$phpBinary = 'php';

MimimalCmsConfig::$exceptionHandlerDisplayErrorTraceDetails = true;
MimimalCmsConfig::$errorPageHideDirectory = '/var/www/html';
MimimalCmsConfig::$errorPageDocumentRootName = 'html';

MimimalCmsConfig::$stringCryptorHkdfKey = 'HKDF_KEY';
MimimalCmsConfig::$stringCryptorOpensslKey = 'OPEN_SSL_KEY';

SecretsConfig::$adminApiKey = 'key';
SecretsConfig::$googleRecaptchaSecretKey = '';
SecretsConfig::$cloudFlareZoneId = '';
SecretsConfig::$cloudFlareApiKey = '';
SecretsConfig::$yahooClientId = '';
SecretsConfig::$discordWebhookUrl = 'https://discord.com/api/webhooks/x/x';

MimimalCmsConfig::$dbHost = 'mysql';
MimimalCmsConfig::$dbUserName = 'root';
MimimalCmsConfig::$dbPassword = 'test_root_pass';

EOF

rm -f storage/*/open_chat_sub_categories/subcategories.json
rm -f storage/*/static_data_top/*.dat
rm -f storage/*/static_data_recommend/*/*.dat
rm -f storage/*/ranking_position/*/*.dat
rm -f storage/*/SQLite/statistics/*.db*
rm -f storage/*/SQLite/ranking_position/*.db*
rm -f storage/*/SQLite/ocgraph_sqlapi/*.db*
rm -f storage/*.log
rm -f storage/*/*/*.log

find public/oc-img* -mindepth 1 -maxdepth 1 ! -name 'default' ! -name 'preview' -exec rm -rf {} +
find public/oc-img*/preview -mindepth 1 -maxdepth 1 ! -name 'default' -exec rm -rf {} +

cp setup/template/static_data_top/* storage/ja/static_data_top/
cp setup/template/static_data_top/* storage/tw/static_data_top/
cp setup/template/static_data_top/* storage/th/static_data_top/

# スキーマファイルからSQLiteデータベースを生成
echo "スキーマファイルからSQLiteデータベースを生成しています..."

# 各言語のディレクトリに対して処理
for lang in ja tw th; do
    echo "  ${lang} のデータベースを生成中..."

    # statistics.db を生成
    sqlite3 "storage/${lang}/SQLite/statistics/statistics.db" < setup/schema/sqlite/statistics.sql

    # ranking_position.db を生成
    sqlite3 "storage/${lang}/SQLite/ranking_position/ranking_position.db" < setup/schema/sqlite/ranking_position.sql

    # sqlapi.db を生成（jaのみ）
    if [ "$lang" == "ja" ]; then
        sqlite3 "storage/${lang}/SQLite/ocgraph_sqlapi/sqlapi.db" < setup/schema/sqlite/sqlapi.sql
    fi
done

echo "SQLiteデータベースの生成が完了しました。"
echo ""

./setup/init-database.sh

composer install
