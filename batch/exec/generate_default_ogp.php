<?php

/**
 * 言語別デフォルトOGP画像（public/assets/ogp*.png）の再生成 CLI。
 *
 * トップページ等の og:image と動的OGカード生成失敗時のフォールバックに使う静的画像を、
 * SiteOgpImageGenerator（濃紺グラデ＋装飾折れ線＋サイト名/タグライン）で全ロケール分書き出す。
 * 生成物はリポジトリにコミットする運用（デプロイ時には実行しない）。デザイン・文言
 * （translation.json のサイト名/タグライン）を変えたらこれを回して画像を更新する。
 *
 * 使い方:
 *   php batch/exec/generate_default_ogp.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Config\AppConfig;
use App\Services\OgImage\SiteOgpImageGenerator;
use Shared\MimimalCmsConfig;

foreach (AppConfig::DEFAULT_OGP_IMAGE_FILE_PATHS as $urlRoot => $path) {
    MimimalCmsConfig::$urlRoot = $urlRoot;

    /** @var SiteOgpImageGenerator $generator */
    $generator = app(SiteOgpImageGenerator::class);
    $png = $generator->renderPng();
    if ($png === null) {
        fwrite(STDERR, "この環境では生成できません（GD/FreeType/フォントを確認）\n");
        exit(1);
    }

    $file = AppConfig::ROOT_PATH . 'public/' . $path;
    file_put_contents($file, $png);
    echo 'wrote ' . $path . ' (' . strlen($png) . " bytes)\n";
}
