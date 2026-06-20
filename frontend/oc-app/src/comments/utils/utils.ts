export function validateStringNotEmpty(str: string) {
  const normalizedStr = str.normalize('NFKC')
  const string = normalizedStr.replace(/[\u200B-\u200D\uFEFF]/g, '')
  return string.trim() !== ''
}

const weekdays = ['日', '月', '火', '水', '木', '金', '土']

export function formatDatetimeWithWeekdayFromMySql(datetime: string): string {
  const obj = new Date(datetime.replace(/-/g, '/'))
  return `${obj.toLocaleDateString()}(${weekdays[obj.getDay()]}) ${obj.toLocaleTimeString('en', {
    hour12: false,
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  })}`
}

export function convertTimeTagFormatFromMySql(datetime: string) {
  // " "を"T"に置換し、末尾に"+09:00"を追加
  return datetime.replace(' ', 'T') + '+09:00'
}

const ymdhis = new Intl.DateTimeFormat(undefined, {
  year: 'numeric',
  month: '2-digit',
  day: '2-digit',
  hour: '2-digit',
  minute: '2-digit',
  second: '2-digit',
})

export function getDatetimeString(date: Date = new Date()) {
  return ymdhis.format(date)
}

// サーバー一時障害(5xx)・ネットワーク到達不可など、時間をおいて再試行すれば回復しうるエラー。
// 表示側で「再読み込み」ボタンを出すために通常の通信エラー(4xx等)と区別する
export class ServerBusyError extends Error {
  name = 'ServerBusyError'
}

export async function fetchApi<T>(url: string, method: string = 'GET', bodyData: unknown) {
  const body = bodyData ? JSON.stringify(bodyData) : undefined

  let response: Response
  try {
    response = await fetch(url, {
      method,
      // X-Ocg-Client: サイト内JSからのfetchであることを示す（Cloudflare側で検証。直叩き収集対策）
      headers: { 'Content-Type': 'application/json', 'X-Ocg-Client': '1' },
      body,
    })
  } catch (e) {
    // ネットワーク到達不可・接続断などfetch自体がrejectしたケースは再試行可能扱い
    throw new ServerBusyError(e instanceof Error ? e.message : 'network error')
  }

  // 5xx(例: MySQLの接続上限で発生する503)はサーバー一時障害。再読み込みで回復しうるので専用エラーにする。
  // bodyがJSONでない可能性もあるためparseせずに投げる
  if (response.status >= 500) {
    throw new ServerBusyError(`server error ${response.status}`)
  }

  const data: T | ErrorResponse = await response.json()

  if (!response.ok) {
    const errorMessage = (data as ErrorResponse).error.message
    console.log(errorMessage)
    throw new Error(errorMessage)
  }

  return data as T
}

export class ApiError extends Error {
  constructor(
    message: string,
    public readonly status: number,
    public readonly serverCode: string,
    public readonly url: string,
    public readonly responseBody?: string
  ) {
    super(message)
    this.name = 'ApiError'
  }
}

export async function fetchApiFormData<T>(url: string, formData: FormData) {
  const response = await fetch(url, {
    method: 'POST',
    headers: { 'Accept': 'application/json' },
    body: formData,
  })

  const rawText = await response.text()
  let data: T | ErrorResponse
  try {
    data = JSON.parse(rawText)
  } catch {
    throw new ApiError(
      `サーバーから不正なレスポンスが返されました (HTTP ${response.status})`,
      response.status,
      '',
      url,
      rawText
    )
  }

  if (!response.ok) {
    const err = (data as ErrorResponse).error
    throw new ApiError(err.message, response.status, err.code, url)
  }

  return data as T
}
