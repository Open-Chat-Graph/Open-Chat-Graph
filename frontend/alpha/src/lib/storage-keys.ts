export const STORAGE_KEYS = {
  myList: 'alpha_mylist',
  myListSort: 'alpha_mylist_sort',
  notifSeen: 'alpha_notif_seen',
  savedSearches: 'alpha_saved_searches',
  /** 最近の検索キーワード履歴（検索実行時に自動追記・上限件数で古いものを捨てる）。 */
  searchHistory: 'alpha_search_history',
  /** マイリスト同期: 最後にサーバ状態を取り込んだ時刻（serverTime）。全置換 PUT の loadedAt に使う。 */
  myListLoadedAt: 'alpha_mylist_loaded_at',
} as const
