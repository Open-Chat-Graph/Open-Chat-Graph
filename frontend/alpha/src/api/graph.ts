import type { GraphDataResponse } from '../types/api'

const API_BASE = '/alpha-api'

/**
 * 単一ルームの時系列グラフデータを取得。
 * フォルダ統合グラフ（FolderChartPage）専用。共有ファイル alpha.ts を汚さないよう別出し。
 */
export async function getGraphData(openChatId: number): Promise<GraphDataResponse> {
  const res = await fetch(`${API_BASE}/stats/${openChatId}/graph`)
  if (!res.ok) throw new Error('Graph API failed')

  return res.json()
}
