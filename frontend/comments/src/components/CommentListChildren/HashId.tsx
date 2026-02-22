import { Chip } from '@mui/material'
import { hashToColor } from '../../utils/hashColor'

type Props = {
  userIdHash: string
  uaHash: string | null
  ipHash: string | null
}

const chipSx = (color: string) => ({
  height: '18px',
  fontSize: '12px',
  fontWeight: 600,
  color,
  borderColor: color,
  opacity: 0.75,
  '& .MuiChip-label': {
    px: '6px',
    py: '1px',
  },
}) as const

export default function HashId({ userIdHash, uaHash, ipHash }: Props) {
  const hasLogData = uaHash !== null && ipHash !== null

  return (
    <span style={{ display: 'flex', alignItems: 'center', gap: '7px', flexWrap: 'wrap', marginTop: '6px', marginBottom: '1rem' }}>
      <Chip label={`ID: ${userIdHash}`} variant="outlined" size="small" sx={chipSx(hashToColor(userIdHash))} />
      {hasLogData && (
        <>
          <Chip label={`UA: ${uaHash}`} variant="outlined" size="small" sx={chipSx(hashToColor(uaHash))} />
          <Chip label={`IP: ${ipHash}`} variant="outlined" size="small" sx={chipSx(hashToColor(ipHash))} />
        </>
      )}
    </span>
  )
}
