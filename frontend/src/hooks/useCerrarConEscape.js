import { useEffect } from 'react'

function useCerrarConEscape(activo, alCerrar) {
  useEffect(() => {
    if (!activo) return

    function alPulsarTecla(evento) {
      if (evento.key === 'Escape') alCerrar()
    }

    window.addEventListener('keydown', alPulsarTecla)
    return () => window.removeEventListener('keydown', alPulsarTecla)
  }, [activo, alCerrar])
}

export default useCerrarConEscape