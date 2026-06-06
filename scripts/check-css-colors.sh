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
# CSS用: 値が複数行に渡るケース（gradient等）も拾うため、コメント除去後に
# コロン位置へ依存しないパターンで判定する
CSS_PATTERN='(#[0-9a-fA-F]{3,8}\b|\brgba?\(|\bhsla?\(|%23[0-9a-fA-F]{3,8})'
strip_comments() {
  awk 'BEGIN{c=0}{line=$0;out="";while(1){if(c==0){i=index(line,"/*");if(i==0){out=out line;break};out=out substr(line,1,i-1);line=substr(line,i+2);c=1}else{j=index(line,"*/");if(j==0){line="";break};line=substr(line,j+2);c=0}}print out}'
}

# --- 移行済みファイル: ここに生色が現れたら違反 ---
# トークン化が完了した PR で随時追加していく
MIGRATED=(
  public/style/base/mvp.css
  public/style/base/mvpmin.css
  public/style/base/unset.css
  public/style/components/site_header.css
  public/style/components/site_footer.css
  public/style/components/search_form.css
  public/style/components/ads_element.css
  public/style/components/theme_discovery.css
  public/style/components/room_list.css
  public/style/components/recommend_list.css
  public/style/pages/ranking_ban.css
  public/style/pages/room_page.css
  public/style/pages/graph_page.css
  public/style/pages/live_ana.css
  public/style/pages/recommend_page.css
  public/style/pages/blog.css
  public/style/pages/oc-jump.css
  public/style/pages/terms.css
  public/style/react/SiteHeader.css
  public/style/react/OpenChatList.css
  public/style/react/OpenChat.css
  app/Views/components/head.php
  app/Views/components/oc_head.php
  app/Views/components/policy_head.php
  app/Views/components/theme_discovery.php
)

fail=0
echo "== 移行済みファイルの違反チェック =="
for f in "${MIGRATED[@]}"; do
  if [ ! -f "$f" ]; then
    echo "✗ $f が存在しません（MIGRATED リストを更新してください）"
    fail=1
    continue
  fi
  case "$f" in
    *.css) hits=$(strip_comments < "$f" | grep -nE "$CSS_PATTERN" | grep -vE '&#[0-9]+;' || true) ;;
    *)     hits=$(grep -nE "$PATTERN" "$f" | grep -vE '&#[0-9]+;' || true) ;;
  esac
  if [ -n "$hits" ]; then
    echo "✗ $f に生色リテラル:"
    echo "$hits" | head -20 | sed 's/^/    /'
    fail=1
  else
    echo "✓ $f"
  fi
done

echo
echo "== tokens.css 読み込み完全性チェック =="
# mvp.css / mvpmin.css はトークン参照のため、直接 <link> する View は
# 必ず tokens.css も読み込むこと（未読込だと var() が未定義になり配色が崩れる）
miss=0
while IFS= read -r f; do
  if ! grep -q "style/tokens\.css" "$f"; then
    echo "✗ $f は mvp(min).css を読むのに tokens.css を読み込んでいません"
    miss=1
    fail=1
  fi
done < <(grep -rl "style/base/mvp" app/Views --include='*.php')
[ "$miss" -eq 0 ] && echo "✓ mvp系を読む全Viewが tokens.css を読み込み済み"

echo
echo "== 未移行領域の残数（参考） =="
css_count=$(for c in $(find public/style -name '*.css' ! -path '*/tokens.css'); do strip_comments < "$c" | grep -nE "$CSS_PATTERN" | sed "s|^|$c:|"; done 2>/dev/null | grep -vE '&#[0-9]+;' | wc -l)
views_count=$(grep -rnE "$PATTERN" app/Views --include='*.php' 2>/dev/null | grep -v '^app/Views/admin/' | grep -vE '&#[0-9]+;' | wc -l)
frontend_count=$(grep -rnE "$PATTERN" frontend/*/src --include='*.ts' --include='*.tsx' --include='*.css' 2>/dev/null | grep -vE '/(theme|themeColors)[^/]*\.ts:' | wc -l)
printf '  %-32s %s件\n' "public/style (tokens.css除く):" "$css_count"
printf '  %-32s %s件\n' "app/Views (admin除く):" "$views_count"
printf '  %-32s %s件\n' "frontend src (theme系除く):" "$frontend_count"

echo
if [ "$fail" -ne 0 ]; then
  echo "NG: 違反があります（生色リテラル または tokens.css 読み込み漏れ）。"
  exit 1
fi
echo "OK: 移行済みファイルはすべてトークン参照になっています。"
