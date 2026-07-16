;(function () {
  var button = document.getElementById('ranking-load-more')
  var list = document.getElementById('ranking-room-list')
  if (!button || !list || !window.DOMParser) return

  button.addEventListener('click', function (event) {
    event.preventDefault()
    if (button.getAttribute('aria-busy') === 'true') return
    button.setAttribute('aria-busy', 'true')
    fetch(button.href, { headers: { 'X-Ocg-Client': '1' } })
      .then(function (response) { if (!response.ok) throw new Error(String(response.status)); return response.text() })
      .then(function (html) {
        var documentNext = new DOMParser().parseFromString(html, 'text/html')
        var nextList = documentNext.getElementById('ranking-room-list')
        if (nextList) Array.from(nextList.children).forEach(function (item) { list.appendChild(item) })
        var nextButton = documentNext.getElementById('ranking-load-more')
        if (nextButton) {
          button.href = nextButton.href
          button.removeAttribute('aria-busy')
        } else {
          button.remove()
        }
      })
      .catch(function () { button.removeAttribute('aria-busy'); window.location.href = button.href })
  })
})()
