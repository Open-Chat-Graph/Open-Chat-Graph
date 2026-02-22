let lastList = ''

export function timeElapsedString(datetime, thresholdMinutes = 15) {
  const now = new Date()
  const targetDatetime = new Date(datetime.replace(/-/g, '/')) // 日付形式を修正してDateオブジェクトを作成

  const diffMs = now - targetDatetime // ミリ秒単位の差を計算
  const totalMinutes = diffMs / 1000 / 60 // ミリ秒を分に変換

  if (totalMinutes <= thresholdMinutes) {
    return ['たった今', '#4d73ff']
  }

  const diffDate = new Date(diffMs)
  const years = diffDate.getUTCFullYear() - 1970 // 1970年からの年数を計算
  const months = diffDate.getUTCMonth()
  const days = diffDate.getUTCDate() - 1 // 月初からの日数
  const hours = diffDate.getUTCHours()
  const minutes = diffDate.getUTCMinutes()
  const seconds = diffDate.getUTCSeconds()

  const formattedTime = `${targetDatetime.getHours()}:${String(targetDatetime.getMinutes()).padStart(2, '0')}`

  if (now.getFullYear() > targetDatetime.getFullYear()) {
    return [
      `${targetDatetime.getFullYear()}年${targetDatetime.getMonth() + 1}月${targetDatetime.getDate()}日 ${formattedTime}`,
      '#777',
    ]
  } else if (months > 0) {
    return [
      `${targetDatetime.getMonth() + 1}月${targetDatetime.getDate()}日 ${formattedTime}`,
      '#777',
    ]
  } else if (days > 0) {
    return [
      `${targetDatetime.getMonth() + 1}月${targetDatetime.getDate()}日 ${formattedTime}`,
      '#777',
    ]
  } else if (hours > 0) {
    return [hours + '時間前', '#4d73ff']
  } else if (minutes > 0) {
    return [minutes + '分前', '#4d73ff']
  } else {
    return [seconds + '秒前', '#4d73ff']
  }
}

export function applyTimeElapsedString() {
  const commentTime = document.querySelectorAll('.comment-time span')
  commentTime.forEach((time) => {
    const [formattedTime, color] = timeElapsedString(time.textContent)
    time.textContent = formattedTime
    time.style.color = color
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
