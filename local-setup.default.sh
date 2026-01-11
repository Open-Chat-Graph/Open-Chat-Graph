#!/bin/bash

# Display warning message
echo "=========================================="
echo "WARNING: Database Deletion"
echo "=========================================="
echo ""
echo "This script will perform the following actions:"
echo "  1. Overwrite local-secrets.php configuration file"
echo "  2. Copy template files to storage directories"
echo "  3. Install composer dependencies"
echo ""
echo "IMPORTANT: Running this script will DELETE ALL existing database data."
echo ""
read -p "Do you want to continue? (yes/no): " response
echo ""

# Convert response to lowercase
response=$(echo "$response" | tr '[:upper:]' '[:lower:]')

# Check response
if [[ "$response" != "yes" && "$response" != "y" ]]; then
    echo "Setup cancelled."
    exit 0
fi

echo "Proceeding with setup..."
echo ""

cat << 'EOF' > local-secrets.php
<?php

use App\Config\SecretsConfig;
use Shared\MimimalCmsConfig;
use App\Config\AppConfig;

AppConfig::$disableAds = false;
AppConfig::$disableStaticDataFile = false;

AppConfig::$isDevlopment = true;

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

cp storage/ja/static_data_top/required_storage_file_template/* storage/ja/static_data_top/
cp storage/ja/static_data_top/required_storage_file_template/* storage/tw/static_data_top/
cp storage/ja/static_data_top/required_storage_file_template/* storage/th/static_data_top/

# Copy example SQLite database files to appropriate locations
echo "Copying example SQLite database files..."
# Japanese (ja)
cp -r storage/ja/SQLite/example/statistics/* storage/ja/SQLite/statistics/
cp -r storage/ja/SQLite/example/ranking_position/* storage/ja/SQLite/ranking_position/
cp -r storage/ja/SQLite/example/ocgraph_sqlapi/* storage/ja/SQLite/ocgraph_sqlapi/
# Traditional Chinese (tw)
cp -r storage/ja/SQLite/example/statistics/* storage/tw/SQLite/statistics/
cp -r storage/ja/SQLite/example/ranking_position/* storage/tw/SQLite/ranking_position/
# Thai (th)
cp -r storage/ja/SQLite/example/statistics/* storage/th/SQLite/statistics/
cp -r storage/ja/SQLite/example/ranking_position/* storage/th/SQLite/ranking_position/
echo "SQLite database files copied successfully."
echo ""

composer install
