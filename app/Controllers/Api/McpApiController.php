<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Services\Api\McpServerService;
use App\Views\Schema\PageBreadcrumbsListSchema;

/**
 * 公開 MCP エンドポイント /mcp（認証なし）
 *
 * - POST: MCP (Model Context Protocol) の Streamable HTTP トランスポート。SSE ストリームは
 *   提供せず、常に単一の application/json レスポンスを返すステートレス実装
 *   （セッションIDは発行しない）。プロトコル処理・ツール実装は McpServerService 側。
 * - GET（ブラウザ）: 同じ URL を人間が開いたときは一般ユーザー向けの案内ページを表示する
 *   （「AIに教えるURL」と「人間が読む案内」を1つのURLに統一するため）。
 * - GET（非ブラウザ）: SSE 非対応のため 405（MCP 仕様）。
 *
 * 使い方は /mcp（案内ページ）・API_README.md・https://openchat-review.me/llms.txt。
 *
 * 注意: Cloudflare の bot チャレンジ除外（firewall_custom `bot` ルールの
 * `not starts_with(http.request.uri.path, "/mcp")`）とセットで公開されている。
 * パスを変える場合は CF ルール（oc-infra）も対で更新すること。
 */
class McpApiController
{
    function index(McpServerService $mcp, PageBreadcrumbsListSchema $breadcrumbsShema)
    {
        // ブラウザからの GET は人間向けの案内ページ（CORS 等の API ヘッダーは不要）
        if (
            $_SERVER['REQUEST_METHOD'] === 'GET'
            && str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'text/html')
        ) {
            return $this->guidePage($breadcrumbsShema);
        }

        // MCP クライアントはブラウザ外が主だが、Web ベースのクライアント向けに CORS を許可
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Accept, Authorization, Mcp-Session-Id, MCP-Protocol-Version');
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            exit;
        }

        header('Content-Type: application/json; charset=UTF-8');

        // GET は SSE 非対応のため 405（MCP 仕様）。機械向けに案内だけ返す
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Allow: POST, OPTIONS');
            http_response_code(405);
            echo json_encode([
                'message' => 'This is the OpenChat Graph MCP endpoint (Streamable HTTP, JSON responses only). '
                    . 'Connect with an MCP client via POST. Docs: https://github.com/Open-Chat-Graph/Open-Chat-Graph/blob/main/API_README.md',
            ], JSON_UNESCAPED_SLASHES);
            exit;
        }

        $body = json_decode(file_get_contents('php://input') ?: '', true);
        if ($body === null) {
            http_response_code(400);
            echo json_encode([
                'jsonrpc' => '2.0',
                'id' => null,
                'error' => ['code' => -32700, 'message' => 'Parse error: invalid JSON'],
            ]);
            exit;
        }

        $response = $mcp->handleMessage($body);

        // 通知のみ（レスポンス不要）は 202 Accepted
        if ($response === null) {
            http_response_code(202);
            exit;
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * 一般ユーザー向けの MCP 案内ページ（ブログと同じトンマナ）。
     * 「AIに教えるURL」をそのままブラウザで開いた人にも意味が通るようにする。
     */
    private function guidePage(PageBreadcrumbsListSchema $breadcrumbsShema)
    {
        $_css = ['components/site_header', 'components/site_footer', 'pages/blog'];

        $_meta = meta()->setTitle('AIにオプチャグラフを接続する（MCP）｜ChatGPT・Claude対応');
        $desc = 'ChatGPTやClaudeなどのAIチャットから、オープンチャット24万室の統計データを直接調べられるMCPサーバーの使い方。設定はURLを1行貼るだけ、登録・申請・料金は不要です。';
        $_meta->setDescription($desc)->setOgpDescription($desc);

        $_breadcrumbsShema = $breadcrumbsShema->generateSchema('AI連携（MCP）の使い方');

        return view('mcp_guide_content', compact('_meta', '_css', '_breadcrumbsShema'));
    }
}
