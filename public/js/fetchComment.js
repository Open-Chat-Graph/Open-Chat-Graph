let lastList = ''

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

function fetchComment() {
    const comment = document.getElementById('recent_comment')

    fetch('/recent-comment-api')
        .then((res) => {
            if (res.status === 200)
                return res.text();
            else
                throw new Error()
        })
        .then((data) => {
            if (lastList === data)
                return

            lastList = data
            comment.textContent = ''
            comment.insertAdjacentHTML('afterbegin', data)
        })
        .catch(error => console.error('エラー', error))
}

export function setEvent(getCookie = false) {
    window.addEventListener("pageshow", () => {
        if (getCookie) {
            getCookieValue() && fetchComment()
        } else {
            fetchComment()
        }
    })
}