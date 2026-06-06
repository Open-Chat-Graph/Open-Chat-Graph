#!/usr/bin/env bash
# ============================================================
# 色リテラル検査
#
# デザイントークン（public/style/tokens.css）以外に「生の色指定」が
# 残っていないかを検査する。
#
#   - 移行済み（MIGRATED）のファイルに生色があれば exit 1（CI/手元の合格条件）
#   - 未移行領域は残数のレポートのみ（移行の進捗可視化）
#
# 検出パターン:
#   - 宣言値位置の 16進カラー（:#fff 等。#id セレクタは誤検知しないよう
#     コロン以降のみを対象とする）
#   - rgb( / rgba( / hsl( / hsla(
#   - data-URI SVG 内のエンコード済みカラー（%23xxxxxx）
#   ※ 色キーワード（white / black 等）は誤検知が多いため対象外。レビューで補完する。
#
# 対象外: admin/*（独自デザインシステム）, tokens.css（唯一の定義場所）,
#         frontend の theme 系ファイル（canvas 用 TS パレットの一次ソース）
#
# 使い方: make css-check  または  scripts/check-css-colors.sh
# ============================================================
set -u
cd "$(dirname "$0")/.."

HEX=':[^;{}]*#[0-9a-fA-F]{3,8}\b'
FUNC=':[^;{}]*\b(rgb|rgba|hsl|hsla)\('
DATAURI='%23[0-9a-fA-F]{3,8}'
PATTERN="($HEX|$FUNC|$DATAURI)"

# --- 移行済みファイル: ここに生色が現れたら違反 ---
# トークン化が完了した PR で随時追加していく
MIGRATED=(
  public/style/mvp.css
  public/style/mvpmin.css
  public/style/unset.css
  app/Views/components/head.php
  app/Views/components/oc_head.php
  app/Views/components/policy_head.php
)

fail=0
echo "== 移行済みファイルの違反チェック =="
for f in "${MIGRATED[@]}"; do
  if [ ! -f "$f" ]; then
    echo "✗ $f が存在しません（MIGRATED リストを更新してください）"
    fail=1
    continue
  fi
  hits=$(grep -nE "$PATTERN" "$f")
  if [ -n "$hits" ]; then
    echo "✗ $f に生色リテラル:"
    echo "$hits" | head -20 | sed 's/^/    /'
    fail=1
  else
    echo "✓ $f"
  fi
done

echo
echo "== 未移行領域の残数（参考） =="
css_count=$(grep -roE "$PATTERN" public/style --include='*.css' 2>/dev/null | grep -cv '^public/style/tokens\.css:' || true)
views_count=$(grep -roE "$PATTERN" app/Views --include='*.php' 2>/dev/null | grep -cv '^app/Views/admin/' || true)
frontend_count=$(grep -roE "$PATTERN" frontend/*/src --include='*.ts' --include='*.tsx' --include='*.css' 2>/dev/null | grep -cvE '/(theme|themeColors)[^/]*\.ts:' || true)
printf '  %-32s %s件\n' "public/style (tokens.css除く):" "$css_count"
printf '  %-32s %s件\n' "app/Views (admin除く):" "$views_count"
printf '  %-32s %s件\n' "frontend src (theme系除く):" "$frontend_count"

echo
if [ "$fail" -ne 0 ]; then
  echo "NG: 移行済みファイルに生の色指定が残っています。tokens.css の --c-* を参照してください。"
  exit 1
fi
echo "OK: 移行済みファイルはすべてトークン参照になっています。"
