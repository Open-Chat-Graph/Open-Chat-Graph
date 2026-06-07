export interface Folder {
  id: string              // UUID
  name: string
  parentId: string | null
  order: number
  expanded: boolean
  /** スマートフォルダ：rule が有効かどうか（ダイアログ保存後に localStorage へ書き戻す軽量キャッシュ） */
  hasRule?: boolean
}

export interface ChatItem {
  id: number              // OpenChat ID
  folderId: string | null
  order: number
  addedAt: string         // ISO 8601
  /** 追加経路。'auto' はサーバ側の自動追加。undefined は既存ローカルデータ＝'manual' 扱い */
  source?: 'manual' | 'auto'
}

export interface MyListData {
  version: number
  folders: Folder[]
  items: ChatItem[]
  lastModified: string
}
