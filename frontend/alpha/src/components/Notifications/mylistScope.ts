import type { MyListData } from '@/types/storage'
import type { MylistAlertScope } from '@/types/api'

/**
 * マイリスト変動アラートのスコープ → 対象 open_chat_id 集合を解決する。
 *
 * マイリストのフォルダ構造は localStorage のみでサーバに無いため、対象集合は
 * 構造を持つフロント側で解決し、PUT 時にサーバへ保存する（cron はその集合を直接使う）。
 *
 * - all    … 全アイテム
 * - root   … folderId == null（ルート直下）のアイテムのみ
 * - folder … 指定フォルダとその子孫フォルダ配下のアイテム
 */
export function resolveScopeOcIds(
  data: MyListData,
  scope: MylistAlertScope,
  folderId: string | null,
): number[] {
  if (scope === 'all') {
    return uniqIds(data.items.map((i) => i.id))
  }
  if (scope === 'root') {
    return uniqIds(data.items.filter((i) => i.folderId == null).map((i) => i.id))
  }
  // folder: 指定フォルダ＋子孫フォルダ配下
  if (!folderId) return []
  const folderIds = collectDescendantFolderIds(data, folderId)
  return uniqIds(
    data.items.filter((i) => i.folderId != null && folderIds.has(i.folderId)).map((i) => i.id),
  )
}

/** 指定フォルダ自身と全子孫フォルダの id 集合。 */
function collectDescendantFolderIds(data: MyListData, rootId: string): Set<string> {
  const set = new Set<string>([rootId])
  const walk = (parentId: string) => {
    for (const f of data.folders) {
      if (f.parentId === parentId && !set.has(f.id)) {
        set.add(f.id)
        walk(f.id)
      }
    }
  }
  walk(rootId)
  return set
}

function uniqIds(ids: number[]): number[] {
  return Array.from(new Set(ids.filter((id) => Number.isFinite(id) && id > 0)))
}
