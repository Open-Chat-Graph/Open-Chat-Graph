#!/bin/bash

# SQLダンプファイルからスキーマ定義のみを抽出するスクリプト

DUMP_DIR="/home/user/oc-review-dev/batch/sh/sqldump"
OUTPUT_DIR="/home/user/oc-review-dev/database/schema"

# 出力ディレクトリ作成
mkdir -p "$OUTPUT_DIR"

echo "SQLスキーマ抽出開始..."
echo "================================"

# 各ダンプファイルからスキーマを抽出
for dump_file in "$DUMP_DIR"/*.sql; do
    db_name=$(basename "$dump_file" .sql)
    output_file="$OUTPUT_DIR/${db_name}_schema.sql"

    echo "処理中: $db_name"

    # スキーマ部分のみを抽出（CREATE TABLE、ALTER TABLE、CREATE INDEXなど）
    # データのINSERT文は除外
    sed -n '
        /^DROP TABLE/p
        /^CREATE TABLE/,/^) ENGINE=/p
        /^ALTER TABLE/p
        /^CREATE.*INDEX/p
        /^CREATE DATABASE/p
        /^USE /p
    ' "$dump_file" > "$output_file"

    # データベース名を先頭に追加
    {
        echo "-- Schema for database: $db_name"
        echo "-- Extracted from: $(basename $dump_file)"
        echo "-- Generated: $(date)"
        echo ""
        echo "CREATE DATABASE IF NOT EXISTS \`$db_name\` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
        echo "USE \`$db_name\`;"
        echo ""
        cat "$output_file"
    } > "${output_file}.tmp"

    mv "${output_file}.tmp" "$output_file"

    file_size=$(du -h "$output_file" | cut -f1)
    echo "  → $output_file ($file_size)"
done

echo ""
echo "================================"
echo "スキーマ抽出完了"
echo "出力ディレクトリ: $OUTPUT_DIR"
echo ""
echo "生成されたファイル:"
ls -lh "$OUTPUT_DIR"
