;(function () {
  'use strict'

  window.dataLayer = window.dataLayer || []
  var path = window.location.pathname
  var locale = path.indexOf('/tw/') === 0 || path === '/tw' ? 'tw' : path.indexOf('/th/') === 0 || path === '/th' ? 'th' : 'ja'
  var pageType = /^\/(?:tw\/|th\/)?oc\/\d+/.test(path)
    ? 'room'
    : /^\/(?:tw\/|th\/)?ranking/.test(path)
      ? 'ranking'
      : /^\/(?:tw\/|th\/)?recommend\//.test(path)
        ? 'theme'
        : path === '/' || path === '/tw' || path === '/th'
          ? 'home'
          : 'content'

  function push(name, values) {
    window.dataLayer.push(Object.assign({ event: name, page_type: pageType, locale: locale, template_version: 'seo-v1' }, values || {}))
  }

  function vital(name, value, id) {
    if (!Number.isFinite(value)) return
    push('web_vital', { metric_name: name, metric_value: Math.round(value * 1000) / 1000, metric_id: id || '' })
  }

  try {
    var navigation = performance.getEntriesByType('navigation')[0]
    if (navigation) vital('TTFB', navigation.responseStart, 'navigation')

    new PerformanceObserver(function (list) {
      var entries = list.getEntries()
      var last = entries[entries.length - 1]
      if (last) vital('LCP', last.startTime, last.id)
    }).observe({ type: 'largest-contentful-paint', buffered: true })

    var cls = 0
    new PerformanceObserver(function (list) {
      list.getEntries().forEach(function (entry) {
        if (!entry.hadRecentInput) cls += entry.value
      })
    }).observe({ type: 'layout-shift', buffered: true })

    var inp = 0
    new PerformanceObserver(function (list) {
      list.getEntries().forEach(function (entry) { inp = Math.max(inp, entry.duration || 0) })
    }).observe({ type: 'event', buffered: true, durationThreshold: 40 })

    addEventListener('visibilitychange', function () {
      if (document.visibilityState !== 'hidden') return
      vital('CLS', cls, 'page')
      if (inp) vital('INP', inp, 'page')
    }, { once: true })
  } catch (_) {}

  var reached = {}
  addEventListener('scroll', function () {
    var max = document.documentElement.scrollHeight - innerHeight
    if (max <= 0) return
    var percent = Math.round((scrollY / max) * 100)
    ;[25, 50, 75, 90].forEach(function (threshold) {
      if (!reached[threshold] && percent >= threshold) {
        reached[threshold] = true
        push('scroll_depth', { percent_scrolled: threshold })
      }
    })
  }, { passive: true })

  document.addEventListener('submit', function (event) {
    var form = event.target
    if (!(form instanceof HTMLFormElement)) return
    var input = form.querySelector('input[type="search"], input[name="keyword"]')
    if (input) push('site_search', { search_term: input.value || '' })
  })

  document.addEventListener('click', function (event) {
    var link = event.target.closest && event.target.closest('a[href]')
    if (!link) return
    var href = new URL(link.href, location.href)
    if (/\/(?:tw\/|th\/)?oc\/\d+\/jump$/.test(href.pathname)) {
      push('line_transition', { link_url: href.href })
    } else if (/\/(?:tw\/|th\/)?oc\/\d+$/.test(href.pathname)) {
      push('room_transition', { link_url: href.href })
    }
  })
})()
