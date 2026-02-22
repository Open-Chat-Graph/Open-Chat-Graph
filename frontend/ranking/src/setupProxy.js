const { createProxyMiddleware } = require('http-proxy-middleware')
const { config } = require('dotenv')
const { resolve } = require('path')

config({ path: resolve(__dirname, '../../..', '.env') })

module.exports = function (app) {
  const port = process.env.HTTPS_PORT || '8443'

  app.use(
    ['/th', '/tw', '/oc', '/oclist', '/search', '/style', '/assets'],
    createProxyMiddleware({
      target: `https://localhost:${port}`,
      changeOrigin: true,
      secure: false,
    })
  )
}
