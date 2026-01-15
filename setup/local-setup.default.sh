#!/bin/bash

# 引数の解析
# 第1引数: DB・storage初期化（-y/yes = 自動実行、省略時は確認）
# 第2引数: local-secrets.php上書き（省略時はyes、-n/no = 上書きしない）
#
# 使用例:
#   ./setup/local-setup.default.sh           # 両方とも対話型確認
#   ./setup/local-setup.default.sh -y        # DB初期化は自動実行、local-secrets.phpも作成
#   ./setup/local-setup.default.sh -y -n     # DB初期化は自動実行、local-secrets.phpは保持
#   ./setup/local-setup.default.sh yes no    # 同上

# ヘルプ表示
if [[ "$1" == "-h" || "$1" == "--help" ]]; then
    echo "使用方法: ./setup/local-setup.default.sh [DB初期化] [local-secrets.php上書き]"
    echo ""
    echo "引数:"
    echo "  第1引数: DB・storage初期化"
    echo "    -y, yes  = 自動実行"
    echo "    省略時   = 対話型確認"
    echo ""
    echo "  第2引数: local-secrets.php上書き"
    echo "    省略時   = 作成（ファイルがある場合は確認）"
    echo "    -n, no   = 上書きしない"
    echo ""
    echo "使用例:"
    echo "  ./setup/local-setup.default.sh           # 両方とも対話型確認"
    echo "  ./setup/local-setup.default.sh -y        # DB初期化は自動実行、local-secrets.phpも作成"
    echo "  ./setup/local-setup.default.sh -y -n     # DB初期化は自動実行、local-secrets.phpは保持"
    exit 0
fi

db_init_auto=false
overwrite_secrets=true

# 第1引数: DB初期化
if [[ "$1" == "-y" || "$1" == "yes" ]]; then
    db_init_auto=true
fi

# 第2引数: local-secrets.php上書き
if [[ "$2" == "-n" || "$2" == "no" ]]; then
    overwrite_secrets=false
fi

# 初期状態の確認（SQLiteファイルとMySQLデータベースの存在チェック）
has_existing_data=false

# SQLiteファイルの存在確認
if ls storage/*/SQLite/statistics/*.db* >/dev/null 2>&1 || \
   ls storage/*/SQLite/ranking_position/*.db* >/dev/null 2>&1 || \
   ls storage/*/SQLite/ocgraph_sqlapi/*.db* >/dev/null 2>&1; then
    has_existing_data=true
fi

# MySQLデータベースの存在確認（docker composeが起動している場合のみ）
if [ "$has_existing_data" = false ] && docker compose ps mysql | grep -q "Up"; then
    # MySQLコンテナが起動している場合、データベースの存在を確認
    mysql_check=$(docker compose exec -T mysql mysql -uroot -ptest_root_pass -e "SHOW DATABASES LIKE 'ocgraph_%';" 2>/dev/null | grep -c "ocgraph_" || echo "0")
    if [ "$mysql_check" -gt 0 ]; then
        has_existing_data=true
    fi
fi

# 初期状態の場合は自動実行
if [ "$has_existing_data" = false ]; then
    echo "初期状態を検出しました。自動的にセットアップを開始します。"
    db_init_auto=true
fi

# local-secrets.phpの上書き確認（第1引数が空で、ファイルが存在する場合のみ）
if [ "$overwrite_secrets" = true ] && [ -z "$1" ] && [ -f "local-secrets.php" ]; then
    echo "=========================================="
    echo "local-secrets.phpの上書き確認"
    echo "=========================================="
    echo ""
    echo "local-secrets.php が既に存在します。"
    echo ""
    read -p "上書きしますか? (yes/no): " response
    echo ""

    # 小文字に変換
    response=$(echo "$response" | tr '[:upper:]' '[:lower:]')

    if [[ "$response" != "yes" && "$response" != "y" ]]; then
        echo "local-secrets.php の上書きをスキップします。"
        overwrite_secrets=false
    fi
fi

# DB・storage初期化の確認（第1引数が空で、既存データがある場合のみ）
if [ "$db_init_auto" = false ] && [ "$has_existing_data" = true ]; then
    echo "=========================================="
    echo "警告: データベースとストレージの初期化"
    echo "=========================================="
    echo ""
    echo "このスクリプトは以下の処理を実行します:"
    echo "  1. storage内の既存データをすべて削除"
    echo "  2. テンプレートファイルとサンプルDBをstorageにコピー"
    echo "  3. MySQLデータベースの初期化"
    echo "  4. composerの依存関係をインストール"
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

# storage内のファイル削除（Docker経由で実行）
echo "既存データを削除しています..."

# appコンテナの起動状態を確認
APP_WAS_STOPPED=0
if ! docker compose ps app 2>/dev/null | grep -q "Up"; then
    echo "appコンテナを一時的に起動します..."
    docker compose up -d app >/dev/null 2>&1
    APP_WAS_STOPPED=1
    sleep 2
fi

# Docker経由でファイル削除
docker compose exec -T app bash -c '
    rm -f storage/*/open_chat_sub_categories/subcategories.json
    rm -f storage/*/static_data_top/*.dat
    rm -f storage/*/static_data_recommend/*/*.dat
    rm -f storage/*/ranking_position/*/*.dat
    rm -f storage/*/ranking_position/*.dat
    rm -f storage/*/SQLite/statistics/*.db*
    rm -f storage/*/SQLite/ranking_position/*.db*
    rm -f storage/*/SQLite/ocgraph_sqlapi/*.db*
    rm -f storage/*.log
    rm -f storage/*/*/*.log

    find public/oc-img* -mindepth 1 -maxdepth 1 ! -name "default" ! -name "preview" -exec rm -rf {} + 2>/dev/null || true
    find public/oc-img*/preview -mindepth 1 -maxdepth 1 ! -name "default" -exec rm -rf {} + 2>/dev/null || true
