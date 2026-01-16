#!/bin/bash
# エラーログチェックスクリプト
# storage/exception.logが存在する場合、内容を出力してエラーで終了する

set -e

# 色付き出力
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

ERROR_LOG="./storage/exception.log"

echo -e "${YELLOW}=========================================${NC}"
echo -e "${YELLOW}エラーログチェック${NC}"
echo -e "${YELLOW}=========================================${NC}"
echo ""

if [ -f "$ERROR_LOG" ]; then
    echo -e "${RED}エラーログが見つかりました: ${ERROR_LOG}${NC}"
    echo ""
    echo -e "${RED}========== エラーログの内容 ==========${NC}"
    cat "$ERROR_LOG"
    echo -e "${RED}======================================${NC}"
    echo ""
    echo -e "${RED}エラーが発生しています。詳細は上記のログを確認してください。${NC}"
    exit 1
else
    echo -e "${GREEN}エラーログは見つかりませんでした（エラー0件）${NC}"
    echo -e "${GREEN}✓ テスト成功${NC}"
    exit 0
fi
