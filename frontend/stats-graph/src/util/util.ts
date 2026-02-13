export function updateURLSearchParams(params: { [key: string]: string }): URL {
  const url = new URL(window.location.href);
  url.search = ''

  for (let k in params) {
    if (params[k]) {
      url.searchParams.set(k, params[k])
    } else {
      url.searchParams.delete(k)
    }
  }

  return url
}
