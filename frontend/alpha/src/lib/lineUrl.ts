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
