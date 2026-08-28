import { Link, useNavigate } from 'react-router-dom'
import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import client from '../api/client'

function Escudo({ url, alt }) {
  if (!url) return <span className="w-9 h-9 rounded-full bg-borde/15 flex items-center justify-center text-sm shrink-0">⚽</span>
  return <img src={url} alt={alt} className="w-9 h-9 object-contain shrink-0" />
}

const BADGE_ESTADO = {
  Jugado: 'bg-acento/15 text-acento',
  'En juego': 'bg-red-400/15 text-red-400 animate-pulse',
  Aplazado: 'bg-red-400/15 text-red-400',
  Programado: 'bg-premio/15 text-premio',
}

function EquipoEnlace({ equipo, alinear }) {
  return (
    <Link
      to={`/equipos/${equipo.id}`}
      onClick={(e) => e.stopPropagation()}
      className={`flex items-center gap-2 flex-1 min-w-0 hover:opacity-70 transition ${alinear === 'derecha' ? 'justify-end text-right' : ''}`}
    >
      {alinear === 'derecha' && <span className="font-body font-medium text-texto text-base truncate">{equipo.nombre}</span>}
      <Escudo url={equipo.escudo_url} alt={equipo.nombre} />
      {alinear !== 'derecha' && <span className="font-body font-medium text-texto text-base truncate">{equipo.nombre}</span>}
    </Link>
  )
}

function MatchCard({ partido }) {
  const navigate = useNavigate()
  const [golesLocal, setGolesLocal] = useState(partido.mi_pronostico?.goles_local_predicho ?? '')
  const [golesVisitante, setGolesVisitante] = useState(partido.mi_pronostico?.goles_visitante_predicho ?? '')
  const [editando, setEditando] = useState(!partido.mi_pronostico)
  const queryClient = useQueryClient()

  const mutacion = useMutation({
    mutationFn: async () => {
      const respuesta = await client.post('/api/v1/pronosticos', {
        id_partido: partido.id,
        goles_local_predicho: Number(golesLocal),
        goles_visitante_predicho: Number(golesVisitante),
      })
      return respuesta.data.data
    },
    onSuccess: () => {
      setEditando(false)
      queryClient.invalidateQueries({ queryKey: ['partidos', String(partido.jornada)] })
    },
  })

  function enviar() {
    if (golesLocal === '' || golesVisitante === '') {
      alert('Rellena primero el marcador que crees que habrá.')
      return
    }
    mutacion.mutate()
  }

  const hora = partido.horario_estimado?.slice(11, 16)

  return (
    <div
      onClick={() => navigate(`/partidos/${partido.id}`)}
      className="bg-fondo border border-borde/30 rounded-lg p-4 cursor-pointer hover:border-acento/40 transition"
    >
      <div className="flex items-center justify-between mb-3">
        <span className={`font-body text-xs font-bold uppercase tracking-wider px-2.5 py-1 rounded-full ${BADGE_ESTADO[partido.estado] ?? 'bg-borde/15 text-borde'}`}>
          {partido.estado}
        </span>
        {hora && <span className="font-marcador text-sm text-texto tabular-nums">{hora}</span>}
      </div>

      <div className="flex items-center justify-between gap-2 mb-1">
        <EquipoEnlace equipo={partido.equipo_local} alinear="derecha" />
        {(partido.estado === 'Jugado' || partido.estado === 'En juego') ? (
          <span className="font-marcador text-2xl font-bold text-texto tabular-nums px-3 shrink-0">
            {partido.goles_casa}-{partido.goles_fuera}
          </span>
        ) : (
          <span className="font-body text-xs text-borde px-3 shrink-0">vs</span>
        )}
        <EquipoEnlace equipo={partido.equipo_visitante} />
      </div>

      {partido.estado === 'Jugado' && partido.mi_pronostico && (
        <p className="text-center font-body text-sm text-borde mt-1">
          Tu pronóstico: {partido.mi_pronostico.goles_local_predicho}-{partido.mi_pronostico.goles_visitante_predicho}
        </p>
      )}

      {partido.estado === 'Programado' && (
        <div onClick={(e) => e.stopPropagation()} className="mt-3 pt-3 border-t border-borde/40 cursor-default">
          {!editando && partido.mi_pronostico ? (
            <div className="flex items-center justify-between bg-acento/10 rounded px-3 py-2">
              <div className="flex items-center gap-2">
                <span className="text-acento text-base">✓</span>
                <span className="font-body text-sm text-acento font-semibold">
                  Tu pronóstico: {partido.mi_pronostico.goles_local_predicho}-{partido.mi_pronostico.goles_visitante_predicho}
                </span>
              </div>
              <button onClick={() => setEditando(true)} className="font-body text-xs text-borde hover:text-texto underline">
                Editar
              </button>
            </div>
          ) : (
            <>
              <div className="flex gap-3 justify-center">
                <input type="number" min="0" placeholder="0" value={golesLocal} onChange={(e) => setGolesLocal(e.target.value)}
                  className="font-marcador text-xl w-20 h-14 text-center bg-borde/20 text-texto rounded border border-borde/40" />
                <span className="font-marcador text-borde self-center text-xl">-</span>
                <input type="number" min="0" placeholder="0" value={golesVisitante} onChange={(e) => setGolesVisitante(e.target.value)}
                  className="font-marcador text-xl w-20 h-14 text-center bg-borde/20 text-texto rounded border border-borde/40" />
              </div>
              <button
                onClick={enviar}
                disabled={mutacion.isPending}
                className="mx-auto mt-3 block bg-acento text-fondo font-body font-semibold text-xs rounded px-4 py-1.5 hover:brightness-110 disabled:opacity-50"
              >
                {mutacion.isPending ? 'Guardando...' : 'Guardar pronóstico'}
              </button>
              {mutacion.isError && <p className="font-body text-xs text-red-500 mt-2 text-center">{mutacion.error.response?.data?.message ?? 'Algo falló'}</p>}
            </>
          )}
        </div>
      )}

      {(partido.estadio || partido.arbitro) && (
        <div className="flex gap-3 mt-3 pt-3 border-t border-borde/10">
          {partido.estadio && (
            <span className="font-body text-xs text-borde truncate flex-1 min-w-0">🏟️ {partido.estadio}</span>
          )}
          {partido.arbitro && (
            <span className="font-body text-xs text-borde truncate flex-1 min-w-0 text-right">🧑‍⚖️ {partido.arbitro}</span>
          )}
        </div>
      )}
    </div>
  )
}

export default MatchCard