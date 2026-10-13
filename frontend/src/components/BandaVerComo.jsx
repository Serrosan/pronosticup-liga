import { useEffect } from 'react'
import { leerVerComo, salirDeVerComo } from '../utils/verComo'

// Franja fija abajo mientras miras la app como otro usuario. Imposible de
// olvidar: icono + texto + botón, no solo un color.
function BandaVerComo() {
  const verComo = leerVerComo()

  // Que la franja no tape el final de ninguna página.
  useEffect(() => {
    if (!verComo) return undefined
    const antes = document.body.style.paddingBottom
    document.body.style.paddingBottom = '3.5rem'
    return () => { document.body.style.paddingBottom = antes }
  }, [verComo?.id])

  if (!verComo) return null

  return (
    <div className="fixed bottom-0 inset-x-0 z-[80] bg-red-600 text-white shadow-[0_-4px_16px_rgba(0,0,0,0.35)]">
      <div className="max-w-5xl mx-auto px-4 py-2 flex items-center justify-between gap-3">
        <p className="font-body text-sm leading-tight min-w-0">
          <span aria-hidden="true">👁 </span>
          Viendo la app como <span className="font-semibold">{verComo.nombre}</span>
          <span className="hidden sm:inline"> · solo lectura, no se guarda nada</span>
        </p>
        <button
          type="button"
          onClick={salirDeVerComo}
          className="font-body text-sm font-semibold bg-white text-red-700 rounded-full px-4 py-1.5 shrink-0 hover:bg-red-50"
        >
          Salir
        </button>
      </div>
    </div>
  )
}

export default BandaVerComo
