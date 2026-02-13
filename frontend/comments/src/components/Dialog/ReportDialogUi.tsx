import ConfirmDialogUi, { DialogParagraph } from './ConfirmDialogUi'

export default function ReportDialogUi({
  open,
  isLoading,
  result = 'unsent',
  handleClose,
  handleOk,
  id,
}: {
  open: boolean
  isLoading: boolean
  result?: FetchResultType
  handleClose: () => void
  handleOk: () => void
  id?: number
}) {
  return (
    <ConfirmDialogUi
      open={open}
      handleOk={handleOk}
      title={result !== 'fail' ? `コメントNo.${id}を通報` : 'エラー'}
      cancelText={isLoading ? undefined : result !== 'unsent' ? '閉じる' : 'キャンセル'}
      okText={isLoading || result !== 'unsent' ? undefined : '通報する'}
      isLoading={isLoading}
      handleClose={isLoading ? undefined : handleClose}
    >
      {result === 'unsent' && <DialogParagraph>削除すべき不適切なコメントとして通報しますか？</DialogParagraph>}
      {result === 'done' && <DialogParagraph>通報しました</DialogParagraph>}
      {result === 'fail' && (
        <DialogParagraph>
          サーバーとの通信に失敗しました。連続アクセスを防止しているため、しばらく待ってから再度お試しください。
        </DialogParagraph>
      )}
    </ConfirmDialogUi>
  )
}
