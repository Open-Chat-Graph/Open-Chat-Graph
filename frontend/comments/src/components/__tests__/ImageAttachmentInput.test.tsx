import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { render, screen, cleanup, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { RecoilRoot } from 'recoil'
import ImageAttachmentInput from '../ImageAttachmentInput'

let testKey = 0

function renderComponent() {
  testKey++
  return render(
    <RecoilRoot key={testKey}>
      <ImageAttachmentInput />
    </RecoilRoot>
  )
}

function createImageFile(name: string, size = 1000): File {
  const buffer = new ArrayBuffer(size)
  return new File([buffer], name, { type: 'image/jpeg' })
}

describe('ImageAttachmentInput', () => {
  beforeEach(() => {
    globalThis.URL.createObjectURL = () => 'blob:test'
    globalThis.URL.revokeObjectURL = () => {}
  })

  afterEach(() => {
    cleanup()
  })

  it('renders the add photo button and caption', () => {
    renderComponent()
    expect(screen.getByText('画像を添付（最大3枚）')).toBeInTheDocument()
  })

  it('accepts image files via file input', async () => {
    const user = userEvent.setup()
    renderComponent()

    const input = document.querySelector('input[type="file"]') as HTMLInputElement
    expect(input).toBeTruthy()

    const file = createImageFile('photo.jpg')
    await user.upload(input, file)

    await waitFor(() => {
      const previews = document.querySelectorAll('img[alt^="preview-"]')
      expect(previews.length).toBe(1)
    })
  })

  it('limits to 3 images maximum', async () => {
    const user = userEvent.setup()
    renderComponent()

    const input = document.querySelector('input[type="file"]') as HTMLInputElement

    const files = [
      createImageFile('a.jpg'),
      createImageFile('b.jpg'),
      createImageFile('c.jpg'),
      createImageFile('d.jpg'),
    ]
    await user.upload(input, files)

    await waitFor(() => {
      const previews = document.querySelectorAll('img[alt^="preview-"]')
      expect(previews.length).toBe(3)
    })
  })

  it('removes an image when close icon is clicked', async () => {
    const user = userEvent.setup()
    renderComponent()

    const input = document.querySelector('input[type="file"]') as HTMLInputElement
    await user.upload(input, [createImageFile('a.jpg'), createImageFile('b.jpg')])

    await waitFor(() => {
      const previews = document.querySelectorAll('img[alt^="preview-"]')
      expect(previews.length).toBe(2)
    })

    const closeIcons = document.querySelectorAll('[data-testid="CloseIcon"]')
    expect(closeIcons.length).toBe(2)
    await user.click(closeIcons[0])

    await waitFor(() => {
      const previews = document.querySelectorAll('img[alt^="preview-"]')
      expect(previews.length).toBe(1)
    })
  })

  it('filters out non-image files', async () => {
    const user = userEvent.setup()
    renderComponent()

    const input = document.querySelector('input[type="file"]') as HTMLInputElement
    const textFile = new File(['hello'], 'readme.txt', { type: 'text/plain' })
    await user.upload(input, textFile)

    // Wait a tick then verify no previews were added
    await waitFor(() => {
      const previews = document.querySelectorAll('img[alt^="preview-"]')
      expect(previews.length).toBe(0)
    })
  })
})
