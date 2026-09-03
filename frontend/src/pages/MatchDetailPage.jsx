import { useParams, Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import client from '../api/client'

function Escudo({ url, alt }) {
  return (
    <div className="relative">
      <div className="absolute inset-0 rounded-full bg-borde/5 blur-md" />
      {url ? (
        <img src={url} alt={alt} className="relative w-20 h-20 object-contain shrink-0" />
      ) : (
        <span className="relative w-20 h-20 rounded-full bg-borde/15 flex items-center justify-center text-3xl shrink-0">⚽</span>
      )}
    </div>
  )
}

function FotoJugador({ url, nombre }) {
  if (url) return <img src={url} alt={nombre} className="w-9 h-9 rounded-full object-cover shrink-0 ring-2 ring-borde/20" />
  return (
    <span className="w-9 h-9 rounded-full bg-acento/10 border border-acento/20 flex items-center justify-center text-xs font-semibold shrink-0 text-acento">
      {nombre?.[0]}
    </span>
  )
}

function obtenerIdYoutube(url) {
  if (!url) return null
  const patron = /(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/
  const coincide = url.match(patron)
  return coincide ? coincide[1] : null
}

const COLOR_SECCION = {
  gol: '#C8FF4D',
  tarjeta_amarilla: '#FACC15',
  tarjeta_roja: '#EF4444',
  sustitucion: '#96ACC2',
}

function Fila({ evento, mostrarAsistencia }) {
  if (!evento) return null

  return (
    <div className="flex items-center gap-2.5 py-1.5">
      <span className="font-marcador text-xs text-borde w-7 shrink-0 text-right">{evento.minuto}'</span>
      <FotoJugador url={evento.jugador_foto} nombre={evento.jugador} />
      <div className="min-w-0">
        <p className="font-body text-sm text-texto truncate">{evento.jugador}</p>
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

function SeccionCompartida({ tipo, titulo, icono, eventosLocal, eventosVisitante, mostrarAsistencia = false }) {
  if (eventosLocal.length === 0 && eventosVisitante.length === 0) return null

  const filas = Math.max(eventosLocal.length, eventosVisitante.length)
  const color = COLOR_SECCION[tipo]

  return (
    <div className="pb-4 mb-4 border-b border-borde/10 last:border-0 last:mb-0 last:pb-0">
      <p className="font-body text-[10px] uppercase tracking-widest mb-2 text-center font-semibold" style={{ color }}>
        {icono} {titulo}
      </p>
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

  const minutosDesdeActualizacion = data.actualizado_en
    ? Math.max(0, Math.round((Date.now() - new Date(data.actualizado_en)) / 60000))
    : null

  const idVideo = obtenerIdYoutube(data.video_resumen_url)

  return (
    <div className="max-w-2xl mx-auto px-4 py-8">
      <Link
        to={`/jornadas/${data.jornada}`}
        className="inline-flex items-center gap-1.5 font-body text-sm text-texto border border-borde/30 rounded-full px-3 py-1.5 mb-4 hover:bg-borde/10 hover:border-borde/50 transition"
      >
        ← Volver a la jornada
      </Link>

      <div className="bg-fondo border border-borde/30 rounded-lg p-6 mb-2">
        <div className="flex items-center justify-between">
          <div className="flex flex-col items-center gap-3 flex-1">
            <Escudo url={data.equipo_local.escudo_url} alt={data.equipo_local.nombre} />
            <span className="font-body font-medium text-texto text-center">{data.equipo_local.nombre}</span>
          </div>
          <div className="text-center px-4">
            <div className="rounded-xl border border-borde/20 bg-borde/5 px-5 py-2">
              {(data.estado === 'Jugado' || data.estado === 'En juego') ? (
                <span className="font-marcador text-5xl font-bold text-texto tabular-nums">{data.goles_casa ?? 0}-{data.goles_fuera ?? 0}</span>
              ) : (
                <span className="font-body text-sm text-borde">{data.estado}</span>
              )}
            </div>
            {data.estado === 'En juego' && <p className="font-body text-xs text-red-400 font-semibold mt-1.5">● En juego</p>}
          </div>
          <div className="flex flex-col items-center gap-3 flex-1">
            <Escudo url={data.equipo_visitante.escudo_url} alt={data.equipo_visitante.nombre} />
            <span className="font-body font-medium text-texto text-center">{data.equipo_visitante.nombre}</span>
          </div>
        </div>
        {(data.estadio || data.arbitro) && (
          <div className="flex gap-4 justify-center mt-4 pt-4 border-t border-borde/10">
            {data.estadio && (
              <span className="font-body text-xs text-borde">
                🏟️ {data.estadio}{data.ciudad && ` · ${data.ciudad}`}
              </span>
            )}
            {data.arbitro && <span className="font-body text-xs text-borde">🧑‍⚖️ {data.arbitro}</span>}
          </div>
        )}
      </div>

      {minutosDesdeActualizacion !== null && (
        <p className="font-body text-[11px] text-borde text-center mb-4">
          Actualizado hace {minutosDesdeActualizacion} min
        </p>
      )}

      <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden mb-4">
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
              tipo="gol" titulo="Goles" icono="⚽"
              eventosLocal={porTipo('gol', eventosLocal)}
              eventosVisitante={porTipo('gol', eventosVisitante)}
              mostrarAsistencia
            />
            <SeccionCompartida
              tipo="tarjeta_amarilla" titulo="Tarjetas amarillas" icono="🟨"
              eventosLocal={porTipo('tarjeta_amarilla', eventosLocal)}
              eventosVisitante={porTipo('tarjeta_amarilla', eventosVisitante)}
            />
            <SeccionCompartida
              tipo="tarjeta_roja" titulo="Tarjetas rojas" icono="🟥"
              eventosLocal={porTipo('tarjeta_roja', eventosLocal)}
              eventosVisitante={porTipo('tarjeta_roja', eventosVisitante)}
            />
            <SeccionCompartida
              tipo="sustitucion" titulo="Sustituciones" icono="🔄"
              eventosLocal={porTipo('sustitucion', eventosLocal)}
              eventosVisitante={porTipo('sustitucion', eventosVisitante)}
            />
          </div>
        )}
      </div>

      {idVideo ? (
        <a
          href={data.video_resumen_url}
          target="_blank"
          rel="noopener noreferrer"
          className="block bg-fondo border border-borde/30 rounded-lg overflow-hidden hover:border-acento/40 transition group"
        >
          <div className="px-4 py-3 bg-borde/10">
            <p className="font-body text-xs uppercase tracking-widest text-borde">🎬 Vídeo resumen</p>
          </div>
          <div className="relative w-full" style={{ paddingBottom: '56.25%' }}>
            <img
              src={`https://img.youtube.com/vi/${idVideo}/hqdefault.jpg`}
              alt="Miniatura del vídeo resumen"
              className="absolute inset-0 w-full h-full object-cover"
            />
            <div className="absolute inset-0 bg-black/30 group-hover:bg-black/20 transition flex items-center justify-center">
              <div className="w-16 h-16 rounded-full bg-red-600 flex items-center justify-center shadow-lg group-hover:scale-110 transition">
                <div className="w-0 h-0 border-t-[10px] border-t-transparent border-b-[10px] border-b-transparent border-l-[16px] border-l-white ml-1" />
              </div>
            </div>
          </div>
          <p className="text-center font-body text-xs text-acento py-2 border-t border-borde/10">
            Ver en YouTube →
          </p>
        </a>
      ) : (
        <p className="font-body text-xs text-borde text-center">🎬 Vídeo resumen — disponible más adelante</p>
      )}
    </div>
  )
}

export default MatchDetailPage