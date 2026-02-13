import { Box, Link, Typography } from '@mui/material'

function LinkText({ url, text }: { url: string; text: string }) {
  return (
    <Link href={url} color="inherit" target="_blank" rel="noopener">
      {text}
    </Link>
  )
}

export default function RecaptchaText() {
  return (
    <Box display="flex" alignItems="center" justifyContent="center" gap="4px">
      <Typography sx={{ fontSize: '11px' }} color="text.secondary" className="comment-term-text-color">
        このサイトはreCAPTCHAによって保護されており、Googleの
        <LinkText url="https://www.google.com/intl/ja/policies/privacy/" text="プライバシーポリシー" />
        と
        <LinkText url="https://www.google.com/intl/ja/policies/terms/" text="利用規約" />
        が適用されます。
      </Typography>
    </Box>
  )
}
