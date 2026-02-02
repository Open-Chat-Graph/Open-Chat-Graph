#!/bin/bash
set -e

curl -fsSL https://claude.ai/install.sh | bash
echo 'export PATH="$HOME/.local/bin:$PATH"' >> ~/.bashrc && source ~/.bashrc
echo "✅ Claude CLIのインストールが完了しました。"
echo "実行コマンド: claude --dangerously-skip-permissions"

# GitHub CLI のインストール
curl -fsSL https://github.com/cli/cli/releases/download/v2.63.2/gh_2.63.2_linux_amd64.tar.gz | tar -xz
sudo mv gh_2.63.2_linux_amd64/bin/gh /usr/local/bin/
rm -rf gh_2.63.2_linux_amd64
echo "✅ GitHub CLIのインストールが完了しました。"

# MySQL設定ファイルのパーミッションを修正（Codespaces環境でworld-writable警告を防ぐ）
chmod 644 docker/mysql/server.cnf

echo "🚀 Codespaces環境のセットアップが完了しました！"

