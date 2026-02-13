import { langCode } from '../../../util/fetchRenderer'
import { weekdays } from '../../../util/translation'
import { isRecentString, isYestString } from './getHourTicksFormatterCallback'

export default function getHorizontalLabelFontColor(context: any) {
  let label = context.tick.label
  if (Array.isArray(label)) {
    label = label[1]
  }

  const saturday = weekdays[langCode][6] ?? weekdays[''][6]
  const sunday = weekdays[langCode][0] ?? weekdays[''][0]

  if (label.includes(saturday)) {
    return '#44617B'
  } else if (label.includes(sunday)) {
    return '#9C3848'
  } else if (label.includes(isYestString)) {
    // 最新24時間表示で昨日の時間の場合
    return '#b7b7b7'
  } else if (label.includes(isRecentString)) {
    // 最新24時間表示で最新の時間の場合
    return '#111'
  } else if (label.includes(':')) {
    // 最新24時間表示で今日の時間の場合
    return '#777'
  } else {
    return '#777'
  }
}
