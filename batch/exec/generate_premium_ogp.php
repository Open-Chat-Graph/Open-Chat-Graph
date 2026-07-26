<?php

/**
 * 通用口ページ（/admin/disable-ads）専用OGP画像（public/assets/ogp-premium.png）の再生成 CLI。
 *
 * PremiumOgpImageGenerator（黒地＋下端から漏れるオレンジの光＋金の折れ線）で1枚だけ書き出す。
 * ページが ja 専用なのでロケール展開はしない。生成物はリポジトリにコミットする運用
 * （デプロイ時には実行しない）。デザイン・文言を変えたらこれを回して画像を更新する。
 *
 * 使い方:
 *   php batch/exec/generate_premium_ogp.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Config\AppConfig;
use App\Services\OgImage\PremiumOgpImageGenerator;

/** @var PremiumOgpImageGenerator $generator */
$generator = app(PremiumOgpImageGenerator::class);
$png = $generator->renderPng();
if ($png === null) {
    fwrite(STDERR, "この環境では生成できません（GD/FreeType/フォントを確認）\n");
    exit(1);
}

$path = AppConfig::OGP_PREMIUM_IMAGE_FILE_PATH;
file_put_contents(AppConfig::ROOT_PATH . 'public/' . $path, $png);
echo 'wrote ' . $path . ' (' . strlen($png) . " bytes)\n";
