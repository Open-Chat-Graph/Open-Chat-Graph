import { Theme } from '@emotion/react'
import { Box, TextField, Stack, Button, SxProps } from '@mui/material'
import useValidateInput from '../hooks/useValidateInput'
import { validateStringNotEmpty as isEmpty } from '../utils/utils'
import GradientCircularProgress from './GradientCircularProgress'
import CommentTermText from './CommentTermText'
import { inputNameState } from '../state/inputNameState'
import { inputTextState } from '../state/inputTextState'
import { FormEventHandler } from 'react'
import { appInitTagDto } from '../config/appInitTagDto'
import CommentFormTitle from './CommentFormTitle'

const textFieldSx: SxProps<Theme> = {
  width: '100%',
}

export default function CommentFormUi({
  onSubmit,
  isSending,
}: {
  onSubmit: FormEventHandler<HTMLFormElement>
  isSending: boolean
}) {
  const nameProps = useValidateInput(20, inputNameState)
  const textProps = useValidateInput(1000, inputTextState)

  return (
    <div>
      <CommentFormTitle />
      <Box
        component="form"
        sx={{
          '& > :not(style)': { mt: 1, mb: 1 },
        }}
        noValidate
        autoComplete="off"
        onSubmit={onSubmit}
      >
        <TextField label="ニックネーム（任意）" variant="standard" sx={textFieldSx} name="name" {...nameProps} />
        <TextField
          label={appInitTagDto.openChatId ? 'オープンチャットについてのコメント' : 'コメント'}
          variant="standard"
          multiline
          minRows={3}
          sx={textFieldSx}
          name="text"
          {...textProps}
        />
        <Stack direction="row" spacing={2} justifyContent="flex-end" minHeight={'40px'}>
          <CommentTermText />
          <Box minWidth="100px" display="flex">
            {isSending ? (
              <GradientCircularProgress />
            ) : (
              <Button
                type="submit"
                variant="contained"
                disabled={!isEmpty(textProps.value)}
                sx={{ m: 'auto', ml: 'auto', height: 'fit-content' }}
              >
                投稿する
              </Button>
            )}
          </Box>
        </Stack>
      </Box>
    </div>
  )
}
