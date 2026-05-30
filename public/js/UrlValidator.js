export class UrlValidator {
  #urlPattern =
    /https?:\/\/[-_.!~*\'()a-zA-Z0-9;\/?:\@&=+\$,%#\u3000-\u30FE\u4E00-\u9FA0\uFF01-\uFFE3]+/g

  constructor(input, message, submitBtn) {
    this.input = input
    this.message = message
    this.submitBtn = submitBtn
  }

  handle() {
    const matchURL = this.input.value.match(this.#urlPattern)
    if (matchURL) {
      this.#toggleErrorMessage(false)
      this.#toggleBtnDisabled(false)
    } else {
      this.#toggleErrorMessage(!!this.input.value)
      this.#toggleBtnDisabled(true)
    }
  }

  #toggleErrorMessage(bool) {
    if (bool) {
      this.message.style.display = 'block'
    } else {
      this.message.style.display = 'none'
    }
  }

  #toggleBtnDisabled(bool) {
    this.submitBtn.disabled = bool
  }
}
