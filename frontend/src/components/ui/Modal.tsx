import { useEffect, type ReactNode } from 'react'

interface Props {
  title: string
  onClose: () => void
  children: ReactNode
  width?: number
}

export function Modal({ title, onClose, children, width = 460 }: Props) {
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        onClose()
      }
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [onClose])

  return (
    <div className="modal-backdrop" onClick={onClose}>
      <div
        className="modal"
        style={{ maxWidth: width }}
        onClick={(e) => e.stopPropagation()}
        role="dialog"
        aria-modal="true"
        aria-label={title}
      >
        <header className="modal__header">
          <h2 className="modal__title">{title}</h2>
          <button className="modal__close" onClick={onClose} type="button" aria-label="Cerrar">
            ✕
          </button>
        </header>
        <div className="modal__body">{children}</div>
      </div>
    </div>
  )
}
