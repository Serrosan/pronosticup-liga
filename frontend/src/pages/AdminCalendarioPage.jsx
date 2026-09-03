import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import client from '../api/client'

const TOTAL_JORNADAS = 38

function Escudo({ url, alt }) {
  if (!url) return <span className="w-5 h-5 rounded-full bg-borde/15 flex items-center justify-center text-xs shrink-0">⚽</span>
  return <img src={url} alt={alt} className="w-5 h-5 object-contain shrink-0" />
}

function FilaPartido({ partido }) {
  const [horario, setHorario] = useState(partido.horario_estimado ?? '')
  const [estado, setEstado] = useState(partido.estado)
  const [golesCasa, setGolesCasa] = useState(partido.goles_casa ?? '')
  const [golesFuera, setGolesFuera] = useState(partido.goles_fuera ?? '')
  const [videoUrl, setVideoUrl] = useState(partido.video_resumen_url ?? '')
  const queryClient = useQueryClient()

  const guardar = useMutation({
    mutationFn: () => client.put(`/api/v1/admin/calendario/${partido.id}`, {
      horario_estimado: horario,
      estado,
      goles_casa: golesCasa === '' ? null : golesCasa,
      goles_fuera: golesFuera === '' ? null : golesFuera,
      video_resumen_url: videoUrl === '' ? null : videoUrl,
    }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', 'calendario'] }),
  })

  return (
    <div className="flex flex-col gap-2 px-3 py-2.5 border-b border-borde/10 last:border-0">
      <div className="flex items-center gap-2 flex-wrap">
        <div className="flex items-center gap-1.5 min-w-0 flex-1 basis-52">
          <Escudo url={partido.escudo_local} alt={partido.equipo_local} />
          <p className="font-body text-xs text-texto truncate">{partido.equipo_local} vs {partido.equipo_visitante}</p>
          <Escudo url={partido.escudo_visitante} alt={partido.equipo_visitante} />
        </div>

        <input
          type="datetime-local"
          value={horario}
          onChange={(e) => setHorario(e.target.value)}
          className="font-body text-xs bg-borde/10 text-texto rounded border border-borde/40 px-2 py-1"
        />

        <select
          value={estado}
          onChange={(e) => setEstado(e.target.value)}
          className="font-body text-xs bg-borde/10 text-texto rounded border border-borde/40 px-2 py-1"
        >
          <option value="Programado">Programado</option>
          <option value="Jugado">Jugado</option>
          <option value="Aplazado">Aplazado</option>
        </select>

        <input
          type="number"
          min="0"
          placeholder="G.local"
          value={golesCasa}
          onChange={(e) => setGolesCasa(e.target.value)}
          className="font-marcador text-xs bg-borde/10 text-texto rounded border border-borde/40 px-2 py-1 w-16"
        />
        <input
          type="number"
          min="0"
          placeholder="G.visit"
          value={golesFuera}
          onChange={(e) => setGolesFuera(e.target.value)}
          className="font-marcador text-xs bg-borde/10 text-texto rounded border border-borde/40 px-2 py-1 w-16"
        />

        <button
          onClick={() => guardar.mutate()}
          disabled={guardar.isPending}
          className="font-body text-xs font-semibold bg-acento text-fondo rounded px-3 py-1.5 hover:brightness-110 disabled:opacity-50"
        >
          {guardar.isPending ? '...' : 'Guardar'}
        </button>
        {guardar.isSuccess && <span className="font-body text-xs text-acento">✓</span>}
      </div>

      {estado === 'Jugado' && (
        <div className="flex items-center gap-2 pl-1">
          <span className="font-body text-xs text-borde shrink-0">🎬 Vídeo resumen (YouTube):</span>
          <input
            type="url"
            placeholder="https://youtube.com/watch?v=..."
            value={videoUrl}
            onChange={(e) => setVideoUrl(e.target.value)}
            className="font-body text-xs bg-borde/10 text-texto rounded border border-borde/40 px-2 py-1 flex-1 min-w-[200px]"
          />
        </div>
      )}
    </div>
  )
}

function AdminCalendarioPage() {
  const [jornada, setJornada] = useState(1)
  const [mensajeCierre, setMensajeCierre] = useState(null)
  const queryClient = useQueryClient()

  const { data: partidos, isLoading, error } = useQuery({
    queryKey: ['admin', 'calendario', jornada],
    queryFn: async () => {
      const respuesta = await client.get(`/api/v1/admin/calendario?jornada=${jornada}`)
      return respuesta.data.data
    },
  })

  const cerrarJornada = useMutation({
    mutationFn: () => client.post(`/api/v1/jornadas/${jornada}/cerrar`),
    onSuccess: (respuesta) => {
      setMensajeCierre({ tipo: 'exito', texto: respuesta.data.message + ` (${respuesta.data.eventos_creados} pronósticos evaluados)` })
      queryClient.invalidateQueries({ queryKey: ['admin', 'calendario', jornada] })
    },
    onError: (err) => {
      setMensajeCierre({ tipo: 'error', texto: err.response?.data?.message ?? 'Error al cerrar la jornada.' })
    },
  })

  return (
    <div>
      <div className="flex items-center gap-3 mb-4 flex-wrap">
        <h2 className="font-display text-xl text-texto">Calendario</h2>
        <select
          value={jornada}
          onChange={(e) => setJornada(Number(e.target.value))}
          className="font-body text-sm bg-borde/10 text-texto rounded border border-borde/40 px-2 py-1"
        >
          {Array.from({ length: TOTAL_JORNADAS }, (_, i) => i + 1).map((j) => (
            <option key={j} value={j}>Jornada {j}</option>
          ))}
        </select>
        <button
          onClick={() => { setMensajeCierre(null); cerrarJornada.mutate() }}
          disabled={cerrarJornada.isPending}
          className="font-body text-sm font-semibold bg-premio text-fondo rounded px-4 py-1.5 hover:brightness-110 disabled:opacity-50"
        >
          {cerrarJornada.isPending ? 'Cerrando...' : '🔒 Cerrar jornada'}
        </button>
      </div>

      {mensajeCierre && (
        <p className={`font-body text-sm mb-4 px-3 py-2 rounded ${
          mensajeCierre.tipo === 'exito' ? 'bg-acento/10 text-acento' : 'bg-red-500/10 text-red-500'
        }`}>
          {mensajeCierre.texto}
        </p>
      )}

      {isLoading && <p className="font-body text-texto p-4">Cargando...</p>}
      {error && <p className="font-body text-red-500 p-4">Error al cargar.</p>}

      {partidos && (
        <div className="bg-fondo border border-borde/30 rounded-lg">
          {partidos.map((p) => <FilaPartido key={p.id} partido={p} />)}
        </div>
      )}
    </div>
  )
}

export default AdminCalendarioPage