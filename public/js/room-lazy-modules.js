(() => {
  const targets = Array.from(document.querySelectorAll('[data-lazy-module]'))
  if (!targets.length) return

  const load = (target) => {
    if (target.dataset.moduleLoaded === 'true') return
    target.dataset.moduleLoaded = 'true'

    const styleUrl = target.dataset.lazyStyle
    if (styleUrl && !document.querySelector(`link[href="${CSS.escape(styleUrl)}"]`)) {
      const link = document.createElement('link')
      link.rel = 'stylesheet'
      link.crossOrigin = 'anonymous'
      link.href = styleUrl
      document.head.append(link)
    }

    const script = document.createElement('script')
    script.type = 'module'
    script.crossOrigin = 'anonymous'
    script.src = target.dataset.lazyModule
    document.body.append(script)
  }

  if (!('IntersectionObserver' in window)) {
    targets.forEach(load)
    return
  }

  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return
      load(entry.target)
      observer.unobserve(entry.target)
    })
  }, { rootMargin: '500px 0px' })

  targets.forEach((target) => {
    observer.observe(target)
    target.addEventListener('pointerdown', () => load(target), { once: true })
    target.addEventListener('focusin', () => load(target), { once: true })
  })

  if (location.hash.startsWith('#graph')) {
    const graph = document.querySelector('.openchat-graph-section[data-lazy-module]')
    if (graph) load(graph)
  }
})()
