import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import client from '../api/client'
import EstadoVacio from '../components/EstadoVacio'
import SkeletonLista from '../components/SkeletonLista'
import TicketHeader from '../components/TicketHeader'

function Escudo({ url, alt }) {
  if (!url) return <span className="w-5 h-5 rounded-full bg-borde/15 flex items-center justify-center text-xs shrink-0">⚽</span>
  return <img src={url} alt={alt} className="w-5 h-5 object-contain shrink-0" />
}

function FotoJugador({ url, nombre }) {
  if (url) return <img src={url} alt={nombre} className="w-8 h-8 rounded-full object-cover shrink-0" />
  return (
    <span className="w-8 h-8 rounded-full bg-acento/10 border border-acento/20 flex items-center justify-center text-xs font-semibold shrink-0 text-acento">
      {nombre?.[0]}
    </span>
  )
}

function AvatarPequeno({ url, nombre }) {
  if (url) return <img src={url} alt={nombre} className="w-6 h-6 rounded-full object-cover shrink-0" />
  return (
    <span className="w-6 h-6 rounded-full bg-acento/15 flex items-center justify-center text-[10px] font-semibold shrink-0 text-acento">
      {nombre?.[0]?.toUpperCase()}
    </span>
  )
}

// Cada tipo de resultado lleva color + icono + palabra. El color nunca es la
// única pista: el icono y la palabra dicen lo mismo por sí solos.
const TIPOS = {
  exacto: { color: '#22C55E', icono: '★', texto: 'Exacto', leyenda: 'Resultado exacto' },
  diferencia: { color: '#F59E0B', icono: '≈', texto: 'Diferencia', leyenda: 'Diferencia (o empate cercano)' },
  signo: { color: 'var(--color-acento)', icono: '✓', texto: 'Signo', leyenda: 'Solo el signo' },
  fallo: { color: '#EF4444', icono: '✕', texto: 'Fallo', leyenda: 'Fallo' },
  pendiente: { color: 'var(--color-borde)', icono: '○', texto: 'Pendiente', leyenda: 'Pendiente' },
}

const TIPOS_RESUELTOS = ['exacto', 'diferencia', 'signo', 'fallo']

// Fondo suave del mismo color. color-mix funciona también cuando el color es
// una variable CSS (pegarle "1F" detrás a un var(...) no era un color válido).
function fondoSuave(color) {
  return `color-mix(in srgb, ${color} 14%, transparent)`
}

function estaResuelto(golesCasa, golesFuera, estadoPartido) {
  return estadoPartido === 'Jugado' && golesCasa !== null && golesFuera !== null
}

function calcularResultado1x2(golesLocal, golesVisitante) {
  if (golesLocal > golesVisitante) return 'Local'
  if (golesLocal < golesVisitante) return 'Visitante'
  return 'Empate'
}

function tipoDePronostico(prediccionTexto, golesCasa, golesFuera) {
  const [golesLocalPred, golesVisitantePred] = prediccionTexto.split('-').map(Number)

  if (golesLocalPred === golesCasa && golesVisitantePred === golesFuera) {
    return 'exacto'
  }

  const resultadoReal = calcularResultado1x2(golesCasa, golesFuera)
  const resultadoPredicho = calcularResultado1x2(golesLocalPred, golesVisitantePred)

  if (resultadoPredicho !== resultadoReal) {
    return 'fallo'
  }

  if (resultadoReal === 'Empate') {
    const margen = Math.abs(golesLocalPred - golesCasa)
    return margen === 1 ? 'diferencia' : 'signo'
  }

  const diferenciaReal = golesCasa - golesFuera
  const diferenciaPredicha = golesLocalPred - golesVisitantePred
  return diferenciaReal === diferenciaPredicha ? 'diferencia' : 'signo'
}

function tipoDePartido(partido) {
  if (!partido.mi_pronostico) return 'pendiente'
  if (!estaResuelto(partido.goles_casa, partido.goles_fuera, partido.estado_partido)) return 'pendiente'
  return tipoDePronostico(partido.mi_pronostico, partido.goles_casa, partido.goles_fuera)
}

