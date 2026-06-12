# AIエージェント対応（Agent-Ready）メモ

Cloudflareの「[Is Your Site Agent-Ready?](https://agentreadiness.dev/)」診断への対応と、
AI経由の流入を最大化するための実装内容・運用メモ。

方針: **MCPサーバー・公開APIは提供しない**が、AIエージェントがWebページ経由でデータを取得できるようにする。

## コード側で実装済み

| 診断項目 | 対応内容 |
| --- | --- |
| Content Signals in robots.txt | `Content-Signal: search=yes, ai-input=yes, ai-train=yes` を追加（[`RobotsController`](app/Controllers/Pages/RobotsController.php)） |
| Markdown Negotiation | `Accept: text/markdown` を含むGETリクエストにHTMLページをMarkdown変換して返す。`Route::run()` に渡す全ルート共通middleware（[`AgentMarkdownNegotiation`](app/Middleware/AgentMarkdownNegotiation.php)）が ViewInterface の実装を [`AgentMarkdownView`](app/Services/Agent/AgentMarkdownView.php) へ差し替え、render() 時に [`HtmlToMarkdownConverter`](app/Services/Agent/HtmlToMarkdownConverter.php) で変換する |
| Link headers (RFC 8288) | トップページに `Link:` ヘッダーで llms.txt と sitemap.xml を案内（`sendAgentDiscoveryLinkHeader()` in [`functions.php`](app/Helpers/functions.php)） |
| （診断外・推奨） llms.txt | `/llms.txt` でAI向けサイト案内をMarkdown配信（[`LlmsTxtController`](app/Controllers/Pages/LlmsTxtController.php)） |

### Markdownネゴシエーションの仕様

対象はGETで、コントローラーが `view()` を返す全ページ（`/admin` 配下・非200エラーページはHTMLのまま。
`response()`のJSON APIやecho直書きルート(robots.txt等)はViewを通らないため影響なし）。
トリガーは2種類あり、**キャッシュ戦略が異なる**:

| トリガー | CDNキャッシュ | 用途 |
| --- | --- | --- |
| `?md=1` クエリパラメータ（例: `/oc/123?md=1`） | **効く**（URLでキャッシュキーが分かれるため、HTMLと同じ Last-Modified / 304 / `Cloudflare-CDN-Cache-Control: max-age=3600` をそのまま適用） | 推奨。llms.txt でもこちらを案内 |
| `Accept: text/markdown` ヘッダー（HTMLと同一URL） | **効かない**（CloudflareはAcceptをキャッシュキーに含めないため、キャッシュ汚染防止に no-store・毎回オリジン処理。304も返さない） | Cloudflare診断のMarkdown Negotiation対応・エージェントの標準的なネゴシエーション |

- レスポンス: `Content-Type: text/markdown; charset=UTF-8`、`X-Markdown-Tokens`（概算）、`Vary: Accept`
- 通常のHTMLレスポンスには `checkLastModified()` 内で `Vary: Accept` を常時付与
- **`?md=1` の発見経路**: 独自パラメータなのでエージェントが自発的には知り得ない。
  (1) 全ページの通常レスポンスに `Link: <同URL?md=1>; rel="alternate"; type="text/markdown"` ヘッダーを付与、
  (2) llms.txt に取得方法として記載、の2経路で機械的に発見できるようにしている。
  ただし現実のAIクローラーの標準動作は `Accept: text/markdown` のため、
  Accept経由のオリジン負荷を消すには下記Transform Rule（案A）の設定が本命
- Accept単独リクエストで `checkLastModified()` の304を返さないのは、Markdown変種が no-store で
  クライアントがキャッシュを持たないため（304で exit するとMarkdown本文を一度も返せなくなる）

### Content-Signal の判断

AI経由の流入最大化が目的のため全て `yes`（学習・AI回答利用・検索とも許可）。
学習利用だけ拒否したい場合は `ai-train=no` に変更する。

## Cloudflareダッシュボード側で必要な作業（コードでは対応不可）

1. **Acceptヘッダー向けのルール追加（Markdownネゴシエーションを確実に動かすために必須）**

   エッジでHTMLのキャッシュHITになるとリクエストがオリジンへ届かず、Markdown変換がスキップされるため、
   どちらか一方を設定する:

   - **案A（推奨・オリジン負荷も減る）: Transform Rule（URL Rewrite）**
     - 条件: リクエストヘッダー `Accept` に `text/markdown` を含む、かつクエリに `md=1` が無い
     - アクション: クエリ文字列に `md=1` を付与するRewrite
     - 効果: Accept経由のリクエストも内部的に `?md=1` 扱いになり、キャッシュキーが分かれて
       **Markdownもエッジキャッシュされる**（オリジン直撃は最初の1回だけ）
   - **案B（シンプル）: Cache Rule で Bypass cache**
     - 条件: リクエストヘッダー `Accept` に `text/markdown` を含む
     - アクション: Bypass cache
     - 注意: Accept経由のMarkdownリクエストは毎回オリジン処理になる（`?md=1` 経由は案Bでもキャッシュされる）
2. **DNSSEC の有効化**（診断の DNS-AID 項目で「DNSSEC was not validated」と指摘）
   - Cloudflare → DNS → Settings → DNSSEC を有効化し、表示される DSレコードをドメインレジストラに登録
3. （任意）Cloudflare の **Markdown for Agents**（AI Crawl Control 配下、利用可能なプランの場合）を有効化すると
   エッジ側でも変換される。オリジン実装と競合はしない（エッジで処理されればオリジンに届かないだけ）

## 意図的に対応しない診断項目

- API Catalog / OAuth・OIDC / OAuth Protected Resource / auth.md → 公開APIを提供しないため
- MCP Server Card / Agent Skills / WebMCP → MCP・エージェントツールを提供しないため
- x402 / MPP / UCP / ACP → コマースサイトではないため（診断でもスコア対象外）
- Web Bot Auth → ボット運営者（クローラー側）が公開するもので、コンテンツサイト側は対象外
