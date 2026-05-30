/**
 * メンバー数の簡潔表示。本家リスト同様に1万以上は「X.X万人」へ丸める。
 * 1万未満はカンマ区切りの実数。詳細画面では実数(toLocaleString)を使うこと。
 */
export function formatMemberCompact(member: number): string {
  if (member >= 10000) {
    const man = member / 10000
    // 10万以上は小数を出さない（1.2万 / 12万 のような自然な見え方）
    const text = man >= 10 ? Math.round(man).toString() : (Math.round(man * 10) / 10).toString()
    return `${text}万人`
  }
  return `${member.toLocaleString()}人`
}
