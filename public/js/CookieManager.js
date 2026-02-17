export class CookieManager {
  constructor() {
    this.joinCountCheckbox = document.getElementById('joinCountCheckbox')
    this.addChangeListener()
  }

  setCookie(name, value) {
    const expires = new Date()
    expires.setFullYear(expires.getFullYear() + 1)
    document.cookie = `${name}=${value}; expires=${expires.toUTCString()}; path=/`
  }

  getCookie(name) {
    const cookies = document.cookie.split('; ')
    for (const cookie of cookies) {
      const [cookieName, cookieValue] = cookie.split('=')
      if (cookieName === name) {
        return cookieValue
      }
    }
    return null
  }

  deleteCookie(name) {
    document.cookie = `${name}=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/`
  }

  addChangeListener() {
    this.joinCountCheckbox.addEventListener('change', () => {
      if (this.joinCountCheckbox.checked) {
        this.setCookie('labs-joincount', '1')
      } else {
        this.deleteCookie('labs-joincount')
      }
    })

    // チェックボックスの初期状態を復元する場合
    const storedValue = this.getCookie('labs-joincount')
    this.joinCountCheckbox.checked = storedValue === '1'
  }
}
