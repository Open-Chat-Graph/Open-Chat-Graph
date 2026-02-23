export const appInitTagDto: {
  openChatId: number
  recaptchaKey: string
  openChatName?: string
} = JSON.parse(document.getElementById('comment-app-init-dto')!.textContent!)
