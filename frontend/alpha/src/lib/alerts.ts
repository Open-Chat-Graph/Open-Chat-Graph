/**
 * アラート機能の共有定数・文言。
 *
 * KEYWORD_LIMIT はサーバー側の上限（PUT /alpha-api/alerts/config が
 * 超過時に 422 `KEYWORD_LIMIT` を返す）と一致させること。
 */

/** ウォッチできるキーワードの上限件数（サーバーと一致）。 */
export const KEYWORD_LIMIT = 20

/** サーバーの 422 エラーコード。 */
export type AlertsErrorCode = 'KEYWORD_LIMIT' | 'PUSH_REQUIRED'

/** ユーザー向け文言。 */
export const ALERT_MESSAGES = {
  /** KEYWORD_LIMIT: 上限到達 */
  keywordLimit: `ウォッチできるキーワードは${KEYWORD_LIMIT}件までです`,
  /** PUSH_REQUIRED / 購読失敗: 通知許可を促す */
  pushRequired: 'キーワード通知を使うには通知を許可してください',
} as const

/**
 * PUT /alerts/config の 422 レスポンス body からエラーコードを取り出す。
 * 想定外の形なら null。
 */
export function parseAlertsErrorCode(body: unknown): AlertsErrorCode | null {
  if (body && typeof body === 'object' && 'code' in body) {
    const code = (body as { code?: unknown }).code
    if (code === 'KEYWORD_LIMIT' || code === 'PUSH_REQUIRED') return code
  }
  return null
}
