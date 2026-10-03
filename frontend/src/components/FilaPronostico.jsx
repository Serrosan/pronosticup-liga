import { TIPOS, TIPOS_RESUELTOS, estaResuelto, tipoDePartido } from '../utils/tiposAcierto'

function Escudo({ url, alt }) {
  if (!url) return <span className="w-5 h-5 rounded-full bg-borde/15 flex items-center justify-center text-xs shrink-0">⚽</span>
  return <img src={url} alt={alt} className="w-5 h-5 object-contain shrink-0" />
}

export function LeyendaAciertos() {
  return (
    <div className="flex flex-wrap gap-x-4 gap-y-1.5 px-1 mb-4">
      {Object.entries(TIPOS).map(([clave, tipo]) => (
        <div key={clave} className="flex items-center gap-1.5">
          <span className="font-body text-xs font-bold leading-none w-3 text-center" style={{ color: tipo.color }}>
            {tipo.icono}
          </span>
          <span className="font-body text-[10px] text-borde">{tipo.leyenda}</span>
        </div>
      ))}
    </div>
  )
}

// Cómo fue la jornada de un vistazo, sin abrirla: cuántos de cada tipo.
export function ResumenJornada({ partidos }) {
  const cuenta = {}
  partidos.forEach((partido) => {
    const tipo = tipoDePartido(partido)
    if (tipo !== 'pendiente') cuenta[tipo] = (cuenta[tipo] ?? 0) + 1
  })

  const visibles = TIPOS_RESUELTOS.filter((tipo) => cuenta[tipo] > 0)
  if (visibles.length === 0) return null

  return (
    <span className="flex items-center gap-2.5">
      {visibles.map((tipo) => (
        <span
          key={tipo}
          title={`${cuenta[tipo]} × ${TIPOS[tipo].leyenda}`}
          className="font-marcador text-[11px] font-bold tabular-nums flex items-center gap-0.5"
          style={{ color: TIPOS[tipo].color }}
        >
          <span className="font-body">{TIPOS[tipo].icono}</span>
          {cuenta[tipo]}
        </span>
      ))}
    </span>
  )
}

// Fila de un pronóstico, compartida por Mis Pronósticos y el detalle de puntos
// de un usuario. Un solo eje: el marcador real en el centro exacto de la
// tarjeta y el pronóstico justo debajo, cifra bajo cifra. A su izquierda la
// etiqueta, a su derecha qué tipo de acierto fue. Los puntos, al borde derecho.
//
// Si se pasa onPulsar, toda la fila es un botón (flechita a la izquierda) y
// lo que venga en children se pinta debajo — p. ej. los pronósticos del grupo.
function FilaPronostico({ partido, etiqueta = 'pronóstico', onPulsar, abierto = false, tituloPulsar, children }) {
  const resuelto = estaResuelto(partido.goles_casa, partido.goles_fuera, partido.estado_partido)
  const tipo = TIPOS[tipoDePartido(partido)]
  const puntosCalculados = partido.puntos !== null && partido.puntos !== undefined
  const pulsable = typeof onPulsar === 'function'

  const Cabecera = pulsable ? 'button' : 'div'
  const propsCabecera = pulsable
    ? { type: 'button', onClick: onPulsar, 'aria-expanded': abierto, title: tituloPulsar }
    : {}

  return (
    <div className={`border-b border-borde/10 last:border-0 odd:bg-borde/5 px-2 sm:px-4 py-3 ${pulsable ? 'hover:bg-borde/10 transition' : ''}`}>
      <Cabecera
        {...propsCabecera}
        className="w-full grid grid-cols-[1.75rem_1fr_1.75rem] sm:grid-cols-[3.5rem_1fr_3.5rem] items-center gap-x-1.5 text-left"
      >
        <span className="flex items-center justify-start">
          {pulsable && (
            <span className={`text-borde text-base leading-none transition-transform ${abierto ? 'rotate-90' : ''}`}>›</span>
          )}
        </span>

        <span className="grid grid-cols-[1fr_auto_1fr] items-center gap-x-2 gap-y-1 min-w-0">
          <span className="flex items-center gap-1.5 justify-end min-w-0">
            <span className="font-body text-[13px] sm:text-sm text-texto truncate">{partido.equipo_local}</span>
            <Escudo url={partido.escudo_local} alt={partido.equipo_local} />
          </span>
          <span className={`font-marcador text-lg font-bold tabular-nums text-center leading-none ${resuelto ? 'text-texto' : 'text-borde'}`}>
            {resuelto ? `${partido.goles_casa}-${partido.goles_fuera}` : 'vs'}
          </span>
          <span className="flex items-center gap-1.5 min-w-0">
            <Escudo url={partido.escudo_visitante} alt={partido.equipo_visitante} />
            <span className="font-body text-[13px] sm:text-sm text-texto truncate">{partido.equipo_visitante}</span>
          </span>

          <span className="font-body text-[10px] text-borde text-right truncate">{etiqueta}</span>
          {partido.oculto ? (
            <>
              <span className="text-xs text-center leading-none">🔒</span>
              <span className="font-body text-[10px] font-semibold text-borde truncate">Oculto</span>
            </>
          ) : (
            <>
              <span
                className="font-marcador text-xs font-bold tabular-nums text-center leading-none"
                style={{ color: resuelto ? tipo.color : 'var(--color-texto)' }}
              >
                {partido.mi_pronostico ?? '—'}
              </span>
              <span className="font-body text-[10px] font-semibold truncate" style={{ color: tipo.color }}>
                {tipo.icono} {tipo.texto}
              </span>
            </>
          )}
        </span>

        <span className="text-right font-marcador text-sm font-bold tabular-nums whitespace-nowrap">
          {!partido.oculto && resuelto && puntosCalculados && (
            <span style={{ color: partido.puntos > 0 ? tipo.color : 'var(--color-borde)' }}>
              {partido.puntos > 0 ? `+${partido.puntos}` : '0'}
              <span className="hidden sm:inline font-body text-[10px] font-normal">pt</span>
            </span>
          )}
          {!partido.oculto && resuelto && !puntosCalculados && (
            <span className="text-borde" title="Puntos sin cerrar todavía">—</span>
          )}
        </span>
      </Cabecera>

      {partido.cartas?.length > 0 && (
        <div className="mt-2.5 flex flex-wrap justify-center gap-1.5">
          {partido.cartas.map((c, i) => (
            <span
              key={i}
              className={`font-body text-[11px] rounded-full px-2 py-0.5 border ${c.cumplida ? 'border-premio/40 text-premio bg-premio/10' : 'border-borde/25 text-borde'}`}
            >
              🃏 {c.nombre} {c.cumplida ? (c.puntos > 0 ? `+${c.puntos}` : 'activada') : '· sin efecto'}
            </span>
          ))}
        </div>
      )}

      {partido.nota_carta && (
        <p className="mt-1.5 text-center font-body text-[11px] text-premio">{partido.nota_carta}</p>
      )}

      {children}
    </div>
  )
}

export default FilaPronostico
