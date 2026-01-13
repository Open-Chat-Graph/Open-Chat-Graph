#!/bin/bash
set -e

# CRON環境変数の確認
if [ -z "${CRON}" ] || [ "${CRON}" != "1" ]; then
    echo "CRON is not enabled or not set to 1. Cleaning up any existing cron jobs..."

    # 既存のcronジョブを削除
    if [ -f /etc/cron.d/openchat-crawling ]; then
        rm -f /etc/cron.d/openchat-crawling
        echo "Removed /etc/cron.d/openchat-crawling"
    fi

    # cronデーモンが起動していれば停止
    if service cron status >/dev/null 2>&1; then
        service cron stop
        echo "Cron daemon stopped"
    fi

    echo "Cron cleanup completed"
    exit 0
fi

echo "Setting up cron jobs..."

# crontabファイルを作成
cat > /etc/cron.d/openchat-crawling << 'CRONTAB'
# オープンチャットクローリング（多言語対応）
# 毎時30分: 日本語 (引数なし)
30 * * * * www-data cd /var/www/html && /usr/local/bin/php batch/cron/cron_crawling.php >> /var/log/cron.log 2>&1

# 毎時35分: 繁体字中国語 (引数/tw)
35 * * * * www-data cd /var/www/html && /usr/local/bin/php batch/cron/cron_crawling.php /tw >> /var/log/cron.log 2>&1

# 毎時40分: タイ語 (引数/th)
40 * * * * www-data cd /var/www/html && /usr/local/bin/php batch/cron/cron_crawling.php /th >> /var/log/cron.log 2>&1

CRONTAB

# crontabファイルのパーミッション設定
chmod 0644 /etc/cron.d/openchat-crawling

# cronログファイルを作成
touch /var/log/cron.log
chown www-data:www-data /var/log/cron.log

echo "Cron jobs configured:"
cat /etc/cron.d/openchat-crawling

# cronデーモンを起動
service cron start

echo "Cron daemon started successfully"
