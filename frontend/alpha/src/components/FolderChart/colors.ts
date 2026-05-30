/**
 * フォルダ統合グラフでルームごとに割り当てる識別色。
 * 凡例・線・チェックリストのチップで同じ色を使う。
 * 明度・彩度を揃えた知覚的に均等な並びにし、隣接ルームでも見分けやすくしている。
 * ダーク/ライト両テーマで視認できるよう中明度の色のみ採用。
 */
const PALETTE = [
  '#2563eb', // blue
  '#dc2626', // red
  '#16a34a', // green
  '#d97706', // amber
  '#9333ea', // purple
  '#0891b2', // cyan
  '#db2777', // pink
  '#65a30d', // lime
  '#e11d48', // rose
  '#0d9488', // teal
  '#7c3aed', // violet
  '#ea580c', // orange
  '#4f46e5', // indigo
  '#ca8a04', // yellow-dark
  '#be185d', // fuchsia-dark
  '#059669', // emerald
] as const

/** index に対して安定した色を返す（パレットを循環）。 */
export function colorForIndex(index: number): string {
  return PALETTE[index % PALETTE.length]
}

/** openChatId の配列から id→色 のマップを作る（並び順で安定割当）。 */
export function buildColorMap(ids: number[]): Map<number, string> {
  const map = new Map<number, string>()
  ids.forEach((id, i) => map.set(id, colorForIndex(i)))
  return map
}
