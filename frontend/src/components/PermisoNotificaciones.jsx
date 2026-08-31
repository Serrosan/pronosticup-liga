import { useState, useEffect } from 'react'

function PermisoNotificaciones() {
  const [mostrar, setMostrar] = useState(false)

  useEffect(() => {
    if ('Notification' in window && Notification.permission === 'default' && !localStorage.getItem('avisos-descartado')) {
      setMostrar(true)
    }
  }, [])

  function activar() {
    Notification.requestPermission().then(() => setMostrar(false))
  }

  function descartar() {
    localStorage.setItem('avisos-descartado', '1')
    setMostrar(false)
  }

  if (!mostrar) return null

  return (
    <div className="bg-premio/10 border border-premio/30 rounded-lg px-4 py-3 flex items-center justify-between gap-3 mb-4">
      <p className="font-body text-sm text-texto">🔔 Activa los avisos para enterarte al momento mientras tengas la app abierta</p>
      <div className="flex gap-2 shrink-0">
        <button onClick={activar} className="font-body text-xs font-semibold bg-acento text-fondo rounded px-3 py-1.5">Activar</button>
        <button onClick={descartar} className="font-body text-xs text-borde">Ahora no</button>
      </div>
    </div>
  )
}

export default PermisoNotificaciones