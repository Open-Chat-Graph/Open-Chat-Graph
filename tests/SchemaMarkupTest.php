<?php

use PHPUnit\Framework\TestCase;
use Spatie\SchemaOrg\Schema;

class SchemaMarkupTest extends TestCase
{
    public function testDatasetSchemaMarkup()
    {
        $dataset = Schema::dataset()
            ->name('LINEオープンチャット「トークルーム名」のメンバー数の推移')
            ->description('オープンチャット「トークルーム名」のメンバー数の推移を日毎に記録しています。URLにアクセスすると、グラフで表示されます。アカウント作成済み(LINEでログイン)のユーザーはCSV形式でダウンロードできます。CSVファイルは日付、メンバー数の2カラムの全期間データになっています.')
            ->author(
                Schema::organization()
                    ->name('オプチャグラフ')
                    ->url('https://openchat-review.me')
            )
            ->keywords(['LINE', 'オープンチャット'])
            ->provider(
                Schema::organization()
                    ->name('LINE Corporation')
                    ->url('https://line.me/ja/')
            )
            ->license('http://opendefinition.org/licenses/cc-by')
            ->url('https://data.bodik.jp/dataset/p_45274_45271')
            ->datePublished('2018-10-09T19:59:32+09:00')
            ->dateModified('2023-05-23T00:18:50+09:00')
            ->distribution(
                Schema::dataDownload()
                    ->encodingFormat('CSV')
                    ->contentUrl('https://data.bodik.jp/dataset/p_45274_45271')
            );

        // JSON-LDのマークアップを生成
        $jsonLd = $dataset->toScript();

        var_dump("\n" . $jsonLd);

        $this->assertIsString($jsonLd);
    }
}
