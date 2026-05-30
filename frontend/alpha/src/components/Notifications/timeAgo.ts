/** unix秒を「N分前 / N時間前 / N日前 / M/D」の相対表記にする。通知の発生時刻表示用。 */
export function timeAgo(unixSec: number): string {
  const diffSec = Math.floor(Date.now() / 1000) - unixSec
  if (diffSec < 60) return 'たった今'
  const min = Math.floor(diffSec / 60)
  if (min < 60) return `${min}分前`
  const hour = Math.floor(min / 60)
  if (hour < 24) return `${hour}時間前`
  const day = Math.floor(hour / 24)
  if (day < 7) return `${day}日前`
  const d = new Date(unixSec * 1000)
  return `${d.getMonth() + 1}/${d.getDate()}`
}

/** ISO/日時文字列を「YYYY/MM/DD HH:mm」に整形（最終算出時刻の表示用）。失敗時は元文字列。 */
export function formatComputedAt(raw: string): string {
  const d = new Date(raw.includes('T') ? raw : raw.replace(' ', 'T'))
  if (isNaN(d.getTime())) return raw
  const p = (n: number) => String(n).padStart(2, '0')
  return `${d.getFullYear()}/${p(d.getMonth() + 1)}/${p(d.getDate())} ${p(d.getHours())}:${p(d.getMinutes())}`
}
