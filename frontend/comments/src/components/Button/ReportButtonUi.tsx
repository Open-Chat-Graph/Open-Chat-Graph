import { Button } from '@mui/material'

export default function ReportButtonUi({ onClick }: { onClick: React.MouseEventHandler<HTMLButtonElement> }) {
  return (
    <Button
      color="inherit"
      sx={{
        p: 0,
        m: 0,
        minWidth: 0,
        ml: 1,
        fontSize: 'inherit',
        lineHeight: "unset"
      }}
      onClick={onClick}
    >
      [通報]
    </Button>
  )
}