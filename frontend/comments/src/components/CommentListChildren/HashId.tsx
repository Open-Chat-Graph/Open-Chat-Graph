import { hashToColor } from '../../utils/hashColor'

type Props = {
  userIdHash: string
  uaHash: string | null
  ipHash: string | null
}

export default function HashId({ userIdHash, uaHash, ipHash }: Props) {
  const hasLogData = uaHash !== null && ipHash !== null

  return (
    <span className="hash-id-line" style={{ display: 'block', marginTop: '4px', marginBottom: '1rem', wordSpacing: '2px' }}>
      {`ID:${userIdHash}`}
      {hasLogData && (
        <>
          {' UA:'}<span style={{ color: hashToColor(uaHash) }}>{uaHash}</span>
          {' IP:'}<span style={{ color: hashToColor(ipHash) }}>{ipHash}</span>
        </>
      )}
      <style>{`@media(max-width:600px){.hash-id-line{font-size:12px}}`}</style>
    </span>
  )
}
