import { Box, Typography } from '@mui/material'

export default function CommentFormTitle() {
  return (
    <Box display="flex" flexDirection="row" alignItems="center" gap={1} mt="1rem">
      <Typography variant="h3" sx={{ fontSize: '13px', fontWeight: 'bold' }}>
        コメントする
      </Typography>
      <Typography sx={{ fontSize: '12px' }} color="text.secondary" className="comment-term-text-color">
        ユーザー登録不要の匿名投稿
      </Typography>
    </Box>
  )
}
