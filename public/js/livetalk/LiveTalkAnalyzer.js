class LiveTalkAnalyzer {
  constructor() {
    this.OPENCHAT_NAME_PATTERN = /\[LINE\]\s(.*?)のトーク/;

    this.DATELINE_PATTERN = /^(\d{4})\/(\d{1,2})\/(\d{1,2})\((月|火|水|木|金|土|日)\)(\r\n|\r|\n|$)/;
    this.DATE_PATTERN = /(\d{4})\/(\d{1,2})\/(\d{1,2})/;

    this.JOIN_TEXT_PATTERN = /^(?:[01]?\d|2[0-3]):[0-5]\d\t.*(がトークに参加しました。|が参加しました。)(\r\n|\r|\n|$)/;

    this.LIVE_START_USER_PATTERN = /^(?:[01]?\d|2[0-3]):[0-5]\d\t(.*?)\t(\[ライブトークが始まりました。\]|ライブトークが始まりました。)(\r\n|\r|\n|$)/;

    this.LIVE_END_PATTERN = /^(?:[01]?\d|2[0-3]):[0-5]\d\t.*がライブを終了しました。(\r\n|\r|\n|$)/;
    this.LIVE_END_PATTERN2 = /^(?:[01]?\d|2[0-3]):[0-5]\d(\t|\t\t)このライブトークは終了しました。(\r\n|\r|\n|$)/;

    this.currentDate = '';
    this.currentArrayElemNum = 0;
    this.result = {
      openChatName: '',
      date: [],
      liveCount: [],
      liveUser: [],
      elapsedTime: [],
      totalTime: [],
      acrossDate: [],
      timeframe: [],
    };
    this.startTime = '';
    this.firstLive = false;
    this.yesterdayLive = false;
  }

  analyzeText(text) {
    const lines = text.split('\n');
    this.getOpenChatName(lines[0]);

    for (const line of lines) {
      this.processLine(line);
    }

    // 最後の日付の処理
    this.addCurrentDateElemToResult();

    if (this.result.date.length === 0) {
      throw new Error('データが空です');
    }

    return this.result;
  }

  getOpenChatName(line) {
    if (!line.match(this.OPENCHAT_NAME_PATTERN)) {
      throw new Error('トーク履歴のファイルではありません');
    }

    this.result.openChatName = line.match(this.OPENCHAT_NAME_PATTERN)[1];
  }

  processLine(line) {
    if (this.isDateLine(line)) {
      if (this.currentDate && this.firstLive) {
        // 前日のデータを検出済みで、ライブトークの開催がある場合
        this.addCurrentDateElemToResult();
      }

      this.processDateLine(line);
    } else if (!this.currentDate) {
      // 前日のデータが未検出の場合
      return;
    } else if (!this.startTime && this.isLiveStartLine(line)) {
      this.processLiveStartLine(line);
      this.firstLive = true; // ログで最初にライブトークが始まったフラグ
    } else if (this.startTime && this.isLiveEndLine(line)) {
      this.processLiveEndLine(line);
    }
  }

  isDateLine(line) {
    const match = line.match(this.DATELINE_PATTERN);
    return match;
  }

  // 日付ごとの最終処理
  addCurrentDateElemToResult() {
    const n = this.currentArrayElemNum;

    if (this.startTime) {
      // 終了していないライブトークがある場合、日付が変わる時点での経過時間を記録する
      const elapsedTime = this.calcElapsedTime(this.startTime, '23:59', 1);
      this.result.elapsedTime[n].push(elapsedTime);

      // ライブトークが開かれた時間を変更する
      this.startTime = '00:00';

      // 時間帯の２つ目の要素
      const timeframeLen = this.result.timeframe[n].length;
      this.result.timeframe[n][timeframeLen - 1].push('23:59');
    }

    const elapsedTime = this.result.elapsedTime[n];
    if (!elapsedTime) {
      // ライブトークの経過時間がない場合（未開催）
      return;
    }

    if (this.result.acrossDate[n]) {
      // 前日から日付をまたいだライブトークがあった場合、回数を１回減らす
      this.result.liveCount[n] = elapsedTime.length - 1;
    } else {
      this.result.liveCount[n] = elapsedTime.length;
    }

    this.result.totalTime[n] = this.calcTotalTime(elapsedTime);
  }

  // 日付ごとの初期化処理
  processDateLine(line) {
    if (this.firstLive) {
      // ライブトークの開催が検出済の場合、配列の要素を増やす
      this.currentArrayElemNum++;
    }

    const matches = line.match(this.DATE_PATTERN);
    this.currentDate = this.removeCurrentYearPrefix(matches[0]);

    const n = this.currentArrayElemNum;
    this.result.date[n] = this.currentDate;
    this.result.liveCount[n] = 0;
    this.result.elapsedTime[n] = [];
    this.result.totalTime[n] = 0;
    this.result.timeframe[n] = [];

    if (this.startTime) {
      // 日付をまたいでライブトークが開かれている場合
      const yesterday = this.result.liveUser[n - 1];
      const len = yesterday.length;
      const yesterdayUser = yesterday[len - 1];

      // 前日の最後に開催したユーザー名を入れる
      this.result.liveUser[n] = [yesterdayUser];
      // 日付をまたいだユーザー名を記録する
      this.result.acrossDate[n] = yesterdayUser;
      // 時間帯の１つ目の要素
      this.result.timeframe[n].push([this.startTime]);
    } else {
      this.result.liveUser[n] = [];
      this.result.acrossDate[n] = '';
    }
  }

  // 日付が今年の場合、西暦を削除して返す
  removeCurrentYearPrefix(dateString) {
    const currentDate = new Date();
    const currentYear = currentDate.getFullYear().toString();

    if (dateString.startsWith(currentYear + "/")) {
      return dateString.slice(currentYear.length + 1);
    }

    return dateString;
  }

  isLiveStartLine(line) {
    return this.LIVE_START_USER_PATTERN.test(line);
  }

  isLiveEndLine(line) {
    return this.LIVE_END_PATTERN.test(line) || this.LIVE_END_PATTERN2.test(line);
  }

  processLiveStartLine(line) {
    const matches = line.match(/^(\d{1,2}:\d{1,2})/);
    this.startTime = matches[1];

    const liveUserMatches = line.match(this.LIVE_START_USER_PATTERN);
    const n = this.currentArrayElemNum;

    this.result.liveUser[n].push(liveUserMatches[1]);

    // 時間帯の１つ目の要素
    this.result.timeframe[n].push([this.startTime]);
  }

  processLiveEndLine(line) {
    const matches = line.match(/^(\d{1,2}:\d{1,2})/);
    const endTime = matches[1];

    const elapsedTime = this.calcElapsedTime(this.startTime, endTime);

    const n = this.currentArrayElemNum;

    this.result.elapsedTime[n].push(elapsedTime);

    // 時間帯の２つ目の要素
    const timeframeLen = this.result.timeframe[n].length;
    this.result.timeframe[n][timeframeLen - 1].push(endTime);

    this.startTime = '';
  }

  calcElapsedTime(startTime, endTime, adjust = 0) {
    const start = new Date(`2020/01/01 ${startTime}`).getTime();
    const end = new Date(`2020/01/01 ${endTime}`).getTime();

    const elapsed = end - start + (adjust * 60000);
    const minutes = Math.floor(elapsed / 60000);
    return minutes;
  }

  calcTotalTime(elapsedTime) {
    const totalMinutes = elapsedTime.reduce((total, time) => total + time, 0);
    return totalMinutes;
  }
}