'

# 一時起動したコンテナを停止
if [ $APP_WAS_STOPPED -eq 1 ]; then
    echo "appコンテナを停止します..."
    docker compose stop app >/dev/null 2>&1
fi

# local-secrets.phpの作成
if [ "$overwrite_secrets" = true ]; then
    echo "local-secrets.php を作成しています..."
    cat << 'EOF' > local-secrets.php
<?php

use App\Config\SecretsConfig;
use Shared\MimimalCmsConfig;
use App\Config\AppConfig;

AppConfig::$disableAds = false;
AppConfig::$disableStaticDataFile = false;

AppConfig::$isDevlopment = true;

// 環境変数でisMockEnvironmentを制御
if (function_exists('getenv') && ($isMockEnv = getenv('IS_MOCK_ENVIRONMENT')) !== false) {
    AppConfig::$isMockEnvironment = filter_var($isMockEnv, FILTER_VALIDATE_BOOLEAN);
} else {
    AppConfig::$isMockEnvironment = false;
}

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
    echo "local-secrets.php を作成しました。"
else
    echo "local-secrets.php の作成をスキップしました。"
fi
echo ""

# テンプレートファイルをコピー（Docker経由）
echo "テンプレートファイルをコピーしています..."
docker compose exec -T app bash -c '
    cp setup/template/static_data_top/* storage/ja/static_data_top/
    cp setup/template/static_data_top/* storage/tw/static_data_top/
    cp setup/template/static_data_top/* storage/th/static_data_top/
'

# スキーマファイルからSQLiteデータベースを生成（Docker経由）
echo "スキーマファイルからSQLiteデータベースを生成しています..."

# 各言語のディレクトリに対して処理
for lang in ja tw th; do
    echo "  ${lang} のデータベースを生成中..."

    # statistics.db を生成
    cat setup/schema/sqlite/statistics.sql | docker compose exec -T app sqlite3 "storage/${lang}/SQLite/statistics/statistics.db"

    # ranking_position.db を生成
    cat setup/schema/sqlite/ranking_position.sql | docker compose exec -T app sqlite3 "storage/${lang}/SQLite/ranking_position/ranking_position.db"

    # sqlapi.db を生成（jaのみ）
    if [ "$lang" == "ja" ]; then
        cat setup/schema/sqlite/sqlapi.sql | docker compose exec -T app sqlite3 "storage/${lang}/SQLite/ocgraph_sqlapi/sqlapi.db"
    fi
done

echo "SQLiteデータベースの生成が完了しました。"
echo ""

./setup/init-database.sh

rm -f docker/line-mock-api/data/hour_index.txt

composer install
