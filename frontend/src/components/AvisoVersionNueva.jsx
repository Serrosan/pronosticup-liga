import { useEffect, useRef, useState } from 'react'

// "Hay una versión nueva": cada build lleva su identificador (vite.config.js lo
// mete en el código y lo escribe en /version.json). Si la app abierta es de un
// build anterior al publicado, sale una franja discreta abajo. No se fuerza
// nada: quien esté a mitad de un pronóstico termina y actualiza cuando quiera.
//
// Se comprueba al volver a la app (cambiar de pestaña, desbloquear el móvil) y
// cada 15 minutos con la app a la vista. Solo en producción.

const VERSION_ACTUAL = import.meta.env.VITE_VERSION_APP
const CADA = 15 * 60 * 1000

async function versionPublicada() {
  try {
    // ?t= para que ni el navegador ni el service worker devuelvan una copia vieja.
    const respuesta = await fetch(`/version.json?t=${Date.now()}`, { cache: 'no-store' })
    if (!respuesta.ok) return null
    const datos = await respuesta.json() // si el servidor devuelve la página (no JSON), falla y se ignora
    return typeof datos?.version === 'string' ? datos.version : null
  } catch {
    return null
  }
}

// Antes de recargar se pide al service worker que se actualice también, para
// que la recarga no sirva nada de la versión anterior.
async function actualizar() {
  try {
    const registro = await navigator.serviceWorker?.getRegistration()
    await registro?.update()
  } catch {
    // da igual: se recarga igualmente
  }
  window.location.reload()
}

export default function AvisoVersionNueva() {
  const [nueva, setNueva] = useState(null)
  const [descartada, setDescartada] = useState(null)
  const ultimaComprobacion = useRef(0)

  useEffect(() => {
    if (!import.meta.env.PROD || !VERSION_ACTUAL) return undefined

    async function comprobar() {
      if (document.visibilityState !== 'visible') return
      if (Date.now() - ultimaComprobacion.current < 60 * 1000) return // como mucho una vez por minuto
      ultimaComprobacion.current = Date.now()

      const publicada = await versionPublicada()
      if (publicada && publicada !== VERSION_ACTUAL) setNueva(publicada)
    }

    comprobar()
    const intervalo = setInterval(comprobar, CADA)
    document.addEventListener('visibilitychange', comprobar)
    window.addEventListener('focus', comprobar)
    return () => {
      clearInterval(intervalo)
      document.removeEventListener('visibilitychange', comprobar)
      window.removeEventListener('focus', comprobar)
    }
  }, [])

  if (!nueva || nueva === descartada) return null

  return (
    <div
      role="status"
      className="fixed inset-x-0 z-[60] flex justify-center px-4 pointer-events-none"
      style={{ bottom: 'calc(env(safe-area-inset-bottom, 0px) + 1rem)' }}
    >
      <div className="pointer-events-auto w-full max-w-md bg-fondo border-2 border-acento/60 rounded-lg shadow-lg px-4 py-3 flex items-center gap-3">
        <span aria-hidden="true" className="text-lg">✨</span>
        <p className="font-body text-sm text-texto flex-1">Hay una versión nueva de PronostiCup</p>
        <button
          onClick={() => setDescartada(nueva)}
          className="font-body text-xs text-borde hover:text-texto px-2 py-2"
        >
          Luego
        </button>
        <button
          onClick={actualizar}
          className="font-body text-sm font-semibold bg-acento text-fondo rounded px-3 py-2 hover:brightness-110"
        >
          Actualizar
        </button>
      </div>
    </div>
  )
}
