import { useParams, useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import client from '../api/client'
import MatchCard from '../components/MatchCard'
import useTitulo from '../hooks/useTitulo'
import SkeletonJornada from '../components/SkeletonJornada'

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

function MatchdayPage() {
  const { jornada } = useParams()
  const navigate = useNavigate()
  const numeroJornada = Number(jornada)

  useTitulo(`Jornada ${numeroJornada}`)

  const { data } = useQuery({
    queryKey: ['partidos', jornada],
    queryFn: async () => {
      const respuesta = await client.get(`/api/v1/jornadas/${jornada}/partidos`)
      return { partidos: respuesta.data.data, ultimaActualizacion: respuesta.data.meta?.ultima_actualizacion }
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

  const minutosDesdeActualizacion = data?.ultimaActualizacion
    ? Math.max(0, Math.round((Date.now() - new Date(data.ultimaActualizacion)) / 60000))
    : null

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

      {grupos.length > 0 && (
        <p className="font-body text-sm font-semibold text-acento text-center whitespace-nowrap mb-1">
          {formatearFecha(grupos[0][0])} — {formatearFecha(grupos[grupos.length - 1][0])}
        </p>
      )}

      {minutosDesdeActualizacion !== null && (
        <p className="font-body text-[11px] text-borde text-center mb-4">
          Actualizado hace {minutosDesdeActualizacion} min
        </p>
      )}

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
                <MatchCard partido={{ ...partidosDelDia[0], jornada }} />
              </div>
            </div>
          ) : (
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 max-w-4xl mx-auto">
              {partidosDelDia.map((partido) => (
                <MatchCard key={partido.id} partido={{ ...partido, jornada }} />
              ))}
            </div>
          )}
        </div>
      ))}
    </div>
  )
}

export default MatchdayPage