export class OpenChatUrlValidator {
  #urlPattern =
    /((https:\/\/openchat.line.me\/jp\/cover\/[a-zA-Z0-9.\-_@:/~?%&;=+#',()*!]+)(.*)(?=\?)|(https:\/\/openchat.line.me\/jp\/cover\/[a-zA-Z0-9.\-_@:/~?%&;=+#',()*!]+))/g

  constructor(form) {
    this.form = form
    this.submitBtn = form.elements['submit']
    this.input = form.elements['url']
  }

  handle() {
    if (this.input.value === '') {
      this.#toggleErrorMessage(false)
      this.#toggleBtnDisabled(true)
      return
    }

    const matchURL = this.input.value.match(this.#urlPattern)
    if (matchURL) {
      this.#insertTextAndFocusOnStart(matchURL[0])
      this.#toggleErrorMessage(false)
      this.#toggleBtnDisabled(false)
    } else {
      this.#toggleErrorMessage(true)
      this.#toggleBtnDisabled(true)
    }
  }

  #toggleErrorMessage(bool) {
    const cl = this.form.classList
    if (bool) {
      !cl.contains('false') && cl.add('false')
    } else {
      cl.contains('false') && cl.remove('false')
    }
  }

  #toggleBtnDisabled(bool) {
    this.submitBtn.disabled = bool
  }

  #insertTextAndFocusOnStart(string) {
    this.input.value = string
    this.input.focus()
    this.input.setSelectionRange(1, 0)
  }
}
