#!/bin/bash
set -e

# モックAPI切り替え（環境変数 MOCK_API_TYPE により index.php が適切な実装を読み込む）
if [ "${MOCK_API_TYPE}" = "fixed" ]; then
    echo "Using fixed mock API (fixed.php)"
else
    echo "Using dynamic mock API (dynamic.php)"
fi

# Apacheを起動
exec apache2-foreground
