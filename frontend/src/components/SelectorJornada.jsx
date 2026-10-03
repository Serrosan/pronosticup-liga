import { useState } from 'react'

// Navegación de jornadas que aguanta 38: flechas para ir de una en una y, al
// pulsar el nombre de la jornada, una rejilla con todas para saltar directo.
//
//   jornadas  — números disponibles, en orden (p. ej. [1..38] o las cerradas)
//   valor     — jornada actual, o null si no hay ninguna elegida
//   grande    — true: aspecto de título de página; false: aspecto de pastilla
function SelectorJornada({ jornadas, valor, onCambiar, grande = false, textoVacio = 'Elegir jornada' }) {
  const [abierto, setAbierto] = useState(false)

  const hayValor = valor !== null && valor !== undefined
  const indice = hayValor ? jornadas.indexOf(valor) : -1
  const anterior = indice > 0 ? jornadas[indice - 1] : null
  const siguiente = indice >= 0 && indice < jornadas.length - 1 ? jornadas[indice + 1] : null

  function elegir(jornada) {
    setAbierto(false)
    onCambiar(jornada)
  }

  const claseFlecha = `font-body text-texto disabled:opacity-30 disabled:cursor-not-allowed hover:text-acento px-2 ${grande ? 'text-xl' : 'text-base'}`

  const claseCentro = grande
    ? 'font-display text-xl text-texto whitespace-nowrap hover:text-acento transition flex items-center gap-1.5'
    : `font-body text-sm px-3 py-1.5 rounded-full transition whitespace-nowrap flex items-center gap-1.5 ${
        hayValor ? 'bg-acento text-fondo font-semibold' : 'text-texto border border-borde/40 hover:bg-borde/10'
      }`

  return (
    <div className="relative inline-flex items-center gap-1">
      <button
        type="button"
        onClick={() => elegir(anterior)}
        disabled={anterior === null}
        className={claseFlecha}
        aria-label="Jornada anterior"
      >
        ←
      </button>

      <button
        type="button"
        onClick={() => setAbierto(!abierto)}
        aria-haspopup="true"
        aria-expanded={abierto}
        title="Ir a otra jornada"
        className={claseCentro}
      >
        {hayValor ? `Jornada ${valor}` : textoVacio}
        <span className={`text-xs ${grande || !hayValor ? 'text-borde' : ''}`}>▾</span>
      </button>

      <button
        type="button"
        onClick={() => elegir(siguiente)}
        disabled={siguiente === null}
        className={claseFlecha}
        aria-label="Jornada siguiente"
      >
        →
      </button>

      {abierto && (
        <>
          <div className="fixed inset-0 z-30" onClick={() => setAbierto(false)} />
          <div className="absolute left-1/2 -translate-x-1/2 top-full mt-2 z-40 w-72 max-w-[calc(100vw-2rem)] bg-fondo border border-borde/30 rounded-lg shadow-lg p-3">
            <p className="font-body text-[10px] uppercase tracking-widest text-borde mb-2 text-center">Ir a la jornada</p>
            <div className="grid grid-cols-7 gap-1.5">
              {jornadas.map((jornada) => (
                <button
                  key={jornada}
                  type="button"
                  onClick={() => elegir(jornada)}
                  aria-current={jornada === valor ? 'true' : undefined}
                  className={`h-9 rounded font-marcador text-sm tabular-nums transition ${
                    jornada === valor
                      ? 'bg-acento text-fondo font-bold'
                      : 'text-texto border border-borde/30 hover:bg-borde/10'
                  }`}
                >
                  {jornada}
                </button>
              ))}
            </div>
          </div>
        </>
      )}
    </div>
  )
}

export default SelectorJornada
