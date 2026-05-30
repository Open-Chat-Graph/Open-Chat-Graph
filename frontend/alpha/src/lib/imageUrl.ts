/**
 * オープンチャット画像のURL生成。
 *
 * サーバー(app/Helpers/functions.php の imgUrl()/imgPreviewUrl())に合わせ、
 * LINE公式CDN(obs.line-scdn.net)を直接指す。API が返す `local_img_url` は
 * OBS のハッシュ。すでに絶対URLならそのまま使う。
 */
const LINE_IMG_URL = 'https://obs.line-scdn.net/'
const LINE_IMG_PREVIEW_PATH = '/preview'

function isFullUrl(value: string): boolean {
  return /^https?:\/\//i.test(value)
}

/** 通常画像URL */
export function imgUrl(localImgUrl: string | undefined | null): string {
  if (!localImgUrl) return ''
  if (isFullUrl(localImgUrl)) return localImgUrl
  return LINE_IMG_URL + localImgUrl
}

/** プレビュー(軽量)画像URL */
export function imgPreviewUrl(localImgUrl: string | undefined | null): string {
  if (!localImgUrl) return ''
  if (isFullUrl(localImgUrl)) return localImgUrl
  return LINE_IMG_URL + localImgUrl + LINE_IMG_PREVIEW_PATH
}
