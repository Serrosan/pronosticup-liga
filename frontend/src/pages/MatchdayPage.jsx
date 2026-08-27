import { useParams, useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import client from '../api/client'
import MatchCard from '../components/MatchCard'

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

  const { data, error } = useQuery({
    queryKey: ['partidos', jornada],
    queryFn: async () => {
      const respuesta = await client.get(`/api/v1/jornadas/${jornada}/partidos`)
      return {
        partidos: respuesta.data.data,
        ultimaActualizacion: respuesta.data.meta?.ultima_actualizacion,
      }
    },
    placeholderData: (datosAnteriores) => datosAnteriores,
  })

  const partidos = data?.partidos

  function ir(numero) {
    if (numero < 1 || numero > TOTAL_JORNADAS) return
    navigate(`/jornadas/${numero}`)
  }

  const grupos = partidos ? agruparPorDia(partidos) : []

  return (
    <div className="max-w-5xl mx-auto px-4 py-4">
      <div className="flex items-center justify-center gap-4 mb-4">
        <button
          onClick={() => ir(numeroJornada - 1)}
          disabled={numeroJornada <= 1}
          className="font-body text-texto disabled:opacity-30 disabled:cursor-not-allowed hover:text-acento text-xl px-2"
          aria-label="Jornada anterior"
        >
          ←
        </button>
        <div className="text-center w-48">
          <h2 className="font-display text-xl text-texto">Jornada {numeroJornada}</h2>
          {grupos.length > 0 && (
            <p className="font-body text-xs font-semibold text-acento">
              {formatearFecha(grupos[0][0])} — {formatearFecha(grupos[grupos.length - 1][0])}
            </p>
          )}
          {data?.ultimaActualizacion && (
            <p className="font-body text-[10px] text-borde mt-0.5">
              Actualizado {new Date(data.ultimaActualizacion).toLocaleString('es-ES', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })}
            </p>
          )}
        </div>
        <button
          onClick={() => ir(numeroJornada + 1)}
          disabled={numeroJornada >= TOTAL_JORNADAS}
          className="font-body text-texto disabled:opacity-30 disabled:cursor-not-allowed hover:text-acento text-xl px-2"
          aria-label="Jornada siguiente"
        >
          →
        </button>
      </div>

      {!partidos && !error && <p className="font-body text-texto p-4 text-center">Cargando partidos...</p>}
      {error && <p className="font-body text-red-500 p-4 text-center">Error al cargar: {error.message}</p>}

      {grupos.map(([fecha, partidosDelDia]) => (
        <div key={fecha} className="mb-6">
          <div className="flex justify-center">
            <p className="inline-block font-body text-sm font-bold text-premio bg-premio/10 px-3 py-1.5 rounded-full mb-3">
              {formatearFecha(fecha)}
            </p>
          </div>
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-3">
            {partidosDelDia.map((partido) => (
              <MatchCard key={partido.id} partido={{ ...partido, jornada }} />
            ))}
          </div>
        </div>
      ))}
    </div>
  )
}

export default MatchdayPage