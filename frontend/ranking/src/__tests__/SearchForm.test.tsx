import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { Provider, createStore } from 'jotai'
import SiteHeaderSearch from '../components/SiteHeaderSearch'
import { listParamsState, keywordState } from '../store/atom'

beforeEach(() => {
  Object.defineProperty(window, 'matchMedia', {
    writable: true,
    value: vi.fn().mockImplementation((query: string) => ({
      matches: false,
      media: query,
      onchange: null,
      addListener: vi.fn(),
      removeListener: vi.fn(),
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
      dispatchEvent: vi.fn(),
    })),
  })
})

function renderSearchForm() {
  const store = createStore()
  store.set(listParamsState, {
    sub_category: '',
    keyword: '',
    order: 'desc',
    sort: 'increase',
    list: 'daily',
  })
  store.set(keywordState, '')

  return render(
    <Provider store={store}>
      <MemoryRouter initialEntries={['/ranking']}>
        <SiteHeaderSearch siperSlideTo={vi.fn()}>
          <div>Header Content</div>
        </SiteHeaderSearch>
      </MemoryRouter>
    </Provider>
  )
}

describe('SiteHeaderSearch', () => {
  it('renders search button', () => {
    renderSearchForm()
    expect(screen.getByLabelText('検索')).toBeInTheDocument()
  })

  it('opens search form on button click', () => {
    renderSearchForm()
    const button = screen.getByLabelText('検索')
    fireEvent.click(button)
    expect(screen.getByPlaceholderText('オープンチャットを検索')).toBeInTheDocument()
  })

  it('renders header children', () => {
    renderSearchForm()
    expect(screen.getByText('Header Content')).toBeInTheDocument()
  })
})
