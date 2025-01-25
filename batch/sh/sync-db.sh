#!/bin/bash

./import-mysql-from-server.sh

# 変数をダミーで連想配列に初期化（後で import-mysql-from-server.env で上書きされる）
declare -A CONFIG_VARS=(
    [REMOTE_SERVER]=""
    [REMOTE_USER]=""
    [REMOTE_PORT]=""
    [REMOTE_KEY]=""
    [REMOTE_STORAGE_DIR]=""
    [LOCAL_STORAGE_DIR]=""
)

# 外部ファイルを読み込む
if [ -f ./import-mysql-from-server.env ]; then
    source ./import-mysql-from-server.env
else
    echo "Error: import-mysql-from-server.env ファイルが見つかりません。" >&2
    exit 1
fi

# 必須変数がすべて設定されているかチェック
for VAR in "${!CONFIG_VARS[@]}"; do
    if [ -z "${CONFIG_VARS[$VAR]}" ]; then
        echo "Error: 必須変数 $VAR が設定されていません。" >&2
        exit 1
    fi
done

declare -a LANG_CODE=(
    "ja"
    "tw"
    "th"
)

for code in "${LANG_CODE[@]}"; do
    FILE_NAME="${code}/*"
    echo "SCPでstorageファイルを取得中: ${FILE_NAME}"
    scp -P "${CONFIG_VARS[REMOTE_PORT]}" -i "${CONFIG_VARS[REMOTE_KEY]}" -r "${CONFIG_VARS[REMOTE_USER]}@${CONFIG_VARS[REMOTE_SERVER]}:${CONFIG_VARS[REMOTE_STORAGE_DIR]}/$FILE_NAME" "${CONFIG_VARS[LOCAL_STORAGE_DIR]}/${code}/"
done

# 終了メッセージ
echo "全てのインポートが完了しました。"
