import { createContext, useContext, useState, useCallback } from 'react'

const ToastContext = createContext(null)

export function ToastProvider({ children }) {
  const [toasts, setToasts] = useState([])

  const mostrar = useCallback((mensaje, tipo = 'exito') => {
    const id = Date.now() + Math.random()
    setToasts((prev) => [...prev, { id, mensaje, tipo }])
    setTimeout(() => {
      setToasts((prev) => prev.filter((t) => t.id !== id))
    }, 3500)
  }, [])

  const toast = {
    exito: (mensaje) => mostrar(mensaje, 'exito'),
    error: (mensaje) => mostrar(mensaje, 'error'),
    info: (mensaje) => mostrar(mensaje, 'info'),
  }

  return (
    <ToastContext.Provider value={toast}>
      {children}
      <div className="fixed bottom-4 right-4 z-50 flex flex-col gap-2 items-end">
        {toasts.map((t) => (
          <div
            key={t.id}
            className={`font-body text-sm px-4 py-2.5 rounded-lg shadow-lg max-w-xs animate-[fadeIn_0.2s_ease-out] ${
              t.tipo === 'exito' ? 'bg-acento text-fondo' : t.tipo === 'error' ? 'bg-red-500 text-white' : 'bg-fondo border border-borde/30 text-texto'
            }`}
          >
            {t.mensaje}
          </div>
        ))}
      </div>
    </ToastContext.Provider>
  )
}

export function useToast() {
  return useContext(ToastContext)
}