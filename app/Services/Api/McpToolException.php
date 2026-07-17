<?php

declare(strict_types=1);

namespace App\Services\Api;

/**
 * MCP ツール実行エラー。
 * JSON-RPC のプロトコルエラーではなく、MCP 仕様に従い result.isError=true で
 * クライアント（AIモデル）に返す（McpServerService::handleSingle が捕捉する）。
 */
class McpToolException extends \RuntimeException {}
