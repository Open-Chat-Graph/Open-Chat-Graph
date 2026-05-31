export const STORAGE_KEYS = {
  myList: 'alpha_mylist',
  myListSort: 'alpha_mylist_sort',
  notifSeen: 'alpha_notif_seen',
  searchQuery: 'searchPageQuery',
  myListCurrentFolder: 'alpha_mylist_current_folder',
  savedSearches: 'alpha_saved_searches',
  /** 最近の検索キーワード履歴（検索実行時に自動追記・上限件数で古いものを捨てる）。 */
  searchHistory: 'alpha_search_history',
  /** 分析ビューで最後に開いていたサブ画面（/period-growth?... | /labs?...）。enter 時に復元する。 */
  analysisLastSub: 'alpha_analysis_last_sub',
} as const
