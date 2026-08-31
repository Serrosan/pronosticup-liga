import { useEffect } from 'react'

function useAvisoSalir(hayCambiosSinGuardar) {
  useEffect(() => {
    function alIntentarSalir(evento) {
      if (hayCambiosSinGuardar) {
        evento.preventDefault()
      }
    }

    window.addEventListener('beforeunload', alIntentarSalir)
    return () => window.removeEventListener('beforeunload', alIntentarSalir)
  }, [hayCambiosSinGuardar])
}

export default useAvisoSalir