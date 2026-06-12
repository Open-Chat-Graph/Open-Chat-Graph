# AIエージェント対応（Agent-Ready）メモ

Cloudflareの「[Is Your Site Agent-Ready?](https://agentreadiness.dev/)」診断への対応と、
AI経由の流入を最大化するための実装内容・運用メモ。

方針: **MCPサーバー・公開APIは提供しない**が、AIエージェントがWebページ経由でデータを取得できるようにする。

## コード側で実装済み

| 診断項目 | 対応内容 |
| --- | --- |
| Content Signals in robots.txt | `Content-Signal: search=yes, ai-input=yes, ai-train=yes` を追加（[`RobotsController`](app/Controllers/Pages/RobotsController.php)） |
| Markdown Negotiation | `Accept: text/markdown` を含むGETリクエストにHTMLページをMarkdown変換して返す（[`AgentMarkdownResponder`](app/Services/Agent/AgentMarkdownResponder.php) / [`HtmlToMarkdownConverter`](app/Services/Agent/HtmlToMarkdownConverter.php)、`shared/bootstrap.php` で登録） |
| Link headers (RFC 8288) | トップページに `Link:` ヘッダーで llms.txt と sitemap.xml を案内（`sendAgentDiscoveryLinkHeader()` in [`functions.php`](app/Helpers/functions.php)） |
| （診断外・推奨） llms.txt | `/llms.txt` でAI向けサイト案内をMarkdown配信（[`LlmsTxtController`](app/Controllers/Pages/LlmsTxtController.php)） |

### Markdownネゴシエーションの仕様

- 対象: GET かつ `Accept` に `text/markdown` を含むリクエスト。`/admin` 配下・非HTMLレスポンス（JSON/text/plain）・非200は対象外
- レスポンス: `Content-Type: text/markdown; charset=UTF-8`、`X-Markdown-Tokens`（概算）、`Vary: Accept`
- キャッシュ: Markdownレスポンスは `Cache-Control: no-store` + `Cloudflare-CDN-Cache-Control: no-store`。
  CloudflareのエッジキャッシュはAcceptヘッダーをキャッシュキーに含めないため、HTMLと同一キーで
  Markdownがキャッシュされる事故（キャッシュ汚染）を防ぐ
- 通常のHTMLレスポンスには `checkLastModified()` 内で `Vary: Accept` を常時付与

### Content-Signal の判断

AI経由の流入最大化が目的のため全て `yes`（学習・AI回答利用・検索とも許可）。
学習利用だけ拒否したい場合は `ai-train=no` に変更する。

## Cloudflareダッシュボード側で必要な作業（コードでは対応不可）

1. **Cache Rule の追加（Markdownネゴシエーションを確実に動かすために必須）**
   - 条件: リクエストヘッダー `Accept` に `text/markdown` を含む
   - アクション: **Bypass cache**
   - 理由: エッジでHTMLのキャッシュHITになるとリクエストがオリジンへ届かず、Markdown変換がスキップされる
2. **DNSSEC の有効化**（診断の DNS-AID 項目で「DNSSEC was not validated」と指摘）
   - Cloudflare → DNS → Settings → DNSSEC を有効化し、表示される DSレコードをドメインレジストラに登録
3. （任意）Cloudflare の **Markdown for Agents**（AI Crawl Control 配下、利用可能なプランの場合）を有効化すると
   エッジ側でも変換される。オリジン実装と競合はしない（エッジで処理されればオリジンに届かないだけ）

## 意図的に対応しない診断項目

- API Catalog / OAuth・OIDC / OAuth Protected Resource / auth.md → 公開APIを提供しないため
- MCP Server Card / Agent Skills / WebMCP → MCP・エージェントツールを提供しないため
- x402 / MPP / UCP / ACP → コマースサイトではないため（診断でもスコア対象外）
- Web Bot Auth → ボット運営者（クローラー側）が公開するもので、コンテンツサイト側は対象外
