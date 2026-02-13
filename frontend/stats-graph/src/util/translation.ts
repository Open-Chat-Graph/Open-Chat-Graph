import { langCode } from './fetchRenderer'

const translation = {
  '1週間': {
    tw: '1週',
    th: '1W',
  },
  '1ヶ月': {
    tw: '1個月',
    th: '1M',
  },
  最新24時間: {
    tw: '最新24小時',
    th: 'Latest 24H',
  },
  全期間: {
    tw: '全部',
    th: 'All',
  },
  急上昇: {
    tw: '熱門',
    th: 'มาแรง',
  },
  ランキング: {
    tw: '排行榜',
    th: 'การจัดอันดับ',
  },
  すべて: {
    tw: '全部',
    th: 'ทั้งหมด',
  },
  カテゴリー内: {
    tw: '分類內',
    th: 'ในหมวดหมู่',
  },
  'グラフの移動・拡大': {
    tw: '移動/縮放圖表',
    th: 'เลื่อน/ขยายกราฟ',
  },
  ランキングの順位を表示: {
    tw: '顯示排名位置',
    th: 'แสดงตำแหน่งอันดับ',
  },
  順位グラフの初期表示: {
    tw: '預設圖表顯示',
    th: 'การแสดงกราฟอันดับเริ่มต้น',
  },
  順位表示なし: {
    tw: '不顯示排名',
    th: 'ไม่แสดงอันดับ',
  },
  設定: {
    tw: '設定',
    th: 'ตั้งค่า',
  },
  'メンバー数・ランキキング履歴の統計グラフ': {
    tw: '成員數量/排名歷史統計圖',
    th: 'กราฟสถิติจำนวนสมาชิก/ประวัติการจัดอันดับ',
  },
  メンバー: {
    tw: '成員',
    th: 'สมาชิก',
  },
  圏外: {
    tw: '圈外',
    th: 'n/a',
  },
  メンバー数: {
    tw: '成員數量',
    th: 'จำนวนสมาชิก',
  },
  公式急上昇の順位: {
    tw: 'LINE official 熱門',
    th: 'LINE official เทรนด์',
  },
  公式ランキングの順位: {
    tw: 'LINE official 排行榜',
    th: 'LINE official การจัดอันดับ',
  },
}

const sprintfTranslation = {
  '%s 位': {
    ja: (s: string) => `${s} 位`,
    tw: (s: string) => `${s}th`,
    th: (s: string) => `${s}th`,
  },
  '%s 位 / %s 件': {
    ja: (s: string, s2: string) => `${s} 位 / ${s2} 件`,
    tw: (s: string, s2: string) => `第 ${s} 位 / ${s2} 項`,
    th: (s: string, s2: string) => `อันดับที่ ${s} / ${s2} รายการ`,
  },
  '%s 時点': {
    ja: (s: string) => `${s} 時点`,
    tw: (s: string) => `截至 ${s}`,
    th: (s: string) => `ณ เวลา ${s} น`,
  },
}

export const sprintfT = (key: string, ...string: (string | number)[]): string => {
  return (
    // @ts-ignore
    sprintfTranslation[key]?.[langCode ? langCode : 'ja'](...string) ?? key
  )
}

export const t = (string: string): string => {
  // @ts-ignore
  return translation[string]?.[langCode] ?? string
}

export const weekdays = {
  '': ['日', '月', '火', '水', '木', '金', '土'],
  tw: ['日', '一', '二', '三', '四', '五', '六'],
  th: ['อา', 'จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส'],
}
