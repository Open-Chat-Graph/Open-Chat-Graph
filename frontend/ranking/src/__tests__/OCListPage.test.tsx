import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import OCListPage from '../pages/OCListPage'

// Mock fetch for SWR
beforeEach(() => {
  vi.stubGlobal(
    'fetch',
    vi.fn(() =>
      Promise.resolve({
        ok: true,
        json: () => Promise.resolve([]),
      })
    )
  )
  // Mock matchMedia for useMediaQuery
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

function renderWithRouter(path: string) {
  return render(
    <MemoryRouter initialEntries={[path]}>
      <Routes>
        <Route path="ranking" element={<OCListPage />} />
        <Route path="ranking/:category" element={<OCListPage />} />
        <Route path="404" element={<div data-testid="not-found">404</div>} />
      </Routes>
    </MemoryRouter>
  )
}

describe('OCListPage', () => {
  it('renders without crashing at /ranking', () => {
    const { container } = renderWithRouter('/ranking')
    expect(container.querySelector('.category-tab')).toBeInTheDocument()
  })

  it('renders category page at /ranking/20', () => {
    const { container } = renderWithRouter('/ranking/20')
    expect(container.querySelector('.category-tab')).toBeInTheDocument()
  })

  it('redirects to 404 for invalid category', () => {
    renderWithRouter('/ranking/99999')
    expect(screen.getByTestId('not-found')).toBeInTheDocument()
  })

  it('renders ranking list container', () => {
    const { container } = renderWithRouter('/ranking')
    // Swiper should render slides for each category
    expect(container.querySelector('.swiper')).toBeInTheDocument()
  })
})
