import { useState, useEffect } from 'react'
import { useNavigate, useLocation } from 'react-router-dom'

const RUTAS_PUBLICAS = ['/login', '/register', '/forgot-password', '/reset-password']

function AvisoSesionCaducada() {
  const [mostrar, setMostrar] = useState(false)
  const navigate = useNavigate()
  const location = useLocation()

  useEffect(() => {
    function alRecibirEvento() {
      const enRutaPublica = RUTAS_PUBLICAS.some((ruta) => location.pathname.startsWith(ruta))
      if (!enRutaPublica) {
        setMostrar(true)
      }
    }
    window.addEventListener('sesion-caducada', alRecibirEvento)
    return () => window.removeEventListener('sesion-caducada', alRecibirEvento)
  }, [location.pathname])

  function irALogin() {
    setMostrar(false)
    navigate('/login')
  }

  if (!mostrar) return null

  return (
    <div className="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4">
      <div className="bg-fondo border-2 border-premio rounded-lg p-6 max-w-sm w-full text-center">
        <p className="text-3xl mb-3">🔒</p>
        <h2 className="font-display text-lg text-texto mb-2">Tu sesión ha caducado</h2>
        <p className="font-body text-sm text-borde mb-5">Por seguridad, tienes que volver a iniciar sesión.</p>
        <button
          onClick={irALogin}
          className="bg-acento text-fondo font-body font-semibold text-sm rounded px-6 py-2.5 hover:brightness-110"
        >
          Ir a iniciar sesión
        </button>
      </div>
    </div>
  )
}

export default AvisoSesionCaducada