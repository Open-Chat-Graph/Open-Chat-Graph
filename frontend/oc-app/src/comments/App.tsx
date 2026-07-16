import { Box, Typography } from '@mui/material'
import { useState } from 'react'
import CommentList from './components/CommentList'
import CommentForm from './components/CommentForm'
import RecaptchaText from './components/RecaptchaText'
import { containerSx } from './style/sx'
import { Provider } from 'jotai'
import { GoogleReCaptchaProvider } from 'react-google-recaptcha-v3'
import { appInitTagDto } from './config/appInitTagDto'

export default function App() {
  const [recaptchaActive, setRecaptchaActive] = useState(false)
  const activateRecaptcha = () => setRecaptchaActive(true)

  return (
    <Provider>
      <GoogleReCaptchaProvider
        // 空キーでは Google のスクリプトを取得しない。投稿フォーム送信または
        // コメント内ボタン（通報を含む）の操作時に初めて実キーへ切り替える。
        reCaptchaKey={recaptchaActive ? appInitTagDto.recaptchaKey : ''}
        scriptProps={{ async: true }}
      >
        <Box
          sx={containerSx}
          onSubmitCapture={activateRecaptcha}
          onClickCapture={(event) => {
            if ((event.target as HTMLElement).closest('button')) activateRecaptcha()
          }}
        >
          {!appInitTagDto.recaptchaKey && (
            <Box sx={containerSx}>
              <Typography color="error" fontWeight="bold">
                reCAPTCHAサイトキーが設定されていません。
              </Typography>
            </Box>
          )}
          <CommentForm />
          <CommentList limit={10} />
          <RecaptchaText />
        </Box>
      </GoogleReCaptchaProvider>
    </Provider>
  )
}
