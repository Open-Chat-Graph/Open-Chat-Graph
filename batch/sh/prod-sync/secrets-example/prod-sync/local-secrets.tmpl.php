<?php

// このファイルは setup.sh が envsubst で展開して local-secrets.php を生成する。
// 置換される変数: ${MYSQL_HOST} ${MYSQL_USER} ${MYSQL_PASS}
//
// 実機ではプライベートリポから自動取得された実テンプレ
// (キーやAPIシークレットを含む) が secrets/prod-sync/local-secrets.tmpl.php に配置される。
// このサンプルはフォーマットを示す目的のみ。

use App\Config\SecretsConfig;
use Shared\MimimalCmsConfig;
use App\Config\AppConfig;

AppConfig::$isDevlopment = true;
AppConfig::$isStaging = false;
AppConfig::$isMockEnvironment = false;
AppConfig::$phpBinary = 'php';
AppConfig::$disableStaticDataFile = false;

MimimalCmsConfig::$exceptionHandlerDisplayErrorTraceDetails = true;
MimimalCmsConfig::$errorPageHideDirectory = '/var/www/html';
MimimalCmsConfig::$errorPageDocumentRootName = 'html';

MimimalCmsConfig::$stringCryptorHkdfKey = 'REPLACE_ME';
MimimalCmsConfig::$stringCryptorOpensslKey = 'REPLACE_ME';

SecretsConfig::$adminApiKey = 'REPLACE_ME';
SecretsConfig::$googleRecaptchaSecretKey = 'REPLACE_ME';
SecretsConfig::$cloudFlareZoneId = '';
SecretsConfig::$cloudFlareApiKey = '';
SecretsConfig::$yahooClientId = 'REPLACE_ME';
SecretsConfig::$discordWebhookUrl = 'REPLACE_ME';

MimimalCmsConfig::$dbHost = '${MYSQL_HOST}';
MimimalCmsConfig::$dbUserName = '${MYSQL_USER}';
MimimalCmsConfig::$dbPassword = '${MYSQL_PASS}';

AppConfig::$dbName = [
    '' =>    'ocgraph_ocreview',
    '/tw' => 'ocgraph_ocreviewtw',
    '/th' => 'ocgraph_ocreviewth',
];

AppConfig::$rankingPositionDbName = [
    '' =>    'ocgraph_ranking',
    '/tw' => 'ocgraph_rankingtw',
    '/th' => 'ocgraph_rankingth',
];

AppConfig::$userLogDbName = [
    '' =>    'ocgraph_userlog',
    '/tw' => 'ocgraph_userlog',
    '/th' => 'ocgraph_userlog',
];

AppConfig::$commentDbName = [
    '' =>    'ocgraph_comment',
    '/tw' => 'ocgraph_commenttw',
    '/th' => 'ocgraph_commentth',
];
