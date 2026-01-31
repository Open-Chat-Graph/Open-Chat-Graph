#!/bin/bash
set -e

curl -fsSL https://claude.ai/install.sh | bash
echo 'export PATH="$HOME/.local/bin:$PATH"' >> ~/.bashrc && source ~/.bashrc
echo "✅ Claude CLIのインストールが完了しました。"
echo "実行コマンド: claude --dangerously-skip-permissions"

cat << 'EOF' > shared/secrets.php
<?php

if (
    isset($_SERVER['HTTP_HOST'], $_SERVER["HTTP_X_FORWARDED_HOST"])
    && str_contains($_SERVER["HTTP_X_FORWARDED_HOST"], 'github.dev')
) {
    $_SERVER['HTTP_HOST'] = $_SERVER["HTTP_X_FORWARDED_HOST"];
    $_SERVER['HTTPS'] = 'on';
}

EOF

# MySQL設定ファイルのパーミッションを修正（Codespaces環境でworld-writable警告を防ぐ）
chmod 644 docker/mysql/server.cnf

echo "🚀 Codespaces環境のセットアップが完了しました！"

