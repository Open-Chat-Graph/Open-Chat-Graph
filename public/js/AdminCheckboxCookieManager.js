export class AdminCheckboxCookieManager {
  constructor() {
    this.cookieName = 'admin-enable'
    this.checkbox = document.getElementById('adminEnable')
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
    this.checkbox.addEventListener('change', () => {
      if (this.checkbox.checked) {
        this.setCookie(this.cookieName, '1')
      } else {
        this.deleteCookie(this.cookieName)
      }

      location.reload()
    })
  }
}
