import { Chip } from '@mui/material'
import KeyboardArrowDownIcon from '@mui/icons-material/KeyboardArrowDown'

export default function ReadMoreCommentButton({ onClick }: { onClick: React.MouseEventHandler<HTMLDivElement> }) {
  return (
    <Chip
      label="もっと見る"
      onClick={onClick}
      variant="outlined"
      sx={{ minHeight: '48px', borderRadius: '99rem' }}
      icon={<KeyboardArrowDownIcon />}
    />
  )
}
