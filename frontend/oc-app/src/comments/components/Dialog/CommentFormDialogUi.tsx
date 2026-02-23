import ConfirmDialogUi, { DialogParagraph, DialogSubTitle } from './ConfirmDialogUi'

export default function CommentFormDialogUi({
  open,
  handleClose,
  handleOk,
}: {
  open: boolean
  handleClose: () => void
  handleOk: () => void
}) {
  return (
    <ConfirmDialogUi
      title="投稿前のご確認"
      cancelText="キャンセル"
      okText="コメントを投稿する"
      {...{ open, handleClose, handleOk }}
    >
      <DialogParagraph>
        <DialogSubTitle>誹謗中傷をしていませんか？</DialogSubTitle>
        悪口や嫌がらせによって他人を著しく傷つける投稿は、名誉毀損や侮辱にあたる場合がありますのでおやめください。
      </DialogParagraph>
      <DialogParagraph>
        <DialogSubTitle>権利侵害をしていませんか？</DialogSubTitle>
        文章を投稿する際には、著作権、プライバシーなど、他者の権利を侵害していないことを十分にご確認ください。
      </DialogParagraph>
      <DialogParagraph>
        <DialogSubTitle>違法な投稿ではありませんか？</DialogSubTitle>
        荒らし行為、度を超えた連続投稿、犯罪行為に関する投稿など、本サイトの運営を妨害する行為はおやめください。
      </DialogParagraph>
      <DialogParagraph fontSize="11px">
        これらに該当したユーザーは、コメントの削除や投稿禁止などの制限を受ける他、法的責任を追及されることがあります。また、権利者から発信者情報開示請求があった場合、法令に則って開示することがあります。全てをご確認いただいた上で投稿してください。
      </DialogParagraph>
    </ConfirmDialogUi>
  )
}
