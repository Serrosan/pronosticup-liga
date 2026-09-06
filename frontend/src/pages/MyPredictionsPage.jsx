import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import client from '../api/client'
import EstadoVacio from '../components/EstadoVacio'
import SkeletonLista from '../components/SkeletonLista'

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

const ESTILO_TIPO = {
  AciertoExacto: { color: 'var(--color-premio)', fondo: 'bg-premio/10', borde: 'border-premio/40' },
  AciertoDiferencia: { color: 'var(--color-acento)', fondo: 'bg-acento/10', borde: 'border-acento/40' },
  Acierto1x2: { color: 'var(--color-acento)', fondo: 'bg-acento/5', borde: 'border-acento/25' },
  Fallo: { color: '#EF4444', fondo: 'bg-red-500/5', borde: 'border-red-500/25' },
}

function ResultadoComparado({ prediccion, golesCasa, golesFuera, tipoEvento, puntos, estadoPartido }) {
  const resuelto = estadoPartido === 'Jugado'
  const estilo = resuelto ? (ESTILO_TIPO[tipoEvento] ?? ESTILO_TIPO.Fallo) : null

  if (!resuelto) {
    return (
      <div className="flex flex-col items-center w-20 shrink-0">
        <p className="font-marcador text-sm text-texto">{prediccion}</p>
        <p className="font-body text-[9px] text-borde mt-0.5">pendiente</p>
      </div>
    )
  }

  return (
    <div className={`flex flex-col items-center w-20 shrink-0 rounded-lg border px-2 py-1 ${estilo.fondo} ${estilo.borde}`}>
      <p className="font-marcador text-sm font-bold" style={{ color: estilo.color }}>{prediccion}</p>
      <p className="font-body text-[9px] text-borde">real: {golesCasa}-{golesFuera}</p>
      <p className="font-marcador text-[10px] font-bold" style={{ color: estilo.color }}>+{puntos}pt</p>
    </div>
  )
}

function OtrosPronosticos({ jornada, idPartido }) {
  const { data, isLoading } = useQuery({
    queryKey: ['otros-pronosticos', jornada],
    queryFn: async () => (await client.get(`/api/v1/jornadas/${jornada}/otros-pronosticos`)).data.data,
  })

  if (isLoading) return <p className="font-body text-[11px] text-borde px-4 py-2">Cargando...</p>

  const otros = data?.[idPartido] ?? []

  if (otros.length === 0) {
    return <p className="font-body text-[11px] text-borde px-4 py-2">Nadie más pronosticó este partido.</p>
  }

  return (
    <div className="flex flex-col gap-1.5 px-4 py-2 bg-borde/5">
      {otros.map((o, i) => (
        <div key={i} className="flex items-center gap-2">
          <AvatarPequeno url={o.avatar_url} nombre={o.usuario} />
          <p className="font-body text-xs text-texto flex-1 truncate">{o.usuario}</p>
          <span className="font-marcador text-xs text-borde">{o.pronostico}</span>
        </div>
      ))}
    </div>
  )
}

function FilaPartido({ partido, jornada, jornadaBloqueada }) {
  const [mostrarOtros, setMostrarOtros] = useState(false)

  return (
    <div className="border-b border-borde/10 last:border-0 odd:bg-borde/5">
      <div className="flex items-center gap-3 px-4 py-3">
        <div className="flex items-center gap-1.5 min-w-0 flex-1">
          <Escudo url={partido.escudo_local} alt={partido.equipo_local} />
          <p className="font-body text-sm text-texto truncate">{partido.equipo_local}</p>
          <span className="text-borde text-xs shrink-0">–</span>
          <p className="font-body text-sm text-texto truncate">{partido.equipo_visitante}</p>
          <Escudo url={partido.escudo_visitante} alt={partido.equipo_visitante} />
        </div>

        <ResultadoComparado
          prediccion={partido.mi_pronostico}
          golesCasa={partido.goles_casa}
          golesFuera={partido.goles_fuera}
          tipoEvento={partido.tipo_evento}
          puntos={partido.puntos}
          estadoPartido={partido.estado_partido}
        />
      </div>

      {jornadaBloqueada && (
        <div className="px-4 pb-2 -mt-1">
          <button
            onClick={() => setMostrarOtros(!mostrarOtros)}
            className="font-body text-[11px] text-acento hover:underline"
          >
            {mostrarOtros ? 'Ocultar' : 'Ver'} qué pronosticaron los demás
          </button>
        </div>
      )}

      {mostrarOtros && <OtrosPronosticos jornada={jornada} idPartido={partido.id_partido} />}
    </div>
  )
}

