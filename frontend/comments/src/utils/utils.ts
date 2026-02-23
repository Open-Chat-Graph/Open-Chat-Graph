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

export async function fetchApi<T>(url: string, method: string = 'GET', bodyData: unknown) {
  const body = bodyData ? JSON.stringify(bodyData) : undefined

  const response = await fetch(url, {
    method,
    headers: { 'Content-Type': 'application/json' },
    body,
  })

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
