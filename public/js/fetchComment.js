let lastList = ''

function timeElapsedString(datetime, thresholdMinutes = 15) {
    const now = new Date();
    const targetDatetime = new Date(datetime.replace(/-/g, '/'));  // 日付形式を修正してDateオブジェクトを作成

    const diffMs = now - targetDatetime;  // ミリ秒単位の差を計算
    const totalMinutes = diffMs / 1000 / 60;  // ミリ秒を分に変換

    if (totalMinutes <= thresholdMinutes) {
        return 'たった今';
    }

    const diffDate = new Date(diffMs);
    const years = diffDate.getUTCFullYear() - 1970; // 1970年からの年数を計算
    const months = diffDate.getUTCMonth();
    const days = diffDate.getUTCDate() - 1; // 月初からの日数
    const hours = diffDate.getUTCHours();
    const minutes = diffDate.getUTCMinutes();
    const seconds = diffDate.getUTCSeconds();

    if (years > 0) {
        return years + '年前';
    } else if (months > 0) {
        return months + 'ヶ月前';
    } else if (days > 0) {
        return days + '日前';
    } else if (hours > 0) {
        return hours + '時間前';
    } else if (minutes > 0) {
        return minutes + '分前';
    } else {
        return seconds + '秒前';
    }
}

function getCookieValue(key = 'comment_flag') {
    const cookies = document.cookie.split(';')
    const foundCookie = cookies.find(
        (cookie) => cookie.split('=')[0].trim() === key.trim()
    )
    if (foundCookie) {
        const cookieValue = decodeURIComponent(foundCookie.split('=')[1])
        return cookieValue
    }
    return ''
}

async function fetchComment(url = '/recent-comment-api') {
    const comment = document.getElementById('recent_comment')

    try {
        const res = await fetch(url)
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

        const commentTime = document.querySelectorAll('.comment-time span')
        commentTime.forEach((time) => {
            time.textContent = timeElapsedString(time.textContent)
        })
    } catch (error) {
        console.error('エラー', error)
    }
}

fetchComment(getCookieValue() ? '/recent-comment-api/nocache' : '/recent-comment-api')
