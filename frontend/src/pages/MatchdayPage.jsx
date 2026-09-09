import { useState } from 'react'
import { useParams, useNavigate, Link } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import client from '../api/client'
import MatchCard from '../components/MatchCard'
import useTitulo from '../hooks/useTitulo'
import SkeletonJornada from '../components/SkeletonJornada'
import MomentoDecisivo from '../components/MomentoDecisivo'
import { useAuth } from '../context/AuthContext'
import { useToast } from '../context/ToastContext'
import { formatearActualizacion } from '../utils/tiempo'

const TOTAL_JORNADAS = 38
const DIAS = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado']

function agruparPorDia(partidos) {
  const grupos = {}
  partidos.forEach((p) => {
    const fecha = p.horario_estimado?.slice(0, 10) ?? 'sin-fecha'
    if (!grupos[fecha]) grupos[fecha] = []
    grupos[fecha].push(p)
  })
  return Object.entries(grupos).sort(([a], [b]) => a.localeCompare(b))
}

function formatearFecha(fechaISO) {
  if (!fechaISO || fechaISO === 'sin-fecha') return 'Sin fecha'
  const [anio, mes, dia] = fechaISO.split('-')
  const fecha = new Date(anio, mes - 1, dia)
  return `${DIAS[fecha.getDay()]} ${dia}/${mes}/${anio}`
}

function EstadoGoleadores({ jornada }) {
  const { data: seleccion } = useQuery({
    queryKey: ['goleadores', jornada],
    queryFn: async () => (await client.get(`/api/v1/jornadas/${jornada}/goleadores`)).data.data,
  })

  const total = seleccion?.length ?? 0
  const completo = total === 5

  return (
    <div className="flex justify-center mb-1">
      <Link
        to={`/jornadas/${jornada}/goleadores`}
        className={`font-body text-xs rounded-full px-3 py-1 border transition ${
          completo
            ? 'text-acento border-acento/40 bg-acento/10 hover:bg-acento/20'
            : 'text-premio border-premio/40 hover:bg-premio/10'
        }`}
      >
        {completo ? `✓ Tus 5 goleadores elegidos` : `⚽ Elegir tus 5 goleadores${total > 0 ? ` (${total}/5)` : ''}`}
      </Link>
    </div>
  )
}

function CopiarDeOtraLiga({ jornada, jornadaBloqueada }) {
  const { usuario } = useAuth()
  const toast = useToast()
  const queryClient = useQueryClient()
  const [abierto, setAbierto] = useState(false)

  const { data: ligas } = useQuery({
    queryKey: ['mis-ligas'],
    queryFn: async () => (await client.get('/api/v1/ligas')).data.data,
  })

  const copiar = useMutation({
    mutationFn: (idLigaOrigen) => client.post(`/api/v1/jornadas/${jornada}/copiar-pronosticos`, { id_liga_origen: idLigaOrigen }),
    onSuccess: (respuesta) => {
      toast.exito(respuesta.data.message)
      setAbierto(false)
      queryClient.invalidateQueries({ queryKey: ['partidos', String(jornada)] })
      queryClient.invalidateQueries({ queryKey: ['goleadores', jornada] })
    },
    onError: (err) => toast.error(err.response?.data?.message ?? 'No se pudo copiar.'),
  })

  const otrasLigas = ligas?.filter((l) => l.id !== usuario?.liga_activa?.id) ?? []

  if (jornadaBloqueada || otrasLigas.length === 0) return null

  return (
    <div className="flex justify-center mb-1">
      {!abierto ? (
        <button
          onClick={() => setAbierto(true)}
          className="font-body text-xs text-borde border border-borde/30 rounded-full px-3 py-1 hover:bg-borde/10"
        >
          📋 Copiar desde otra liga
        </button>
      ) : (
        <div className="bg-fondo border border-borde/30 rounded-lg px-3 py-2 flex items-center gap-2 flex-wrap justify-center">
          <span className="font-body text-xs text-borde">Copiar desde:</span>
          {otrasLigas.map((liga) => (
            <button
              key={liga.id}
              onClick={() => copiar.mutate(liga.id)}
              disabled={copiar.isPending}
              className="font-body text-xs font-semibold text-acento border border-acento/40 rounded-full px-3 py-1 hover:bg-acento/10 disabled:opacity-50"
            >
              {liga.nombre}
            </button>
          ))}
          <button onClick={() => setAbierto(false)} className="font-body text-xs text-borde hover:text-texto">
            Cancelar
          </button>
        </div>
      )}
    </div>
  )
}

