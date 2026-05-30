class LiveTalkAnalyzerEventListener {
  constructor(limit = 8) {
    this.fileInputElem = document.getElementById('file-input')
    this.buttons = document.getElementById('chart-btn-nav').querySelectorAll('.chart-btn')
    this.ocTitleElem = document.getElementById('octitle')
    this.errorMessageElem = document.getElementById('errorMessage')
    this.graphArea = document.getElementById('graph-area')
    this.graphAreaHiddenClassName = 'hidden-graph'
    this.graphCanvas = document.getElementById('openchat-statistics')
    this.listTable = document.getElementById('live-table-body')
    this.listUserTable = document.getElementById('live-user-table-body')
    this.totalTime = document.getElementById('totalTime')
    this.openChatChart = null
    this.timeframeGraph = null
    this.parsedLog = null
    this.nowLoading = false
    this.initLimit = limit
  }

  eventListener() {
    window.addEventListener('pageshow', () => this.#resetDisplay)
    this.fileInputElem.addEventListener('click', () => this.#resetDisplay)
    this.fileInputElem.addEventListener('change', async (e) => await this.#fileEventHandler(e))
    this.buttons.forEach((el) => el.addEventListener('click', this.#btnEventHandler))

    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'visible') {
        this.#resetDisplay()
      }
    })
  }

  #resetDisplay = () => {
    this.fileInputElem.value = ''
  }

  #scrollToTop() {
    window.scrollTo(0, 0)
  }

  async #fileEventHandler(e) {
    const file = e.target.files[0] ?? null
    if (!file) {
      return
    }

    try {
      this.nowLoading = true
      const text = await this.#readTextFileAsUTF8(file)
      this.#fileInputHandler(text)
    } catch (error) {
      this.nowLoading = false
      this.#showError(error.message)
      console.error(error)
    }

    this.nowLoading = false
  }

  async #readTextFileAsUTF8(file) {
    return new Promise((resolve, reject) => {
      const reader = new FileReader()

      reader.onload = function () {
        const text = reader.result
        resolve(text)
      }

      reader.onerror = function () {
        reject('ファイルの読み込み中にエラーが発生しました。')
      }

      reader.readAsText(file, 'utf-8')
    })
  }

  #fileInputHandler(text) {
    this.#parseLog(text)

    if (this.openChatChart !== null) {
      this.openChatChart.chart.destroy()
      this.openChatChart === null
      this.#switchBtnDisabled(this.buttons[0])
    }

    if (this.timeframeGraph !== null) {
      this.timeframeGraph.chart.destroy()
      this.timeframeGraph === null
    }

    this.#loadLog()
    this.#errorTextContent('')
    this.#titleTextContent(this.parsedLog.openChatName)
    this.#addRowsToTable(this.initLimit)
    this.#scrollToTop()
    return
  }

  #parseLog(text) {
    this.parsedLog = new LiveTalkAnalyzer().analyzeText(text)
    console.log(this.parsedLog)
  }

  #loadLog() {
    this.graphArea.classList.remove(this.graphAreaHiddenClassName)

    this.openChatChart = new LiveTalkChartFactory(
      {
        date: this.parsedLog.date,
        liveTime: this.parsedLog.totalTime,
        liveUser: this.parsedLog.liveUser,
      },
      this.graphCanvas,
      this.initLimit
    )

    this.timeframeGraph = new LiveTalkTimeframeGraph(this.parsedLog, this.initLimit)
  }

  #btnEventHandler = (e) => {
    if (this.nowLoading) {
      return
    } else if (e.target.id === 'btn-week') {
      this.openChatChart.update(7)
      this.timeframeGraph.update(7)
      this.#addRowsToTable(7)
    } else if (e.target.id === 'btn-month') {
      this.openChatChart.update(31)
      this.timeframeGraph.update(31)
      this.#addRowsToTable(31)
    } else if (e.target.id === 'btn-all') {
      this.openChatChart.update(0)
      this.timeframeGraph.update(0)
      this.#addRowsToTable(0)
    }

    this.#switchBtnDisabled(e.target)
  }

  #switchBtnDisabled(el) {
    this.buttons.forEach((btn) => (btn.disabled = false))
    el.disabled = true
  }

  #addRowsToTable(limit) {
    this.listTable.innerHTML = ''

    const slicedObj = this.#applySlice(this.parsedLog, limit)

    // テーブルの要素を生成して追加する
    slicedObj.date.forEach((item, i) => {
      const row = document.createElement('tr')

      const dateCell = document.createElement('td')
      const date = LiveTalkChartFactory.dateFormatter(item, true)
      const dateColor = LiveTalkChartFactory.dateFrontColor(date)
      dateCell.textContent = date
      dateCell.style.color = dateColor
      row.appendChild(dateCell)

      const talkTimeCell = document.createElement('td')
      talkTimeCell.textContent = LiveTalkChartFactory.formatTimeCallback2(slicedObj.totalTime[i])
      row.appendChild(talkTimeCell)

      const talkCountCell = document.createElement('td')
      talkCountCell.textContent = slicedObj.liveCount[i]
      row.appendChild(talkCountCell)

      const talkUserCell = document.createElement('td')
      const users = this.#processUserCallTimes(slicedObj.liveUser[i], slicedObj.elapsedTime[i])

      // ユーザー名カラムの要素を生成して追加する
      users.forEach(function (el) {
        const talkUser = document.createElement('span')
        talkUser.textContent = el[0]

        const talkUserTime = document.createElement('span')
        talkUserTime.textContent = el[1]
        talkUser.appendChild(talkUserTime)

        talkUserCell.appendChild(talkUser)
      })

      row.appendChild(talkUserCell)

      this.listTable.appendChild(row)
    })

    // ユーザー毎の利用時間テーブルの要素を生成して追加する
    this.#addRowsToUserTable(slicedObj)

    // 累計利用時間の算出
    const sum = slicedObj.totalTime.reduce((total, current) => total + current, 0)
    this.totalTime.textContent = this.#formatTotalTime(sum)
  }

  #addRowsToUserTable(slicedObj) {
    this.listUserTable.innerHTML = ''

    const userCallTimes = this.#processUserCallTimesAll(
      slicedObj.liveUser,
      slicedObj.elapsedTime,
      slicedObj.acrossDate
    )

    userCallTimes.forEach((item) => {
      const row = document.createElement('tr')

      const talkTimeCell = document.createElement('td')
      talkTimeCell.textContent = LiveTalkChartFactory.formatTimeCallback2(item.callTime)
      row.appendChild(talkTimeCell)

      const talkCountCell = document.createElement('td')
      talkCountCell.textContent = item.callCount
      row.appendChild(talkCountCell)

      const talkUserCell = document.createElement('td')
      talkUserCell.textContent = this.#sanitizeString(item.userName)
      row.appendChild(talkUserCell)

      this.listUserTable.appendChild(row)
    })
  }

  // オブジェクトに含まれる配列全てを指定した長さまで詰めて、逆順にソートする
  #applySlice(obj, limit) {
    const newObj = {}
    Object.keys(obj).forEach((key) => {
      if (Array.isArray(obj[key])) {
        newObj[key] = obj[key].slice(-limit).reverse()
      } else {
        newObj[key] = obj[key]
      }
    })

    return newObj
  }

  #processUserCallTimes(usernames, callTimes) {
    // ユーザー名をキーとして通話時間を合計するためのオブジェクトを作成
    const userCallTimes = {}

    for (let i = 0; i < usernames.length; i++) {
      const username = usernames[i]
      const callTime = callTimes[i]

      // ユーザー名が既にオブジェクトに存在する場合、通話時間を加算
      if (userCallTimes.hasOwnProperty(username)) {
        userCallTimes[username] += callTime
      } else {
        // ユーザー名がまだオブジェクトに存在しない場合、新しいエントリを作成
        userCallTimes[username] = callTime
      }
    }

    let users = []

    Object.keys(userCallTimes).forEach((username) => {
      const callTime = LiveTalkChartFactory.formatTimeCallback2(userCallTimes[username])
      users.push([username, callTime])
    })

    // カスタムの比較関数を使用して users をソート
    users.sort((a, b) => {
      const stringA = a[0]
      const stringB = b[0]

      for (let i = 0; i < Math.min(stringA.length, stringB.length); i++) {
        const charCodeA = stringA.charCodeAt(i)
        const charCodeB = stringB.charCodeAt(i)

        if (charCodeA !== charCodeB) {
          return charCodeA - charCodeB
        }
      }

      // 一致する文字がある場合、短い文字列を前に配置
      return stringA.length - stringB.length
    })

    return users
  }

  // ユーザー毎の利用時間を取得する
  #processUserCallTimesAll(usernamesArray, callTimesArray, acrossDateArray) {
    // ユーザーの通話情報を格納する配列
    const userCallTimes = []

    // ネストされた配列からユーザー情報を抽出
    for (let i = 0; i < usernamesArray.length; i++) {
      const usernames = usernamesArray[i]
      const callTimes = callTimesArray[i]
      const acrossDate = acrossDateArray[i]

      // 日付をまたいだ場合にカウントを既に減らしたかどうかのフラグ
      let isAcrossDateFlag = false

      // ユーザーごとに通話情報を処理
      for (let j = 0; j < usernames.length; j++) {
        const username = usernames[j]
        const callTime = callTimes[j]

        // 日付をまたいだユーザーかどうか
        const isAcrossDate = acrossDate === username

        // ユーザーがすでに存在するかを確認
        let userIndex = -1
        for (let k = 0; k < userCallTimes.length; k++) {
          if (userCallTimes[k].userName === username) {
            userIndex = k
            break
          }
        }

        if (userIndex === -1) {
          // ユーザーが存在しない場合、新しいエントリを追加
          if (isAcrossDate && isAcrossDateFlag === false) {
            // 日付をまたいだユーザーで、まだカウントを減らしていない場合
            userCallTimes.push({
              userName: username,
              callTime: callTime,
              callCount: 0,
            })

            isAcrossDateFlag = true
          } else {
            userCallTimes.push({
              userName: username,
              callTime: callTime,
              callCount: 1,
            })
          }
        } else {
          // ユーザーが存在する場合、通話時間と通話回数を更新
          userCallTimes[userIndex].callTime += callTime
          userCallTimes[userIndex].callCount++

          if (isAcrossDate && isAcrossDateFlag === false) {
            // 日付をまたいだユーザーで、まだカウントを減らしていない場合
            userCallTimes[userIndex].callCount--
            isAcrossDateFlag = true
          }
        }
      }
    }

    // 通話時間を基準に降順でソート
    userCallTimes.sort((a, b) => b.callTime - a.callTime)
    return userCallTimes
  }

  // 分数の値から、x時間x分の形式の文字列を生成する
  #formatTotalTime(value) {
    if (value < 0) return ''

    const hours = Math.floor(value / 60)
    const minutes = value % 60
    if (!hours && !minutes) return '0分'

    return (hours ? hours + '時間' : '') + (minutes ? minutes + '分' : '')
  }

  #sanitizeString(input) {
    const sanitized = input
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;')

    return sanitized
  }

  #showError(message) {
    this.graphArea.classList.add(this.graphAreaHiddenClassName)
    this.#resetDisplay()
    this.#errorTextContent(message)
    this.#titleTextContent()
  }

  #errorTextContent(message = '') {
    this.errorMessageElem.textContent = message
  }

  #titleTextContent(title = 'ライブトーク利用時間分析ツール') {
    this.ocTitleElem.textContent = title
  }
}
