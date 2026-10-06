import { useEffect } from 'react'
import { ES_PRODUCCION, ENTORNO } from '../utils/entorno'

const RAYAS = 'repeating-linear-gradient(135deg, #F5B800 0 12px, #1A1A1A 12px 24px)'

/**
 * Marca bien visible de que NO estás en producción, para no confundir tu
 * ordenador con la app de verdad. En producción no pinta nada: los jugadores
 * nunca la ven. (El aviso de "PRODUCCIÓN" para el admin vive en AdminLayout.)
 *
 * Tres señales a la vez, ninguna depende solo del color:
 *  - una franja de rayas arriba del todo, como una cinta de obra;
 *  - una etiqueta con texto en la esquina inferior izquierda;
 *  - el título de la pestaña del navegador empieza por 🧪.
 */
function MarcaEntorno() {
  useEffect(() => {
    if (ES_PRODUCCION) return undefined

    const prefijo = `${ENTORNO.icono} `
    const marcar = () => {
      if (!document.title.startsWith(prefijo)) document.title = prefijo + document.title
    }

    marcar()
    // Cada página pone su propio título: se vuelve a marcar cuando cambia.
    const elementoTitulo = document.querySelector('title')
    if (!elementoTitulo) return undefined

    const observador = new MutationObserver(marcar)
    observador.observe(elementoTitulo, { childList: true, characterData: true, subtree: true })
    return () => observador.disconnect()
  }, [])

  if (ES_PRODUCCION) return null

  return (
    <>
      <div
        aria-hidden="true"
        className="fixed top-0 left-0 right-0 h-1.5 z-[70] pointer-events-none"
        style={{ backgroundImage: RAYAS }}
      />
      <div
        className="fixed bottom-2 left-2 z-[70] pointer-events-none rounded-md p-[3px] shadow-lg"
        style={{ backgroundImage: RAYAS }}
      >
        <div className="bg-[#1A1A1A] rounded px-2.5 py-1">
          <p className="font-body text-[11px] font-bold text-[#F5B800] leading-tight">
            {ENTORNO.icono} {ENTORNO.nombre}
          </p>
          <p className="font-body text-[9px] text-white/80 leading-tight">{ENTORNO.detalle}</p>
        </div>
      </div>
    </>
  )
}

export default MarcaEntorno
