import { useState } from 'react'
import { useParams, useLocation, Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import client from '../api/client'

function Escudo({ url, alt }) {
  if (!url) return <span className="w-5 h-5 rounded-full bg-borde/15 flex items-center justify-center text-xs shrink-0">⚽</span>
  return <img src={url} alt={alt} className="w-5 h-5 object-contain shrink-0" />
}

function Avatar({ url, nombre, tamano = 'w-10 h-10' }) {
  if (url) return <img src={url} alt={nombre} className={`${tamano} rounded-full object-cover shrink-0`} />
  return (
    <div className={`${tamano} rounded-full bg-acento/15 flex items-center justify-center shrink-0`}>
      <span className="font-display text-sm text-acento">{nombre?.[0]?.toUpperCase()}</span>
    </div>
  )
}

function FotoJugador({ url, nombre }) {
  if (url) return <img src={url} alt={nombre} className="w-8 h-8 rounded-full object-cover shrink-0" />
  return (
    <span className="w-8 h-8 rounded-full bg-acento/10 border border-acento/20 flex items-center justify-center text-xs font-semibold shrink-0 text-acento">
      {nombre?.[0]}
    </span>
  )
}

const ESTILO_TIPO = {
  AciertoExacto: { color: 'var(--color-premio)', fondo: 'bg-premio/10', borde: 'border-premio/40' },
  AciertoDiferencia: { color: 'var(--color-acento)', fondo: 'bg-acento/10', borde: 'border-acento/40' },
  Acierto1x2: { color: 'var(--color-acento)', fondo: 'bg-acento/5', borde: 'border-acento/25' },
  Fallo: { color: '#EF4444', fondo: 'bg-red-500/5', borde: 'border-red-500/25' },
}

function ResultadoComparado({ prediccion, oculto, golesCasa, golesFuera, tipoEvento, puntos, estadoPartido }) {
  if (oculto) {
    return (
      <div className="flex flex-col items-center w-20 shrink-0">
        <p className="font-marcador text-sm text-borde">🔒</p>
        <p className="font-body text-[9px] text-borde mt-0.5">oculto</p>
      </div>
    )
  }

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

function FilaPartido({ partido }) {
  return (
    <div className="flex items-center gap-3 px-4 py-3 border-b border-borde/10 last:border-0 odd:bg-borde/5">
      <div className="flex items-center gap-1.5 min-w-0 flex-1">
        <Escudo url={partido.escudo_local} alt={partido.equipo_local} />
        <p className="font-body text-xs text-texto truncate">{partido.equipo_local}</p>
        <span className="text-borde text-xs shrink-0">–</span>
        <p className="font-body text-xs text-texto truncate">{partido.equipo_visitante}</p>
        <Escudo url={partido.escudo_visitante} alt={partido.equipo_visitante} />
      </div>

      <ResultadoComparado
        prediccion={partido.mi_pronostico}
        oculto={partido.oculto}
        golesCasa={partido.goles_casa}
        golesFuera={partido.goles_fuera}
        tipoEvento={partido.tipo_evento}
        puntos={partido.puntos}
        estadoPartido={partido.estado_partido}
      />
    </div>
  )
}

function SeccionGoleadores({ goleadores }) {
  if (!goleadores || goleadores.length === 0) return null

  return (
    <div className="px-4 py-3 bg-premio/5 border-t border-borde/10">
      <p className="font-body text-[10px] uppercase tracking-widest text-premio mb-2">⚽ Goleadores elegidos</p>
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
          {bloque.partidos.map((partido) => <FilaPartido key={partido.id_partido} partido={partido} />)}
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

function DesgloseTotal({ desglose }) {
  const items = [
    { label: 'Pronósticos', valor: desglose.pronosticos, icono: '⚽' },
    { label: 'Bonus de pleno', valor: desglose.bonus_pleno, icono: '🎯' },
    { label: 'Goleadores', valor: desglose.goleadores, icono: '🥅' },
  ]

  return (
    <div className="bg-fondo border-x border-borde/30 px-6 py-4">
      <p className="font-body text-[10px] uppercase tracking-widest text-borde mb-3">Desglose de puntos</p>
      <div className="flex flex-col gap-2">
        {items.map((item) => (
          <div key={item.label} className="flex items-center justify-between">
            <span className="font-body text-sm text-texto">{item.icono} {item.label}</span>
            <span className="font-marcador text-sm font-bold text-acento">+{item.valor}pt</span>
          </div>
        ))}
      </div>
    </div>
  )
}

function UserPointsDetailPage() {
  const { idUsuario } = useParams()
  const location = useLocation()
  const infoInicial = location.state

  const { data, isLoading, error } = useQuery({
    queryKey: ['clasificacion-detalle', idUsuario],
    queryFn: async () => (await client.get(`/api/v1/clasificacion/usuarios/${idUsuario}/detalle`)).data.data,
  })

  const nombre = data?.usuario.nombre ?? infoInicial?.nombre
  const avatarUrl = data?.usuario.avatar_url ?? infoInicial?.avatar_url

  if (error) return <p className="font-body text-red-500 p-4">{error.response?.data?.message ?? 'Error al cargar.'}</p>

  return (
    <div className="max-w-2xl mx-auto px-4 py-8">
      <Link to="/clasificacion" className="font-body text-sm text-acento hover:underline mb-4 inline-block">← Volver a la clasificación</Link>

      <div className="bg-borde/10 border border-borde/30 rounded-t-2xl px-6 py-4 text-center">
        <div className="flex items-center justify-center gap-3">
          <Avatar url={avatarUrl} nombre={nombre} />
          <h1 className="font-display text-xl text-texto">{nombre ?? 'Cargando...'}</h1>
        </div>
      </div>

      <div className="relative border-t-2 border-dashed border-borde/30">
        <div className="absolute -left-3 -top-3 w-6 h-6 rounded-full bg-fondo border border-borde/30" />
        <div className="absolute -right-3 -top-3 w-6 h-6 rounded-full bg-fondo border border-borde/30" />
      </div>

      {isLoading ? (
        <p className="font-body text-texto text-center py-8 bg-fondo border-x border-b border-borde/30 rounded-b-2xl">Cargando...</p>
      ) : (
        <>
          <div className="bg-fondo border-x border-borde/30 px-6 py-4 flex items-center justify-between">
            {[
              { label: 'TOTAL', valor: data.stats.total, color: 'var(--color-texto)' },
              { label: 'PUNTOS', valor: data.stats.puntos_totales, color: 'var(--color-acento)' },
              { label: 'ACIERTOS', valor: data.stats.aciertos, color: 'var(--color-texto)' },
              { label: 'EXACTOS', valor: data.stats.exactos, color: 'var(--color-premio)' },
            ].map((item, i) => (
              <div key={item.label} className={`text-center flex-1 ${i > 0 ? 'border-l border-dotted border-borde/30' : ''}`}>
                <p className="font-marcador text-xl tabular-nums" style={{ color: item.color, textShadow: `0 0 10px ${item.color}55` }}>
                  {item.valor}
                </p>
                <p className="font-body text-[9px] tracking-widest text-borde mt-0.5">{item.label}</p>
              </div>
            ))}
          </div>

          <div className="relative border-t border-dotted border-borde/20">
            <div className="absolute -left-3 -top-3 w-6 h-6 rounded-full bg-fondo border border-borde/30" />
            <div className="absolute -right-3 -top-3 w-6 h-6 rounded-full bg-fondo border border-borde/30" />
          </div>

          <DesgloseTotal desglose={data.desglose} />

          <div className="relative border-t-2 border-dashed border-borde/30 mb-6">
            <div className="absolute -left-3 -top-3 w-6 h-6 rounded-full bg-fondo border border-borde/30" />
            <div className="absolute -right-3 -top-3 w-6 h-6 rounded-full bg-fondo border border-borde/30" />
          </div>

          {data.jornadas.map((bloque) => <BloqueJornada key={bloque.jornada} bloque={bloque} />)}
        </>
      )}
    </div>
  )
}

export default UserPointsDetailPage
