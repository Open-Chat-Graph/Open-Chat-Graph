import '@testing-library/jest-dom/vitest'

// jsdom does not decode images or implement canvas. Complete the same async
// compression path with deterministic browser API stubs.
Object.defineProperty(globalThis.Image.prototype, 'src', {
  configurable: true,
  set() {
    Object.defineProperty(this, 'width', { configurable: true, value: 640 })
    Object.defineProperty(this, 'height', { configurable: true, value: 480 })
    queueMicrotask(() => this.onload?.(new Event('load')))
  },
})

HTMLCanvasElement.prototype.getContext = (() => ({ drawImage() {} })) as unknown as typeof HTMLCanvasElement.prototype.getContext
HTMLCanvasElement.prototype.toBlob = function (callback) {
  callback(new Blob(['compressed'], { type: 'image/jpeg' }))
}
