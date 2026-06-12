<?php

declare(strict_types=1);

namespace App\Controllers\Pages;

use App\Config\AppConfig;

/**
 * /llms.txt — AIエージェント・LLM向けのサイト案内（https://llmstxt.org/ 形式）
 *
 * AI経由の流入最大化のため、サイト概要・主要ページ・データの取得方法をMarkdownで公開する。
 * robots.txt と同様にルートドメイン（日本語）のみで配信し、台湾・タイ版へのリンクを含める。
 */
class LlmsTxtController
{
    public function index()
    {
        header('Content-Type: text/markdown; charset=UTF-8');

        echo $this->build();
        exit;
    }

    private function build(): string
    {
        $domain = AppConfig::$siteDomain;

        $categoryLines = '';
        foreach (AppConfig::OPEN_CHAT_CATEGORY[''] as $name => $id) {
            if ($id === 0) {
                continue;
            }
            $categoryLines .= "- [{$name}]({$domain}/ranking/{$id})\n";
        }
        $categoryLines = rtrim($categoryLines);

        return <<<MD
        # オプチャグラフ (OpenChat Graph)

        > LINEオープンチャットのメンバー数統計・ランキング・検索サイト。LINE公式オープンチャットサイトを毎時クロールし、全ルームのメンバー数推移・急上昇ランキング・公式ランキングの掲載状況を記録して公開している。

        - 対応地域: 日本 ({$domain}/)、台湾 ({$domain}/tw)、タイ ({$domain}/th)
        - データ更新: 毎時（各ルームのメンバー数とランキング順位を1時間ごとに記録）
        - AIエージェント向け: 各ページはURLに `?md=1` を付ける（推奨・CDNキャッシュで高速）か、リクエストヘッダー `Accept: text/markdown` でMarkdown版を返す
        - 運営・ソースコード: https://github.com/Open-Chat-Graph/Open-Chat-Graph （MITライセンス）

        ## 主要ページ

        - [トップ]({$domain}/): 急上昇中のルーム・おすすめ・公式ルーム一覧
        - [ランキング]({$domain}/ranking): 全ルームのメンバー数・増加数ランキング。キーワード検索は `{$domain}/ranking?keyword=検索語`
        - [ルーム個別ページ]({$domain}/oc/{id}): 各オープンチャットの詳細・メンバー数推移グラフ・統計分析（{id} は本サイトの内部ID。一覧は sitemap.xml を参照）
        - [新規登録ルーム]({$domain}/recently-registered): 最近オプチャグラフに登録されたルーム一覧
        - [タグ別一覧]({$domain}/recommend/{タグ名}): タグごとのルーム一覧
        - [全ルーム統計]({$domain}/labs/all-room-stats): 全ルームを集計した統計データ
        - [サイトについて]({$domain}/policy): サイト概要・データの取り扱い

        ## カテゴリー別ランキング

        {$categoryLines}

        ## データ取得

        - [サイトマップ]({$domain}/sitemap.xml): 全ページのURL一覧（ルーム個別ページを含む）
        - 各ページはMarkdownとして取得可能（HTMLのスクレイピング不要）。URLに `?md=1` を付ける方法（例: `{$domain}/oc/123?md=1`）が推奨でCDNキャッシュが効いて高速。`Accept: text/markdown` ヘッダーでも同じ内容を返す
        - [データAPI（申請制）](https://github.com/Open-Chat-Graph/Open-Chat-Graph/blob/main/API_README.md): 収集データへの読み取り専用SQL API。利用にはX (Twitter) [@openchat_graph](https://x.com/openchat_graph) のDMでの申請が必要
        - 公開API・MCPサーバーは提供していない。データの参照は上記のHTML/Markdownページ経由で行うこと

        ## 言語別サイト

        - [台湾版 (繁體中文)]({$domain}/tw)
        - [タイ版 (ภาษาไทย)]({$domain}/th)
        MD;
    }
}
