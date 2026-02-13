import { Box, Typography } from '@mui/material'

/* function LinkText({ url, text }: { url: string; text: string }) {
  return (
    <Link href={url} color="inherit" target="_blank">
      {text}
    </Link>
  )
} */

export default function CommentTermText() {
  return (
    <Box display="flex" alignItems="center" justifyContent="center" gap="4px" mt="1rem">
      <Typography sx={{ fontSize: '12px' }} color="text.secondary" className="comment-term-text-color">
        LINE IDや他のSNSにおける個人の連絡先の投稿は禁止です。
      </Typography>
    </Box>
  )
}
