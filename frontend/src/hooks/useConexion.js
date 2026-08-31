import { useState, useEffect } from 'react'

function useConexion() {
  const [conectado, setConectado] = useState(navigator.onLine)

  useEffect(() => {
    function marcarConectado() { setConectado(true) }
    function marcarDesconectado() { setConectado(false) }

    window.addEventListener('online', marcarConectado)
    window.addEventListener('offline', marcarDesconectado)

    return () => {
      window.removeEventListener('online', marcarConectado)
      window.removeEventListener('offline', marcarDesconectado)
    }
  }, [])

  return conectado
}

export default useConexion