function SeccionGoleadores({ goleadores }) {
  if (!goleadores || goleadores.length === 0) return null

  return (
    <div className="px-4 py-3 bg-premio/5 border-t border-borde/10">
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

function BloqueJornada({ bloque }) {
  const [abierto, setAbierto] = useState(bloque.bloqueada === false || bloque.partidos.some((p) => p.estado_partido !== 'Jugado'))

  return (
    <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden mb-4">
      <button
        onClick={() => setAbierto(!abierto)}
        className="w-full flex items-center justify-between px-4 py-3 bg-borde/10 hover:bg-borde/15 transition"
      >
        <div className="flex items-center gap-2">
          <span className="font-display text-base text-texto">Jornada {bloque.jornada}</span>
          {!bloque.bloqueada && (
            <span className="font-body text-[10px] font-semibold text-premio bg-premio/10 rounded-full px-2 py-0.5">Abierta</span>
          )}
        </div>
        <div className="flex items-center gap-3">
          <span className="font-marcador text-sm font-bold text-acento">{bloque.puntos_totales_jornada}pt</span>
          <span className="text-borde text-xs">{abierto ? '▲' : '▼'}</span>
        </div>
      </button>

      {abierto && (
        <div>
          {bloque.partidos.map((partido) => (
            <FilaPartido
              key={partido.id_partido}
              partido={partido}
              jornada={bloque.jornada}
              jornadaBloqueada={bloque.bloqueada}
            />
          ))}
          {bloque.bonus_pleno > 0 && (
            <div className="px-4 py-2 bg-acento/5 border-t border-borde/10 flex items-center justify-between">
              <p className="font-body text-xs text-acento font-semibold">🎯 Bonus por buena jornada</p>
              <span className="font-marcador text-xs font-bold text-acento">+{bloque.bonus_pleno}pt</span>
            </div>
          )}
          <SeccionGoleadores goleadores={bloque.goleadores} />
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

  return (
    <div className="max-w-2xl mx-auto px-4 py-8">
      <div className="bg-borde/10 border border-borde/30 rounded-t-2xl px-6 py-4 text-center">
        <p className="font-body text-[10px] uppercase tracking-[0.2em] text-borde">Boletín de pronósticos</p>
        <h1 className="font-display text-xl text-texto mt-1">Mis Pronósticos</h1>
      </div>

      <div className="relative border-t-2 border-dashed border-borde/30">
        <div className="absolute -left-3 -top-3 w-6 h-6 rounded-full bg-fondo border border-borde/30" />
        <div className="absolute -right-3 -top-3 w-6 h-6 rounded-full bg-fondo border border-borde/30" />
      </div>

      <div className="bg-fondo border-x border-b border-borde/30 rounded-b-2xl px-6 py-4 flex items-center justify-between mb-6">
        {[
          { label: 'TOTAL', valor: data.stats.total, color: 'var(--color-texto)' },
          { label: 'PUNTOS', valor: data.stats.puntos_totales, color: 'var(--color-acento)' },
          { label: 'ACIERTOS', valor: data.stats.aciertos, color: 'var(--color-texto)' },
          { label: 'EXACTOS', valor: data.stats.exactos, color: 'var(--color-premio)' },
        ].map((item, i) => (
          <div key={item.label} className={`text-center flex-1 ${i > 0 ? 'border-l border-dotted border-borde/30' : ''}`}>
            <p
              className="font-marcador text-xl tabular-nums"
              style={{ color: item.color, textShadow: `0 0 10px ${item.color}55` }}
            >
              {item.valor}
            </p>
            <p className="font-body text-[9px] tracking-widest text-borde mt-0.5">{item.label}</p>
          </div>
        ))}
      </div>

      {data.jornadas.length === 0 ? (
        <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden">
          <EstadoVacio icono="⚽" titulo="Nada por aquí" texto="Aún no has hecho ningún pronóstico." />
        </div>
      ) : (
        data.jornadas.map((bloque) => <BloqueJornada key={bloque.jornada} bloque={bloque} />)
      )}
    </div>
  )
}

export default MyPredictionsPage
