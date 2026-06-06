import { CircularProgress } from '@mui/material'

export default function GradientCircularProgress({ margin = 'auto' }: { margin?: string }) {
  return (
    <>
      <svg width={0} height={0}>
        <defs>
          <linearGradient id="my_gradient" x1="0%" y1="0%" x2="0%" y2="100%">
            <stop offset="0%" style={{ stopColor: 'var(--c-spinner-grad-1)' }} />
            <stop offset="100%" style={{ stopColor: 'var(--c-spinner-grad-2)' }} />
          </linearGradient>
        </defs>
      </svg>
      <CircularProgress sx={{ 'svg circle': { stroke: 'url(#my_gradient)' }, m: margin }} />
    </>
  )
}
