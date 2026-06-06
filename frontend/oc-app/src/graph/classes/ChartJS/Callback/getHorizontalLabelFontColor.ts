import { langCode } from '../../../util/fetchRenderer'
import { weekdays } from '../../../util/translation'
import { isRecentString, isYestString } from './getHourTicksFormatterCallback'
import { getColors } from '../../../util/theme'

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export default function getHorizontalLabelFontColor(context: any) {
  let label = context.tick.label
  if (Array.isArray(label)) {
    label = label[1]
  }

  const saturday = weekdays[langCode][6] ?? weekdays[''][6]
  const sunday = weekdays[langCode][0] ?? weekdays[''][0]

  if (label.includes(saturday)) {
    return getColors().text.saturday
  } else if (label.includes(sunday)) {
    return getColors().text.sunday
  } else if (label.includes(isYestString)) {
    // 最新24時間表示で昨日の時間の場合
    return getColors().text.yesterday
  } else if (label.includes(isRecentString)) {
    // 最新24時間表示で最新の時間の場合
    return getColors().text.primary
  } else if (label.includes(':')) {
    // 最新24時間表示で今日の時間の場合
    return getColors().text.secondary
  } else {
    return getColors().text.secondary
  }
}
