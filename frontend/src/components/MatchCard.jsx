import { Link } from 'react-router-dom'
import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import client from '../api/client'

function Escudo({ url, alt }) {
  if (!url) return <span className="w-8 h-8 rounded-full bg-borde/15 flex items-center justify-center text-xs shrink-0">⚽</span>
  return <img src={url} alt={alt} className="w-8 h-8 object-contain shrink-0" />
}

function EquipoEnlace({ equipo, alinear }) {
  const nombre = equipo?.nombre_corto || equipo?.nombre

  return (
    <Link
      to={`/equipos/${equipo?.id}`}
      state={{ nombre, escudo_url: equipo?.escudo_url }}
      className={`flex items-center gap-2 flex-1 min-w-0 hover:opacity-70 transition ${alinear === 'derecha' ? 'justify-end text-right' : ''}`}
    >
      {alinear === 'derecha' && <span className="font-body font-medium text-texto text-sm sm:text-base">{nombre}</span>}
      <Escudo url={equipo?.escudo_url} alt={nombre} />
      {alinear !== 'derecha' && <span className="font-body font-medium text-texto text-sm sm:text-base">{nombre}</span>}
    </Link>
  )
}

const BADGE_ESTADO = {
  Jugado: 'bg-acento/15 text-acento',
  Aplazado: 'bg-red-400/15 text-red-400',
  Programado: 'bg-borde/15 text-borde',
}

function MatchCard({ partido }) {
  const [golesLocal, setGolesLocal] = useState('')
  const [golesVisitante, setGolesVisitante] = useState('')
  const queryClient = useQueryClient()

  const mutacion = useMutation({
    mutationFn: async ({ resultado1x2, golesLocal, golesVisitante }) => {
      const respuesta = await client.post('/api/v1/pronosticos', {
        id_partido: partido.id,
        resultado_1x2: resultado1x2,
        goles_local_predicho: Number(golesLocal),
        goles_visitante_predicho: Number(golesVisitante),
      })
      return respuesta.data.data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['partidos', partido.jornada] })
    },
  })

  function pronosticar(resultado1x2) {
    if (golesLocal === '' || golesVisitante === '') {
      alert('Rellena primero el marcador que crees que habrá.')
      return
    }
    mutacion.mutate({ resultado1x2, golesLocal, golesVisitante })
  }

  return (
    <div className="bg-fondo border border-borde/40 rounded-lg p-4 w-full">
      <div className="flex items-center justify-between mb-3">
        <span className={`font-body text-xs font-bold uppercase tracking-wider px-2 py-1 rounded-full ${BADGE_ESTADO[partido.estado] ?? 'bg-borde/15 text-borde'}`}>
          {partido.estado}
        </span>
        {partido.horario_estimado && (
          <span className="font-marcador text-sm text-texto tabular-nums">{partido.horario_estimado.slice(11, 16)}h</span>
        )}
      </div>

      <div className="flex items-center justify-between gap-2">
        <EquipoEnlace equipo={partido.equipo_local} alinear="derecha" />
        <span className="font-marcador text-2xl text-acento tabular-nums px-2 shrink-0">
          {partido.goles_casa ?? '–'} : {partido.goles_fuera ?? '–'}
        </span>
        <EquipoEnlace equipo={partido.equipo_visitante} />
      </div>

      {partido.estado === 'Programado' && (
        <div className="mt-4 pt-4 border-t border-borde/40">
          <div className="flex gap-2 justify-center">
            <input type="number" min="0" placeholder="0" value={golesLocal} onChange={(e) => setGolesLocal(e.target.value)}
              className="font-marcador w-16 text-center bg-borde/20 text-texto rounded border border-borde/40 py-1.5" />
            <span className="font-marcador text-borde self-center">-</span>
            <input type="number" min="0" placeholder="0" value={golesVisitante} onChange={(e) => setGolesVisitante(e.target.value)}
              className="font-marcador w-16 text-center bg-borde/20 text-texto rounded border border-borde/40 py-1.5" />
          </div>
          <div className="flex gap-2 mt-3">
            <button onClick={() => pronosticar('Local')} disabled={mutacion.isPending}
              className="flex-1 bg-acento text-fondo font-body font-semibold text-sm rounded py-2.5 hover:brightness-110 disabled:opacity-50">Local</button>
            <button onClick={() => pronosticar('Empate')} disabled={mutacion.isPending}
              className="flex-1 bg-borde text-fondo font-body font-semibold text-sm rounded py-2.5 hover:brightness-110 disabled:opacity-50">Empate</button>
            <button onClick={() => pronosticar('Visitante')} disabled={mutacion.isPending}
              className="flex-1 bg-acento text-fondo font-body font-semibold text-sm rounded py-2.5 hover:brightness-110 disabled:opacity-50">Visitante</button>
          </div>
          {mutacion.isSuccess && <p className="font-body text-sm text-acento mt-2">✓ Pronóstico guardado</p>}
          {mutacion.isError && <p className="font-body text-sm text-red-500 mt-2">{mutacion.error.response?.data?.message ?? 'Algo falló'}</p>}
        </div>
      )}
    </div>
  )
}

export default MatchCard