function LeyendaColores() {
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
function ResumenJornada({ partidos }) {
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

function OtrosPronosticos({ jornada, idPartido, golesCasa, golesFuera, estadoPartido }) {
  const { data, isLoading } = useQuery({
    queryKey: ['otros-pronosticos', jornada],
    queryFn: async () => (await client.get(`/api/v1/jornadas/${jornada}/otros-pronosticos`)).data.data,
  })

  const otros = data?.[idPartido] ?? []
  const resuelto = estaResuelto(golesCasa, golesFuera, estadoPartido)

  return (
    <div className="mt-3 bg-borde/5 border border-borde/10 rounded-lg overflow-hidden">
      <p className="font-body text-[10px] uppercase tracking-widest text-borde px-3 pt-2.5 pb-1.5">
        Pronósticos del grupo
      </p>
      {isLoading ? (
        <p className="font-body text-xs text-borde px-3 pb-2.5">Cargando...</p>
      ) : otros.length === 0 ? (
        <p className="font-body text-xs text-borde px-3 pb-2.5">Nadie más pronosticó este partido.</p>
      ) : (
        <div className="flex flex-col divide-y divide-borde/10">
          {otros.map((o, i) => {
            const tipo = resuelto ? TIPOS[tipoDePronostico(o.pronostico, golesCasa, golesFuera)] : null
            return (
              <div key={i} className="flex items-center gap-2.5 px-3 py-2">
                <AvatarPequeno url={o.avatar_url} nombre={o.usuario} />
                <p className="font-body text-xs text-texto flex-1 truncate">{o.usuario}</p>
                <span
                  title={tipo?.leyenda}
                  className="font-marcador text-xs font-bold rounded px-2 py-0.5 tabular-nums flex items-center gap-1.5"
                  style={tipo ? { backgroundColor: fondoSuave(tipo.color), color: tipo.color } : { color: 'var(--color-texto)' }}
                >
                  {tipo && <span className="font-body">{tipo.icono}</span>}
                  {o.pronostico}
                </span>
              </div>
            )
          })}
        </div>
      )}
    </div>
  )
}

// Un solo eje por fila: el marcador real en el centro exacto de la tarjeta y
// tu pronóstico justo debajo, cifra bajo cifra. A su izquierda la etiqueta, a
// su derecha qué tipo de acierto fue. Los puntos, al borde derecho.
// Si la jornada está cerrada, toda la fila se pulsa para ver al grupo.
function FilaPartido({ partido, jornada, jornadaBloqueada }) {
  const [mostrarOtros, setMostrarOtros] = useState(false)

  const resuelto = estaResuelto(partido.goles_casa, partido.goles_fuera, partido.estado_partido)
  const tipo = TIPOS[tipoDePartido(partido)]
  const puntosCalculados = partido.puntos !== null && partido.puntos !== undefined

  const Cabecera = jornadaBloqueada ? 'button' : 'div'
  const propsCabecera = jornadaBloqueada
    ? {
        type: 'button',
        onClick: () => setMostrarOtros(!mostrarOtros),
        'aria-expanded': mostrarOtros,
        title: mostrarOtros ? 'Ocultar pronósticos del grupo' : 'Ver pronósticos del grupo',
      }
    : {}

  return (
    <div className={`border-b border-borde/10 last:border-0 odd:bg-borde/5 px-2 sm:px-4 py-3 ${jornadaBloqueada ? 'hover:bg-borde/10 transition' : ''}`}>
      <Cabecera
        {...propsCabecera}
        className="w-full grid grid-cols-[1.75rem_1fr_1.75rem] sm:grid-cols-[3.5rem_1fr_3.5rem] items-center gap-x-1.5 text-left"
      >
        <span className="flex items-center justify-start">
          {jornadaBloqueada && (
            <span className={`text-borde text-base leading-none transition-transform ${mostrarOtros ? 'rotate-90' : ''}`}>›</span>
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

          <span className="font-body text-[10px] text-borde text-right truncate">tu pronóstico</span>
          <span
            className="font-marcador text-xs font-bold tabular-nums text-center leading-none"
            style={{ color: resuelto ? tipo.color : 'var(--color-texto)' }}
          >
            {partido.mi_pronostico ?? '—'}
          </span>
          <span className="font-body text-[10px] font-semibold truncate" style={{ color: tipo.color }}>
            {tipo.icono} {tipo.texto}
          </span>
        </span>

        <span className="text-right font-marcador text-sm font-bold tabular-nums whitespace-nowrap">
          {resuelto && puntosCalculados && (
            <span style={{ color: partido.puntos > 0 ? tipo.color : 'var(--color-borde)' }}>
              {partido.puntos > 0 ? `+${partido.puntos}` : '0'}
              <span className="hidden sm:inline font-body text-[10px] font-normal">pt</span>
            </span>
          )}
          {resuelto && !puntosCalculados && (
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

      {mostrarOtros && (
        <OtrosPronosticos
          jornada={jornada}
          idPartido={partido.id_partido}
          golesCasa={partido.goles_casa}
          golesFuera={partido.goles_fuera}
          estadoPartido={partido.estado_partido}
        />
      )}
    </div>
  )
}

function SeccionGoleadores({ goleadores }) {
  if (!goleadores || goleadores.length === 0) return null

  return (
    <div className="px-4 py-3 bg-premio/5">
      <p className="font-body text-[10px] uppercase tracking-widest text-premio mb-2">⚽ Tus goleadores elegidos</p>
      <div className="flex flex-wrap gap-2">
        {goleadores.map((g) => (
          <div key={g.id} className="flex items-center gap-2 bg-fondo border border-borde/20 rounded-full pl-1 pr-3 py-1">
            <FotoJugador url={g.foto_url} nombre={g.nombre} />
            <span className="font-body text-xs text-texto">{g.nombre}</span>
            {g.goles > 0 ? (
              <span className="font-marcador text-xs font-bold text-premio">+{g.puntos}</span>
            ) : (
              <span className="font-body text-[10px] text-borde">—</span>
            )}
          </div>
        ))}
      </div>
    </div>
  )
}

// Misma tarjeta por jornada que ya usa UserPointsDetailPage — consistente con
// el resto de la app, en vez de un patrón propio de esta pantalla.
function BloqueJornada({ bloque }) {
  const [abierto, setAbierto] = useState(bloque.bloqueada === false || bloque.partidos.some((p) => p.estado_partido !== 'Jugado'))

  // "Sin cerrar" se dice UNA vez aquí arriba, no en cada partido.
  const sinCerrar = bloque.partidos.some(
    (p) => estaResuelto(p.goles_casa, p.goles_fuera, p.estado_partido) && (p.puntos === null || p.puntos === undefined)
  )

  return (
    <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden mb-4">
      <button
        onClick={() => setAbierto(!abierto)}
        aria-expanded={abierto}
        className="w-full flex items-center justify-between gap-3 px-4 py-3 bg-borde/10 hover:bg-borde/15 transition"
      >
        <span className="flex items-center flex-wrap gap-x-3 gap-y-1 min-w-0">
          <span className="font-display text-base text-texto">Jornada {bloque.jornada}</span>
          {!bloque.bloqueada && (
            <span className="font-body text-[10px] font-semibold text-premio bg-premio/10 rounded-full px-2 py-0.5">Abierta</span>
          )}
          {sinCerrar && (
            <span
              title="Hay partidos jugados cuyos puntos aún no se han calculado"
              className="font-body text-[10px] font-semibold text-borde border border-borde/30 rounded-full px-2 py-0.5"
            >
              ⏳ Puntos sin cerrar
            </span>
          )}
          <ResumenJornada partidos={bloque.partidos} />
        </span>
        <span className="flex items-center gap-3 shrink-0">
          <span className="font-marcador text-sm font-bold text-acento">{bloque.puntos_totales_jornada}pt</span>
          <span className="text-borde text-xs">{abierto ? '▲' : '▼'}</span>
        </span>
      </button>

      {abierto && (
        <div>
          <div className="flex items-baseline justify-between gap-3 px-4 pt-3 pb-1">
            <p className="font-body text-[10px] uppercase tracking-widest text-borde">Partidos</p>
            {bloque.bloqueada && (
              <p className="font-body text-[10px] text-borde text-right">Pulsa un partido para ver los pronósticos del grupo</p>
            )}
          </div>
          <div>
            {bloque.partidos.map((partido) => (
              <FilaPartido
                key={partido.id_partido}
                partido={partido}
                jornada={bloque.jornada}
                jornadaBloqueada={bloque.bloqueada}
              />
            ))}
          </div>

          {(bloque.bonus_pleno > 0 || bloque.bonus_cartas > 0) && (
            <div className="border-t border-borde/10">
              <p className="font-body text-[10px] uppercase tracking-widest text-borde px-4 pt-3 pb-1">Bonus</p>
              {bloque.bonus_pleno > 0 && (
                <div className="px-4 py-2 flex items-center justify-between">
                  <div>
                    <p className="font-body text-xs text-acento font-semibold">🎯 Bonus por buena jornada</p>
                    {bloque.bonus_pleno_con_amuleto && (
                      <p className="font-body text-[11px] text-borde mt-0.5">🍀 En parte, gracias a tu Amuleto</p>
                    )}
                  </div>
                  <span className="font-marcador text-xs font-bold text-acento">+{bloque.bonus_pleno}pt</span>
                </div>
              )}
              {bloque.bonus_cartas > 0 && (
                <div className="px-4 py-2 flex items-center justify-between">
                  <p className="font-body text-xs text-premio font-semibold">🃏 Bonus de cartas</p>
                  <span className="font-marcador text-xs font-bold text-premio">+{bloque.bonus_cartas}pt</span>
                </div>
              )}
            </div>
          )}

          {bloque.goleadores?.length > 0 && (
            <div className="border-t border-borde/10">
              <SeccionGoleadores goleadores={bloque.goleadores} />
            </div>
          )}
        </div>
      )}
    </div>
  )
}

function MyPredictionsPage() {
  const { data, isLoading, error } = useQuery({
    queryKey: ['mis-pronosticos'],
    queryFn: async () => {
      const respuesta = await client.get('/api/v1/pronosticos')
      return respuesta.data.data
    },
  })

  if (isLoading) return <div className="max-w-2xl mx-auto px-4 py-8"><SkeletonLista /></div>
  if (error) return <p className="font-body text-red-500 p-4">{error.response?.data?.message ?? 'Error al cargar.'}</p>

  const jornadas = [...data.jornadas].reverse()

  return (
    <div className="max-w-2xl mx-auto px-4 py-8">
      <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden mb-6">
        <TicketHeader titulo="Mis Pronósticos" />
        <div className="px-6 py-6 flex items-center justify-between">
          {[
            { label: 'TOTAL', valor: data.stats.total, color: 'var(--color-texto)' },
            { label: 'PUNTOS', valor: data.stats.puntos_totales, color: 'var(--color-acento)' },
            { label: 'ACIERTOS', valor: data.stats.aciertos, color: 'var(--color-texto)' },
            { label: 'EXACTOS', valor: data.stats.exactos, color: 'var(--color-premio)' },
          ].map((item, i) => (
            <div key={item.label} className={`text-center flex-1 ${i > 0 ? 'border-l border-dotted border-borde/30' : ''}`}>
              <p
                className="font-marcador text-2xl tabular-nums"
                style={{ color: item.color, textShadow: `0 0 14px ${item.color}70` }}
              >
                {item.valor}
              </p>
              <p className="font-body text-[9px] tracking-widest text-borde mt-1">{item.label}</p>
            </div>
          ))}
        </div>
      </div>

      {jornadas.length > 0 && <LeyendaColores />}

      {jornadas.length === 0 ? (
        <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden">
          <EstadoVacio icono="⚽" titulo="Nada por aquí" texto="Aún no has hecho ningún pronóstico." />
        </div>
      ) : (
        jornadas.map((bloque) => <BloqueJornada key={bloque.jornada} bloque={bloque} />)
      )}
    </div>
  )
}

export default MyPredictionsPage