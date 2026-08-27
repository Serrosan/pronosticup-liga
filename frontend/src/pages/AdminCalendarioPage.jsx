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
  const queryClient = useQueryClient()

  const guardar = useMutation({
    mutationFn: () => client.put(`/api/v1/admin/calendario/${partido.id}`, {
      horario_estimado: horario,
      estado,
      goles_casa: golesCasa === '' ? null : golesCasa,
      goles_fuera: golesFuera === '' ? null : golesFuera,
    }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', 'calendario'] }),
  })

  return (
    <div className="flex items-center gap-2 px-3 py-2.5 border-b border-borde/10 last:border-0 flex-wrap">
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
  )
}

function AdminCalendarioPage() {
  const [jornada, setJornada] = useState(1)

  const { data: partidos, isLoading, error } = useQuery({
    queryKey: ['admin', 'calendario', jornada],
    queryFn: async () => {
      const respuesta = await client.get(`/api/v1/admin/calendario?jornada=${jornada}`)
      return respuesta.data.data
    },
  })

  return (
    <div>
      <div className="flex items-center gap-3 mb-4">
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
      </div>

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