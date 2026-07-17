<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Services\Api\McpServerService;

/**
 * 公開 MCP エンドポイント /mcp（POST・認証なし）
 *
 * MCP (Model Context Protocol) の Streamable HTTP トランスポート。SSE ストリームは
 * 提供せず、常に単一の application/json レスポンスを返すステートレス実装
 * （セッションIDは発行しない）。プロトコル処理・ツール実装・レートリミットは
 * McpServerService 側。使い方は API_README.md / https://openchat-review.me/llms.txt。
 *
 * 注意: Cloudflare の bot チャレンジ除外（firewall_custom `bot` ルールの
 * `not starts_with(http.request.uri.path, "/mcp")`）とセットで公開されている。
 * パスを変える場合は CF ルール（oc-infra）も対で更新すること。
 */
class McpApiController
{
    function index(McpServerService $mcp)
    {
        // MCP クライアントはブラウザ外が主だが、Web ベースのクライアント向けに CORS を許可
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Accept, Authorization, Mcp-Session-Id, MCP-Protocol-Version');
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            exit;
        }

        header('Content-Type: application/json; charset=UTF-8');

        // GET は SSE 非対応のため 405（MCP 仕様）。人間向けに案内だけ返す
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
}
