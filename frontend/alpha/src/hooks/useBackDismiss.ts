import { useEffect, useRef } from 'react'

/**
 * モーダル/ダイアログを「ブラウザバックで閉じる」に統一するための共通フック。
 *
 * アプリ全体の方針: 上に重ねる画面（オーバーレイ/モーダル）は必ずブラウザバックで閉じる。
 * 全面オーバーレイ（詳細・掲載履歴・フォルダ統合グラフ・画像）は URL ルート駆動なので元々バックで閉じる。
 * ローカル state で開閉する Radix ダイアログ（フォルダ・確認・検索保存 等）は
 * このフックを使って同じ挙動に揃える。
 *
 * 仕組み:
 *  - open=true の間だけ history にダミーエントリ（同一URL）を1つ積む。
 *  - popstate（戻る）が来たら onClose を呼ぶ。
 *  - ESC/オーバーレイ/ボタンなど open=false 側から閉じた時は、積んだダミーを history.back() で取り除く
 *    （戻るボタンに余計な1回が残らないように）。多重発火は popped フラグでガード。
 *
 * URL は変えない（pushState の第3引数を渡さない）ため React Router の location には影響しない。
 */
export function useBackDismiss(open: boolean, onClose: () => void): void {
  const onCloseRef = useRef(onClose)
  onCloseRef.current = onClose

  useEffect(() => {
    if (!open) return

    let popped = false
    window.history.pushState({ __modal: true }, '')

    const onPop = () => {
      popped = true
      onCloseRef.current()
    }
    window.addEventListener('popstate', onPop)

    return () => {
      window.removeEventListener('popstate', onPop)
      // 戻るで閉じたのでなければ、積んだダミーを取り除く
      if (!popped && window.history.state?.__modal) {
        window.history.back()
      }
    }
  }, [open])
}
