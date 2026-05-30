export interface Folder {
  id: string              // UUID
  name: string
  parentId: string | null
  order: number
  expanded: boolean
}

export interface ChatItem {
  id: number              // OpenChat ID
  folderId: string | null
  order: number
  addedAt: string         // ISO 8601
}

export interface MyListData {
  version: number
  folders: Folder[]
  items: ChatItem[]
  lastModified: string
}