function MatchdayPage() {
  const { jornada } = useParams()
  const navigate = useNavigate()
  const numeroJornada = Number(jornada)
  useTitulo(`Jornada ${numeroJornada}`)

  const { data } = useQuery({
    queryKey: ['partidos', jornada],
    queryFn: async () => {
      const respuesta = await client.get(`/api/v1/jornadas/${jornada}/partidos`)
      return { partidos: respuesta.data.data, ultimaActualizacion: respuesta.data.meta?.ultima_actualizacion, jornadaBloqueada: respuesta.data.meta?.jornada_bloqueada }
    },
    placeholderData: (datosAnteriores) => datosAnteriores,
  })

  const partidos = data?.partidos

  function ir(numero) {
    if (numero < 1 || numero > TOTAL_JORNADAS) return
    navigate(`/jornadas/${numero}`)
  }

  const grupos = partidos ? agruparPorDia(partidos) : []
  const sinPronosticar = partidos ? partidos.filter((p) => p.estado === 'Programado' && !p.mi_pronostico) : []
  const todosJugados = partidos && partidos.length > 0 && partidos.every((p) => p.estado === 'Jugado')
  const textoActualizacion = !todosJugados ? formatearActualizacion(data?.ultimaActualizacion) : null

  return (
    <div className="max-w-5xl mx-auto px-4 py-4">
      <div className="flex items-center justify-center gap-4 mb-1">
        <button
          onClick={() => ir(numeroJornada - 1)}
          disabled={numeroJornada <= 1}
          className="font-body text-texto disabled:opacity-30 disabled:cursor-not-allowed hover:text-acento text-xl px-2"
          aria-label="Jornada anterior"
        >
          ←
        </button>
        <h2 className="font-display text-xl text-texto whitespace-nowrap">Jornada {numeroJornada}</h2>
        <button
          onClick={() => ir(numeroJornada + 1)}
          disabled={numeroJornada >= TOTAL_JORNADAS}
          className="font-body text-texto disabled:opacity-30 disabled:cursor-not-allowed hover:text-acento text-xl px-2"
          aria-label="Jornada siguiente"
        >
          →
        </button>
      </div>
      <EstadoGoleadores jornada={numeroJornada} />
      <CopiarDeOtraLiga jornada={numeroJornada} jornadaBloqueada={data?.jornadaBloqueada} />

      {grupos.length > 0 && (
        <p className="font-body text-sm font-semibold text-acento text-center whitespace-nowrap mb-1">
          {formatearFecha(grupos[0][0])} — {formatearFecha(grupos[grupos.length - 1][0])}
        </p>
      )}

      {textoActualizacion && (
        <p className="font-body text-[11px] text-borde text-center mb-4">
          {textoActualizacion}
        </p>
      )}

      <MomentoDecisivo jornada={numeroJornada} />

      {sinPronosticar.length > 0 && (
        <div className="max-w-md mx-auto mb-6 bg-premio/10 border border-premio/30 rounded-lg px-4 py-3 text-center">
          <p className="font-body text-sm text-premio font-semibold">
            Te quedan {sinPronosticar.length} partido{sinPronosticar.length > 1 ? 's' : ''} por pronosticar
          </p>
        </div>
      )}

      {!partidos && <SkeletonJornada />}

      {grupos.map(([fecha, partidosDelDia]) => (
        <div key={fecha} className="mb-10">
          <div className="flex items-center gap-3 mb-4">
            <div className="h-px bg-borde/20 flex-1" />
            <p className="font-display text-base text-texto tracking-wide whitespace-nowrap">{formatearFecha(fecha)}</p>
            <div className="h-px bg-borde/20 flex-1" />
          </div>
          {partidosDelDia.length === 1 ? (
            <div className="flex justify-center">
              <div className="w-full max-w-md">
                <MatchCard partido={{ ...partidosDelDia[0], jornada }} jornadaBloqueada={data?.jornadaBloqueada} />
              </div>
            </div>
          ) : (
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 max-w-4xl mx-auto">
              {partidosDelDia.map((partido) => (
                <MatchCard key={partido.id} partido={{ ...partido, jornada }} jornadaBloqueada={data?.jornadaBloqueada} />
              ))}
            </div>
          )}
        </div>
      ))}
    </div>
  )
}

export default MatchdayPage