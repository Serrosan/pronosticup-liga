import { lazy, useEffect, useState } from 'react'

const CLAVE_RECARGA = 'pronosticup-recarga-por-version'

/**
 * Carga una página solo cuando se abre (en vez de bajar toda la app de golpe).
 *
 * Si falla la descarga del trozo, casi siempre es porque se ha desplegado una
 * versión nueva mientras el jugador tenía la app abierta: los trozos viejos ya
 * no existen. En ese caso se recarga la página UNA vez para coger la versión
 * nueva; si vuelve a fallar, el error llega al ErrorBoundary de siempre.
 */
export function lazyPagina(importar) {
  return lazy(() =>
    importar().then(
      (modulo) => {
        try { sessionStorage.removeItem(CLAVE_RECARGA) } catch { /* sin almacenamiento */ }
        return modulo
      },
      (error) => {
        let yaRecargada = false
        try { yaRecargada = sessionStorage.getItem(CLAVE_RECARGA) === '1' } catch { /* sin almacenamiento */ }
        if (!yaRecargada) {
          try { sessionStorage.setItem(CLAVE_RECARGA, '1') } catch { /* sin almacenamiento */ }
          window.location.reload()
          return new Promise(() => {}) // la recarga ya está en marcha
        }
        throw error
      },
    ),
  )
}

/**
 * Lo que se ve mientras baja la página. Espera un poco antes de mostrarse para
 * que, con buena conexión, no aparezca un parpadeo de "Cargando…".
 */
export function CargandoPagina() {
  const [visible, setVisible] = useState(false)
  useEffect(() => {
    const temporizador = setTimeout(() => setVisible(true), 250)
    return () => clearTimeout(temporizador)
  }, [])
  if (!visible) return null
  return (
    <p className="font-body text-sm text-borde p-4" role="status">
      ⏳ Cargando…
    </p>
  )
}
