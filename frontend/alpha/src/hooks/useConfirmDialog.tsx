import { useState } from 'react'

export interface ConfirmOptions {
  title: string
  description: string
  confirmText?: string
  cancelText?: string
  variant?: 'default' | 'destructive'
}

export function useConfirmDialog() {
  const [isOpen, setIsOpen] = useState(false)
  const [options, setOptions] = useState<ConfirmOptions | null>(null)
  const [resolver, setResolver] = useState<((value: boolean) => void) | null>(null)

  const confirm = (opts: ConfirmOptions): Promise<boolean> => {
    return new Promise((resolve) => {
      setOptions(opts)
      setResolver(() => resolve)
      setIsOpen(true)
    })
  }

  const handleConfirm = () => {
    resolver?.(true)
    setIsOpen(false)
  }

  const handleCancel = () => {
    resolver?.(false)
    setIsOpen(false)
  }

  const handleOpenChange = (open: boolean) => {
    if (!open) {
      resolver?.(false)
    }
    setIsOpen(open)
  }

  return {
    isOpen,
    options,
    confirm,
    handleConfirm,
    handleCancel,
    handleOpenChange,
  }
}
