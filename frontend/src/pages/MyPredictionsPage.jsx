import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import client from '../api/client'
import EstadoVacio from '../components/EstadoVacio'
import SkeletonLista from '../components/SkeletonLista'
import TicketHeader from '../components/TicketHeader'
import FilaPronostico, { LeyendaAciertos, ResumenJornada } from '../components/FilaPronostico'
import { TIPOS, fondoSuave, estaResuelto, tipoDePronostico } from '../utils/tiposAcierto'

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

// La fila en sí es FilaPronostico (compartida con el detalle de puntos de un
// usuario). Aquí solo se le añade lo propio de esta pantalla: si la jornada
// está cerrada, se pulsa para ver qué pronosticó el resto del grupo.
function FilaPartido({ partido, jornada, jornadaBloqueada }) {
  const [mostrarOtros, setMostrarOtros] = useState(false)

  return (
    <FilaPronostico
      partido={partido}
      etiqueta="tu pronóstico"
      onPulsar={jornadaBloqueada ? () => setMostrarOtros(!mostrarOtros) : undefined}
      abierto={mostrarOtros}
      tituloPulsar={mostrarOtros ? 'Ocultar pronósticos del grupo' : 'Ver pronósticos del grupo'}
    >
      {mostrarOtros && (
        <OtrosPronosticos
          jornada={jornada}
          idPartido={partido.id_partido}
          golesCasa={partido.goles_casa}
          golesFuera={partido.goles_fuera}
          estadoPartido={partido.estado_partido}
        />
      )}
    </FilaPronostico>
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

      {jornadas.length > 0 && <LeyendaAciertos />}

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
