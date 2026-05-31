/**
 * LINE オープンチャットの「LINEで開く」URL生成。
 *
 * emid（招待用の内部ID）から LINE のグループ招待リンクを組み立てる。
 * 未登録（オプチャグラフに未収録）の新規部屋は詳細ページが無いので、
 * この導線だけで LINE アプリ/サイトへ送る。
 */
const LINE_INVITE_BASE = 'https://line.me/ti/g2/'

export function lineOpenUrl(emid: string | null | undefined): string {
  if (!emid) return ''
  return LINE_INVITE_BASE + encodeURIComponent(emid)
}

/**
 * LINE オープンチャットの「カバー（公開ページ）」URL生成。
 *
 * 未登録（オプチャグラフに未収録）の新規部屋は emid からこの cover URL でしか開けない
 * （招待リンクではなく公開カバーページに送る）。検索由来の発見部屋の導線に使う。
 */
export function coverUrl(emid: string | null | undefined): string {
  if (!emid) return ''
  return `https://openchat.line.me/jp/cover/${encodeURIComponent(emid)}?utm_source=line-openchat-seo&utm_medium=category&utm_campaign=default`
}
