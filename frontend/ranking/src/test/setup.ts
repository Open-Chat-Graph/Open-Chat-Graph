import '@testing-library/jest-dom/vitest'

// Mock IntersectionObserver (not available in jsdom)
class MockIntersectionObserver {
  readonly root: Element | null = null
  readonly rootMargin: string = ''
  readonly thresholds: ReadonlyArray<number> = []
  constructor() {}
  observe() {}
  unobserve() {}
  disconnect() {}
  takeRecords(): IntersectionObserverEntry[] {
    return []
  }
}
globalThis.IntersectionObserver = MockIntersectionObserver as any

// Setup the arg-dto JSON element that config.ts reads on module load
const argDto = {
  baseUrl: '',
  rankingUpdatedAt: '2025/1/5 1:20',
  modifiedUpdatedAtDate: '2025-01-04',
  hourlyUpdatedAt: '2025-01-05 00:30:00',
  subCategories: {
    '0': ['テスト1', 'テスト2'],
    '20': ['ファッション', 'コスメ'],
  },
  urlRoot: '',
  openChatCategory: [
    ['全部', 0],
    ['流行', 20],
    ['金融', 40],
  ] as [string, number][],
}

const script = document.createElement('script')
script.type = 'application/json'
script.id = 'arg-dto'
script.textContent = JSON.stringify(argDto)
document.body.appendChild(script)

const root = document.createElement('div')
root.id = 'root'
document.body.appendChild(root)
