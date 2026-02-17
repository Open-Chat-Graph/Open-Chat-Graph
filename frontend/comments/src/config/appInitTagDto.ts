export const appInitTagDto: {
  openChatId: number
  recaptchaKey: string
} = JSON.parse(document.getElementById('comment-app-init-dto')!.textContent!)
