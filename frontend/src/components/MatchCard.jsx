import { Link, useNavigate } from 'react-router-dom'
import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import client from '../api/client'

const MAX_GOLES = 15

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

function minutoEstimado(horarioEstimado, minutoOficial) {
  if (minutoOficial) return `${minutoOficial}'`
  if (!horarioEstimado) return null

  const transcurridos = Math.floor((Date.now() - new Date(horarioEstimado)) / 60000)
  if (transcurridos < 0) return null
  if (transcurridos <= 45) return `~${transcurridos}'`
  if (transcurridos <= 60) return 'Descanso'
  const segundaParte = transcurridos - 15
  if (segundaParte <= 90) return `~${segundaParte}'`
  return '~90+'
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

function SelectorGoles({ valor, onCambiar }) {
  const numero = valor === '' ? 0 : Number(valor)

  function bajar(e) {
    e.stopPropagation()
    onCambiar(String(Math.max(0, numero - 1)))
  }

  function subir(e) {
    e.stopPropagation()
    onCambiar(String(Math.min(MAX_GOLES, numero + 1)))
  }

  return (
    <div className="flex items-center gap-1.5">
      <button
        type="button"
        onClick={bajar}
        disabled={numero <= 0}
        className="w-9 h-9 rounded-full bg-borde/15 text-texto font-body text-lg font-bold flex items-center justify-center hover:bg-borde/25 active:scale-90 transition disabled:opacity-30 disabled:cursor-not-allowed"
      >
        −
      </button>
      <span className="font-marcador text-2xl w-10 text-center text-texto tabular-nums">{numero}</span>
      <button
        type="button"
        onClick={subir}
        disabled={numero >= MAX_GOLES}
        className="w-9 h-9 rounded-full bg-borde/15 text-texto font-body text-lg font-bold flex items-center justify-center hover:bg-borde/25 active:scale-90 transition disabled:opacity-30 disabled:cursor-not-allowed"
      >
        +
      </button>
    </div>
  )
}

function MatchCard({ partido, jornadaBloqueada = false }) {
  const navigate = useNavigate()
  const [golesLocal, setGolesLocal] = useState(String(partido.mi_pronostico?.goles_local_predicho ?? 0))
  const [golesVisitante, setGolesVisitante] = useState(String(partido.mi_pronostico?.goles_visitante_predicho ?? 0))
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
    mutacion.mutate()
  }

  const hora = partido.horario_estimado?.slice(11, 16)
  const puedePronosticar = partido.estado === 'Programado' && !jornadaBloqueada
  const minutoMostrado = partido.estado === 'En juego' ? minutoEstimado(partido.horario_estimado, partido.minuto_partido) : null

  return (
    <div
      onClick={() => navigate(`/partidos/${partido.id}`)}
      className="bg-fondo border border-borde/30 rounded-lg p-4 cursor-pointer hover:border-acento/40 transition"
    >
      <div className="flex items-center justify-between mb-3">
        <span className={`font-body text-xs font-bold uppercase tracking-wider px-2.5 py-1 rounded-full ${BADGE_ESTADO[partido.estado] ?? 'bg-borde/15 text-borde'}`}>
          {partido.estado === 'Jugado' ? 'Finalizado' : partido.estado}
          {minutoMostrado && ` · ${minutoMostrado}`}
        </span>
        {hora && partido.estado === 'Programado' && <span className="font-marcador text-sm text-texto tabular-nums">{hora}</span>}
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

      {puedePronosticar && (
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
              <div className="flex gap-4 justify-center items-center">
                <SelectorGoles valor={golesLocal} onCambiar={setGolesLocal} />
                <span className="font-marcador text-borde text-xl">-</span>
                <SelectorGoles valor={golesVisitante} onCambiar={setGolesVisitante} />
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

      {partido.estado === 'Programado' && jornadaBloqueada && !partido.mi_pronostico && (
        <p className="mt-3 pt-3 border-t border-borde/40 text-center font-body text-xs text-borde">
          Ya no admite pronósticos — otro partido de la jornada ya ha empezado.
        </p>
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
