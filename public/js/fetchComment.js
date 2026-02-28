let lastList = ''

// 表示ルール:
// 今日 & 15分以内       → "たった今"
// 今日 & 1〜23時間前    → "3時間前"
// 今日 & 16〜59分前     → "30分前"
// 今日 & 15分超〜1分未満 → "45秒前"
// 昨日以前 & 同年 (モバイル) → "2/23"
// 昨日以前 & 同年 (PC)      → "2月23日"
// 昨日以前 & 前年以前 (モバイル) → "2025/12/1"
// 昨日以前 & 前年以前 (PC)      → "2025年12月1日"
export function timeElapsedString(datetime, thresholdMinutes = 15) {
  const now = new Date()
  const targetDatetime = new Date(datetime.replace(/-/g, '/'))

  const diffMs = now - targetDatetime
  const totalMinutes = diffMs / 1000 / 60

  if (totalMinutes <= thresholdMinutes) {
    return 'たった今'
  }

  const diffDate = new Date(diffMs)
  const hours = diffDate.getUTCHours()
  const minutes = diffDate.getUTCMinutes()
  const seconds = diffDate.getUTCSeconds()

  const isToday = now.getFullYear() === targetDatetime.getFullYear()
    && now.getMonth() === targetDatetime.getMonth()
    && now.getDate() === targetDatetime.getDate()

  if (isToday) {
    if (hours > 0) {
      return hours + '時間前'
    } else if (minutes > 0) {
      return minutes + '分前'
    } else {
      return seconds + '秒前'
    }
  }

  const isPC = window.matchMedia('(min-width: 512px)').matches
  const m = targetDatetime.getMonth() + 1
  const d = targetDatetime.getDate()

  if (now.getFullYear() > targetDatetime.getFullYear()) {
    const y = targetDatetime.getFullYear()
    return isPC ? `${y}年${m}月${d}日` : `${y}/${m}/${d}`
  }

  return isPC ? `${m}月${d}日` : `${m}/${d}`
}

export function applyTimeElapsedString() {
  const commentTime = document.querySelectorAll('.comment-time span')
  commentTime.forEach((time) => {
    time.textContent = timeElapsedString(time.textContent)
  })
}

function getCookieValue(key = 'comment_flag') {
  const cookies = document.cookie.split(';')
  const foundCookie = cookies.find((cookie) => cookie.split('=')[0].trim() === key.trim())
  if (foundCookie) {
    const cookieValue = decodeURIComponent(foundCookie.split('=')[1])
    return cookieValue
  }
  return ''
}

async function fetchComment(url = '/recent-comment-api', openChatId = 0) {
  const comment = document.getElementById('recent_comment')
  if (!comment) return

  const query = openChatId ? '?open_chat_id=' + openChatId : ''

  try {
    const res = await fetch(url + query)
    if (res.status !== 200) {
      throw new Error()
    }

    const data = await res.text()
    if (lastList === data) {
      return
    }

    lastList = data
    comment.textContent = ''
    comment.insertAdjacentHTML('afterbegin', data)

    applyTimeElapsedString()
  } catch (error) {
    console.error('エラー', error)
  }
}

export function getComment(openChatId = 0, urlRoot = '') {
  fetchComment(
    getCookieValue() ? `${urlRoot}/recent-comment-api/nocache` : `${urlRoot}/recent-comment-api`,
    openChatId
  )
}
