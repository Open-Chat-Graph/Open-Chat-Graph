<?php

/**
 * hreflang alternate links for fully-translated pages (ja / zh-TW / th).
 *
 * path() は現在の urlRoot (''/'/tw'/'/th') を除いた言語非依存パスを返すため、
 * 各言語の base URL に同じパスを付ければ対応する言語版 URL になる。
 * 3言語すべてに同一内容のページが存在するテンプレート（/oc など）でのみ include すること。
 */

use Shadow\Kernel\Dispatcher\ReceptionInitializer;

$cleanPath = strstr(path(), '?', true) ?: path();

// urlRoot => hreflang 言語コード（<html lang> と一致させる）
$hreflangRoots = [
    ''    => 'ja',
    '/tw' => 'zh-TW',
    '/th' => 'th',
];

foreach ($hreflangRoots as $root => $lang) {
    echo '<link rel="alternate" hreflang="' . $lang . '" href="'
        . ReceptionInitializer::getDomainAndHttpHost($root) . $cleanPath . '">' . "\n";
}

// x-default は日本語（ルート）を指す
echo '<link rel="alternate" hreflang="x-default" href="'
    . ReceptionInitializer::getDomainAndHttpHost('') . $cleanPath . '">' . "\n";
