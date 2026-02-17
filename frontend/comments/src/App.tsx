import { Box, Typography } from '@mui/material'
import CommentList from './components/CommentList'
import CommentForm from './components/CommentForm'
import RecaptchaText from './components/RecaptchaText'
import { containerSx } from './style/sx'
import { RecoilRoot } from 'recoil'
import { GoogleReCaptchaProvider } from 'react-google-recaptcha-v3'
import { appInitTagDto } from './config/appInitTagDto'

export default function App() {
  return (
    <RecoilRoot>
      <GoogleReCaptchaProvider
        reCaptchaKey={appInitTagDto.recaptchaKey}
        scriptProps={{ async: true }}
      >
        <Box sx={containerSx}>
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
    </RecoilRoot>
  );
}
