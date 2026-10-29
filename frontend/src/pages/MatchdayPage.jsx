import { useState } from 'react'
import { useParams, useNavigate, Link } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import client from '../api/client'
import MatchCard from '../components/MatchCard'
import SelectorJornada from '../components/SelectorJornada'
import PestanasJornada from '../components/PestanasJornada'
import useTitulo from '../hooks/useTitulo'
import SkeletonJornada from '../components/SkeletonJornada'
import MomentoDecisivo from '../components/MomentoDecisivo'
import { useAuth } from '../context/AuthContext'
import { useToast } from '../context/ToastContext'
import { formatearActualizacion } from '../utils/tiempo'

const TOTAL_JORNADAS = 38
const JORNADAS = Array.from({ length: TOTAL_JORNADAS }, (_, i) => i + 1)
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

const ETIQUETA_TIPO_FALTA = { Local: 'Victorias locales', Visitante: 'Victorias visitantes', Empate: 'Empates' }
const ICONO_TIPO_FALTA = { Local: '🏠', Visitante: '✈️', Empate: '🟰' }

// Todas las Faltas de tipo "requisito" activas contra ti, a la vez: cada condición
// con su progreso, cuánto margen te queda y qué Falta queda "tapada" por otra más
// exigente del mismo tipo (no se suman: manda la más exigente).
function AvisoFaltaActiva({ falta }) {
  if (!falta) return null

  const requisitos = falta.requisitos?.length
    ? falta.requisitos
    : [{ tipo: falta.tipo, cantidad: falta.cantidad, cumplidos: falta.cumplidos, faltas: [] }]
  const pendientes = requisitos.filter((r) => r.cumplidos < r.cantidad)
  const faltan = falta.faltan ?? pendientes.reduce((suma, r) => suma + (r.cantidad - r.cumplidos), 0)
  const sinPronosticar = falta.partidos_sin_pronosticar ?? 0
  const numeroFaltas = requisitos.reduce((suma, r) => suma + Math.max(1, r.faltas?.length ?? 0), 0)
  const tapadas = requisitos.flatMap((r) => (r.faltas ?? []).filter((f) => !f.manda).map((f) => ({ ...f, tipo: r.tipo, cubre: r.cantidad })))
  const todoCumplido = pendientes.length === 0

  const estilo = todoCumplido
    ? { caja: 'bg-acento/10 border-acento/30', titulo: 'text-acento' }
    : falta.cumplible
      ? { caja: 'bg-premio/10 border-premio/40', titulo: 'text-premio' }
      : { caja: 'bg-red-500/10 border-red-500/30', titulo: 'text-red-500' }

  return (
    <div className={`max-w-md mx-auto mb-6 border rounded-lg px-4 py-3 ${estilo.caja}`}>
      <p className={`font-body text-sm font-semibold ${estilo.titulo}`}>
        {todoCumplido
          ? `✓ Cumples ${numeroFaltas > 1 ? `las ${numeroFaltas} Faltas` : 'la Falta'} que tienes encima esta jornada`
          : `⚠️ Tienes ${numeroFaltas > 1 ? `${numeroFaltas} Faltas` : 'una Falta'} encima esta jornada. Para cumplir${numeroFaltas > 1 ? 'las todas' : 'la'} necesitas:`}
      </p>

      <ul className="mt-2 flex flex-col gap-1.5">
        {requisitos.map((r) => {
          const hecho = r.cumplidos >= r.cantidad
          const resto = r.cantidad - r.cumplidos
          const quienes = (r.faltas ?? []).filter((f) => f.manda).map((f) => `${f.atacante} (${f.carta})`).join(', ')
          return (
            <li key={r.tipo} className="font-body text-sm text-texto">
              <span aria-hidden="true">{ICONO_TIPO_FALTA[r.tipo] ?? '•'} </span>
              <span className="font-semibold">{ETIQUETA_TIPO_FALTA[r.tipo] ?? r.tipo}: {Math.min(r.cumplidos, r.cantidad)} de {r.cantidad}</span>
              {hecho ? <span className="text-acento font-semibold"> ✓ cumplido</span> : <span className="text-borde"> (te falta{resto > 1 ? 'n' : ''} {resto})</span>}
              {quienes && <span className="block font-body text-[11px] text-borde pl-5">De: {quienes}</span>}
            </li>
          )
        })}
      </ul>

      {!todoCumplido && (
        <p className={`font-body text-xs mt-2 ${falta.cumplible ? 'text-texto' : 'text-red-500 font-semibold'}`}>
          {!falta.cumplible
            ? 'Ya no da tiempo a cumplirlas todas con los partidos que te quedan.'
            : faltan >= sinPronosticar
              ? `Sin margen: los ${sinPronosticar} partido${sinPronosticar > 1 ? 's' : ''} que te quedan tienen que ser de esos resultados.`
              : `Te quedan ${sinPronosticar} partidos por pronosticar y necesitas ${faltan} con esos resultados: todavía llegas.`}
        </p>
      )}

      {tapadas.map((f, i) => (
        <p key={i} className="font-body text-[11px] text-borde mt-1.5">
          ℹ️ La de {f.atacante} ({f.cantidad} {(ETIQUETA_TIPO_FALTA[f.tipo] ?? f.tipo).toLowerCase()}) no se suma: ya la cubre la de {f.cubre}. Manda la más exigente.
        </p>
      ))}
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
      return {
        partidos: respuesta.data.data,
        ultimaActualizacion: respuesta.data.meta?.ultima_actualizacion,
        jornadaBloqueada: respuesta.data.meta?.jornada_bloqueada,
        faltaActiva: respuesta.data.meta?.falta_activa ?? null,
        jornadaAbierta: respuesta.data.meta?.jornada_abierta ?? true,
        apertura: respuesta.data.meta?.apertura ?? null,
      }
    },
    placeholderData: (datosAnteriores) => datosAnteriores,
  })

  const partidos = data?.partidos
  // Ligas con cartas: la jornada se abre cuando termina la anterior.
  const cerrada = data?.jornadaAbierta === false
  const motivoCerrada = cerrada ? `🔒 Se podrá pronosticar cuando termine la jornada ${numeroJornada - 1}.` : null

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
      <div className="flex justify-center mb-1">
        <SelectorJornada jornadas={JORNADAS} valor={numeroJornada} onCambiar={ir} grande />
      </div>
      <PestanasJornada jornada={numeroJornada} activa="pronosticar" />
      <EstadoGoleadores jornada={numeroJornada} />
      <CopiarDeOtraLiga jornada={numeroJornada} jornadaBloqueada={data?.jornadaBloqueada || cerrada} />

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

      <AvisoFaltaActiva falta={data?.faltaActiva} />

      {cerrada && (
        <div className="max-w-md mx-auto mb-6 bg-borde/10 border border-borde/30 rounded-lg px-4 py-3 text-center">
          <p className="font-body text-sm text-texto font-semibold">🔒 Todavía no se puede pronosticar</p>
          <p className="font-body text-xs text-borde mt-1">{data.apertura}</p>
        </div>
      )}

      {!cerrada && sinPronosticar.length > 0 && (
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
                <MatchCard partido={{ ...partidosDelDia[0], jornada }} jornadaBloqueada={data?.jornadaBloqueada || cerrada} motivoBloqueo={motivoCerrada} />
              </div>
            </div>
          ) : (
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 max-w-4xl mx-auto">
              {partidosDelDia.map((partido) => (
                <MatchCard key={partido.id} partido={{ ...partido, jornada }} jornadaBloqueada={data?.jornadaBloqueada || cerrada} motivoBloqueo={motivoCerrada} />
              ))}
            </div>
          )}
        </div>
      ))}
    </div>
  )
}

export default MatchdayPage
