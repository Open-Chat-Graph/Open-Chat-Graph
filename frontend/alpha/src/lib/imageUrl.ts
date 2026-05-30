/**
 * 画像URL生成ユーティリティ
 * PHPのimgUrl()とimgPreviewUrl()を移植
 */

// デフォルト画像のハッシュリスト
const DEFAULT_OPENCHAT_IMG_URL_HASH = [
  '2AtTNcODU67',
  '3SXDWf2OXqcY',
  '2pPOKy2ldZWl',
  '3y6jWliwCg1',
  '4DceVI1KwU1k',
]

// 画像パス（固定）
const OPENCHAT_IMG_PATH = 'oc-img'
const OPENCHAT_IMG_PREVIEW_PATH = 'preview'
const OPENCHAT_IMG_PREVIEW_SUFFIX = '_p'

/**
 * IDからサブディレクトリパスを生成
 * PHPのfilePathNumById()と同等
 */
function getSubDir(id: number): string {
  const thousands = Math.floor(id / 1000)
  return `${thousands}`
}

/**
 * 画像パスを生成
 * PHPのgetImgPath()と同等
 */
function getImgPath(id: number, imgUrl: string): string {
  const subDir = getSubDir(id)
  return `${OPENCHAT_IMG_PATH}/${subDir}/${imgUrl}.webp`
}

/**
 * プレビュー画像パスを生成
 * PHPのgetImgPreviewPath()と同等
 */
function getImgPreviewPath(id: number, imgUrl: string): string {
  const subDir = getSubDir(id)
  return `${OPENCHAT_IMG_PATH}/${OPENCHAT_IMG_PREVIEW_PATH}/${subDir}/${imgUrl}${OPENCHAT_IMG_PREVIEW_SUFFIX}.webp`
}

/**
 * 通常の画像URL を生成
 * PHPのimgUrl()と同等
 */
export function imgUrl(id: number, localImgUrl: string): string {
  if (!localImgUrl) return ''

  const basePath = 'https://openchat-review.me/'

  if (DEFAULT_OPENCHAT_IMG_URL_HASH.includes(localImgUrl)) {
    return `${basePath}${OPENCHAT_IMG_PATH}/default/${localImgUrl}.webp?id=${id}`
  }

  return basePath + getImgPath(id, localImgUrl)
}

/**
 * プレビュー画像URLを生成
 * PHPのimgPreviewUrl()と同等
 */
export function imgPreviewUrl(id: number, localImgUrl: string): string {
  if (!localImgUrl) return ''

  const basePath = 'https://openchat-review.me/'

  if (DEFAULT_OPENCHAT_IMG_URL_HASH.includes(localImgUrl)) {
    return `${basePath}${OPENCHAT_IMG_PATH}/${OPENCHAT_IMG_PREVIEW_PATH}/default/${localImgUrl}${OPENCHAT_IMG_PREVIEW_SUFFIX}.webp?id=${id}`
  }

  return basePath + getImgPreviewPath(id, localImgUrl)
}
