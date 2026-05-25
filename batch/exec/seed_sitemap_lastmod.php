<?php

/**
 * oc_sitemap_lastmod を一度きり seed するスクリプト。
 *
 * 使い方 (locale ごとに 1 回ずつ):
 *   php batch/exec/seed_sitemap_lastmod.php        # JP
 *   php batch/exec/seed_sitemap_lastmod.php /tw    # TW
 *   php batch/exec/seed_sitemap_lastmod.php /th    # TH
 *
 * JP: narrative を全 /oc/{id} に載せた 2026-05-24 を floor にして lastmod を底上げし、
 *     Google の再クロールの波を起こす (= GREATEST(updated_at, deploy))。
 * TW/TH: narrative 未提供のため強制 bump しない。MINIMUM_LASTMOD を floor にするだけ。
 *
 * 以後の維持は SyncOpenChat::dailyTask() の refreshLastmod() が担う。
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Models\Repositories\DB;
use App\Services\SitemapGenerator;
use Shared\MimimalCmsConfig;

// narrative (#259) を全 JP /oc/{id} へデプロイした日時
const NARRATIVE_DEPLOY_AT = '2026-05-24 19:00:00';

set_time_limit(3600);

if (isset($argv[1]) && $argv[1]) {
    MimimalCmsConfig::$urlRoot = $argv[1];
}

// JP は narrative デプロイ日を floor、TW/TH は従来の最小日時を floor
$floor = MimimalCmsConfig::$urlRoot === ''
    ? NARRATIVE_DEPLOY_AT
    : SitemapGenerator::MINIMUM_LASTMOD;

DB::connect();

$sql =
    "INSERT INTO oc_sitemap_lastmod (open_chat_id, lastmod, member_snapshot)
     SELECT id, GREATEST(updated_at, :floor), member
       FROM open_chat
     ON DUPLICATE KEY UPDATE
         lastmod         = VALUES(lastmod),
         member_snapshot = VALUES(member_snapshot)";

$rows = DB::execute($sql, ['floor' => $floor])->rowCount();

printf(
    "seeded oc_sitemap_lastmod for locale='%s' floor='%s' (affected=%d)\n",
    MimimalCmsConfig::$urlRoot === '' ? 'ja' : ltrim(MimimalCmsConfig::$urlRoot, '/'),
    $floor,
    $rows
);
