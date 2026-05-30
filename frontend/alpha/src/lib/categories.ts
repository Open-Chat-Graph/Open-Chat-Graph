// LINEオープンチャットのカテゴリ（日本語）。サーバの AppConfig::OPEN_CHAT_CATEGORY[''] と一致。
// id=0 は「すべて」(絞り込みなし)。
export interface CategoryOption {
  id: number
  name: string
}

export const CATEGORIES: CategoryOption[] = [
  { id: 0, name: 'すべて' },
  { id: 17, name: 'ゲーム' },
  { id: 16, name: 'スポーツ' },
  { id: 26, name: '芸能人・有名人' },
  { id: 7, name: '同世代' },
  { id: 22, name: 'アニメ・漫画' },
  { id: 40, name: '金融・ビジネス' },
  { id: 33, name: '音楽' },
  { id: 8, name: '地域・暮らし' },
  { id: 20, name: 'ファッション・美容' },
  { id: 41, name: 'イラスト' },
  { id: 11, name: '研究・学習' },
  { id: 5, name: '働き方・仕事' },
  { id: 2, name: '学校・同窓会' },
  { id: 12, name: '料理・グルメ' },
  { id: 23, name: '健康' },
  { id: 6, name: '団体' },
  { id: 28, name: '妊活・子育て' },
  { id: 19, name: '乗り物' },
  { id: 37, name: '写真' },
  { id: 18, name: '旅行' },
  { id: 27, name: '動物・ペット' },
  { id: 24, name: 'TV・VOD' },
  { id: 29, name: '本' },
  { id: 30, name: '映画・舞台' },
]
