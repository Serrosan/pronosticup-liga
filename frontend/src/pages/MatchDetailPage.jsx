import { useParams, Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import client from '../api/client'

function Escudo({ url, alt }) {
  if (!url) return <span className="w-20 h-20 rounded-full bg-borde/15 flex items-center justify-center text-3xl shrink-0">⚽</span>
  return <img src={url} alt={alt} className="w-20 h-20 object-contain shrink-0" />
}

function Fila({ evento, mostrarAsistencia }) {
  if (!evento) return null

  return (
    <div className="flex items-baseline justify-center gap-2 py-1 text-sm">
      <span className="font-marcador text-xs text-borde w-8 shrink-0 text-right">{evento.minuto}'</span>
      <div className="min-w-0 text-center">
        <p className="font-body text-texto truncate">{evento.jugador}</p>
        {mostrarAsistencia && evento.jugador_relacionado && (
          <p className="font-body text-[11px] text-borde truncate">Asistencia: {evento.jugador_relacionado}</p>
        )}
        {!mostrarAsistencia && evento.jugador_relacionado && (
          <p className="font-body text-[11px] text-borde truncate">Entra por: {evento.jugador_relacionado}</p>
        )}
      </div>
    </div>
  )
}

function SeccionCompartida({ titulo, icono, eventosLocal, eventosVisitante, mostrarAsistencia = false }) {
  if (eventosLocal.length === 0 && eventosVisitante.length === 0) return null

  const filas = Math.max(eventosLocal.length, eventosVisitante.length)

  return (
    <div className="pb-4 mb-4 border-b border-borde/10 last:border-0 last:mb-0 last:pb-0">
      <p className="font-body text-[10px] uppercase tracking-widest text-borde mb-2 text-center">{icono} {titulo}</p>
      <div className="grid grid-cols-2 divide-x divide-borde/10">
        <div className="px-4">
          {Array.from({ length: filas }, (_, i) => <Fila key={i} evento={eventosLocal[i]} mostrarAsistencia={mostrarAsistencia} />)}
        </div>
        <div className="px-4">
          {Array.from({ length: filas }, (_, i) => <Fila key={i} evento={eventosVisitante[i]} mostrarAsistencia={mostrarAsistencia} />)}
        </div>
      </div>
    </div>
  )
}

function MatchDetailPage() {
  const { id } = useParams()

  const { data, isLoading } = useQuery({
    queryKey: ['partido-detalle', id],
    queryFn: async () => (await client.get(`/api/v1/partidos/${id}`)).data.data,
  })

  if (isLoading || !data) return <p className="font-body text-texto p-4">Cargando...</p>

  const eventosLocal = data.eventos.filter((e) => e.id_equipo === data.equipo_local.id)
  const eventosVisitante = data.eventos.filter((e) => e.id_equipo === data.equipo_visitante.id)

  const porTipo = (tipo, lista) => lista.filter((e) => e.tipo_evento === tipo)

  return (
    <div className="max-w-2xl mx-auto px-4 py-8">
      <Link to={`/jornadas/${data.jornada}`} className="font-body text-sm text-acento hover:underline mb-4 inline-block">← Volver a la jornada</Link>

      <div className="bg-fondo border border-borde/30 rounded-lg p-6 mb-6">
        <div className="flex items-center justify-between">
          <div className="flex flex-col items-center gap-3 flex-1">
            <Escudo url={data.equipo_local.escudo_url} alt={data.equipo_local.nombre} />
            <span className="font-body font-medium text-texto text-center">{data.equipo_local.nombre}</span>
          </div>
          <div className="text-center px-4">
            {data.estado === 'Jugado' ? (
              <span className="font-marcador text-5xl font-bold text-texto tabular-nums">{data.goles_casa}-{data.goles_fuera}</span>
            ) : (
              <span className="font-body text-sm text-borde">{data.estado}</span>
            )}
          </div>
          <div className="flex flex-col items-center gap-3 flex-1">
            <Escudo url={data.equipo_visitante.escudo_url} alt={data.equipo_visitante.nombre} />
            <span className="font-body font-medium text-texto text-center">{data.equipo_visitante.nombre}</span>
          </div>
        </div>
        {(data.estadio || data.arbitro) && (
          <div className="flex gap-4 justify-center mt-4 pt-4 border-t border-borde/10">
            {data.estadio && <span className="font-body text-xs text-borde">🏟️ {data.estadio}</span>}
            {data.arbitro && <span className="font-body text-xs text-borde">🧑‍⚖️ {data.arbitro}</span>}
          </div>
        )}
      </div>

      <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden">
        <div className="px-4 py-3 bg-borde/10">
          <p className="font-body text-xs uppercase tracking-widest text-borde">Eventos del partido</p>
        </div>

        {data.eventos.length === 0 ? (
          <p className="font-body text-sm text-borde py-8 text-center px-4">
            Aún no hay eventos detallados de este partido (goles/tarjetas/sustituciones).
          </p>
        ) : (
          <div className="py-4">
            <SeccionCompartida
              titulo="Goles" icono="⚽"
              eventosLocal={porTipo('gol', eventosLocal)}
              eventosVisitante={porTipo('gol', eventosVisitante)}
              mostrarAsistencia
            />
            <SeccionCompartida
              titulo="Tarjetas amarillas" icono="🟨"
              eventosLocal={porTipo('tarjeta_amarilla', eventosLocal)}
              eventosVisitante={porTipo('tarjeta_amarilla', eventosVisitante)}
            />
            <SeccionCompartida
              titulo="Tarjetas rojas" icono="🟥"
              eventosLocal={porTipo('tarjeta_roja', eventosLocal)}
              eventosVisitante={porTipo('tarjeta_roja', eventosVisitante)}
            />
            <SeccionCompartida
              titulo="Sustituciones" icono="🔄"
              eventosLocal={porTipo('sustitucion', eventosLocal)}
              eventosVisitante={porTipo('sustitucion', eventosVisitante)}
            />
          </div>
        )}
      </div>

      <p className="font-body text-xs text-borde text-center mt-4">🎬 Vídeo resumen — disponible más adelante</p>
    </div>
  )
}

export default MatchDetailPage