import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import client from '../api/client'
import { useAuth } from '../context/AuthContext'
import EvolutionChart from '../components/EvolutionChart'
import TicketHeader from '../components/TicketHeader'
import SkeletonLista from '../components/SkeletonLista'
import TickerNovedades from '../components/TickerNovedades'
import { formatearFechaHora } from '../utils/tiempo'

function Escudo({ url, alt }) {
  if (!url) return <span className="w-6 h-6 rounded-full bg-borde/15 flex items-center justify-center text-xs shrink-0">⚽</span>
  return <img src={url} alt={alt} className="w-6 h-6 object-contain shrink-0" />
}

function saludoSegunHora() {
  const hora = new Date().getHours()
  if (hora < 7) return 'Buenas noches'
  if (hora < 13) return 'Buenos días'
  if (hora < 20) return 'Buenas tardes'
  return 'Buenas noches'
}

function RachaAciertos() {
  const { data } = useQuery({
    queryKey: ['racha-aciertos'],
    queryFn: async () => (await client.get('/api/v1/racha-aciertos')).data.data,
  })

  if (!data || data.racha < 2) return null

  return (
    <div className="bg-premio/10 border border-premio/30 rounded-lg px-4 py-2.5 mb-6 text-center">
      <p className="font-body text-sm text-premio font-semibold">
        🔥 Llevas {data.racha} aciertos seguidos
      </p>
    </div>
  )
}

function ProgresoJornada({ partidos }) {
  if (!partidos || partidos.length === 0) return null

  const total = partidos.length
  const hechos = partidos.filter((p) => p.mi_pronostico).length
  const completo = hechos === total

  return (
    <div className="flex items-center gap-2">
      <div className="flex-1 h-1.5 bg-borde/15 rounded-full overflow-hidden">
        <div
          className={`h-full rounded-full transition-all ${completo ? 'bg-acento' : 'bg-premio'}`}
          style={{ width: `${(hechos / total) * 100}%` }}
        />
      </div>
      <span className={`font-marcador text-[10px] font-bold shrink-0 ${completo ? 'text-acento' : 'text-premio'}`}>
        {hechos}/{total}
      </span>
    </div>
  )
}

function ProgresoGrupo({ jornada }) {
  const { data } = useQuery({
    queryKey: ['progreso-liga', jornada],
    queryFn: async () => (await client.get(`/api/v1/jornadas/${jornada}/progreso-liga`)).data.data,
    enabled: !!jornada,
  })

  if (!data || data.total_miembros === 0) return null

  return (
    <p className="font-body text-[11px] text-borde mt-1.5 px-1">
      👥 {data.completados}/{data.total_miembros} miembros ya han completado sus pronósticos
    </p>
  )
}

function calcularRestante(cierreEn) {
  const diferenciaMs = new Date(cierreEn) - Date.now()
  if (diferenciaMs <= 0) return null

  const horas = Math.floor(diferenciaMs / 3600000)
  const minutos = Math.floor((diferenciaMs % 3600000) / 60000)

  if (horas >= 24) {
    const dias = Math.floor(horas / 24)
    const horasRestantes = horas % 24
    return `${dias}d ${horasRestantes}h`
  }
  if (horas >= 1) return `${horas}h ${minutos}min`
  return `${minutos}min`
}

function CuentaAtrasCierre({ cierreEn }) {
  const [restante, setRestante] = useState(() => cierreEn ? calcularRestante(cierreEn) : null)

  useEffect(() => {
    if (!cierreEn) return

    setRestante(calcularRestante(cierreEn))
    const intervalo = setInterval(() => setRestante(calcularRestante(cierreEn)), 60000)
    return () => clearInterval(intervalo)
  }, [cierreEn])

  if (!cierreEn || !restante) return null

  const urgente = new Date(cierreEn) - Date.now() < 3 * 3600000

  return (
    <p className={`font-body text-[11px] mt-1 px-1 ${urgente ? 'text-red-400 font-semibold' : 'text-borde'}`}>
      ⏱️ Quedan {restante} para que se bloquee esta jornada
    </p>
  )
}

function FilaPartidoProximo({ p }) {
  const yaJugado = p.estado === 'Jugado' || p.estado === 'En juego'

  return (
    <div className="flex items-center justify-between py-2.5">
      <div className="flex items-center gap-2 min-w-0">
        <Escudo url={p.escudo_local} alt={p.equipo_local} />
        <p className="font-body text-base text-texto truncate">{p.equipo_local} <span className="text-borde">vs</span> {p.equipo_visitante}</p>
        <Escudo url={p.escudo_visitante} alt={p.equipo_visitante} />
      </div>
      {p.estado === 'Aplazado' ? (
        <span className="font-marcador text-xs text-borde tabular-nums shrink-0 ml-2">Aplazado</span>
      ) : yaJugado ? (
        <span className="font-marcador text-sm font-bold text-texto tabular-nums shrink-0 ml-2">
          {p.goles_casa}-{p.goles_fuera}
        </span>
      ) : (
        <span className="font-marcador text-xs text-borde tabular-nums shrink-0 ml-2">
          {formatearFechaHora(p.horario_estimado)}
        </span>
      )}
    </div>
  )
}

function DashboardPage() {
  const { usuario } = useAuth()

  const { data, isLoading, error } = useQuery({
    queryKey: ['dashboard'],
    queryFn: async () => {
      const respuesta = await client.get('/api/v1/dashboard')
      return respuesta.data.data
    },
  })

  if (isLoading) return <div className="max-w-6xl mx-auto px-4 py-6"><SkeletonLista /></div>
  if (error) return <p className="font-body text-red-500 p-4">{error.response?.data?.message ?? 'Error al cargar el dashboard.'}</p>

  const primerNombre = usuario?.nombre?.split(' ')[0]

  return (
    <div className="max-w-6xl mx-auto px-4 py-6">
      <h1 className="font-display text-2xl text-texto mb-4">{saludoSegunHora()}, {primerNombre} ⚽</h1>
      <TickerNovedades novedades={data.novedades} />

      <div className="bg-fondo border border-borde/30 rounded-lg px-6 py-3 flex items-center justify-around mb-6">
        {[
          { label: 'LIGA', valor: data.liga_nombre, texto: true },
          { label: 'POSICIÓN', valor: data.posicion ? `#${data.posicion}` : '—', color: 'var(--color-acento)' },
          { label: 'PUNTOS', valor: data.puntos_totales, color: 'var(--color-premio)' },
          { label: 'PARTICIPANTES', valor: data.total_participantes, color: 'var(--color-texto)' },
        ].map((item, i) => (
          <div key={item.label} className={`text-center flex-1 ${i > 0 ? 'border-l border-dotted border-borde/30' : ''}`}>
            {item.texto ? (
              <p className="font-body text-sm font-semibold text-texto truncate px-1">{item.valor}</p>
            ) : (
              <p className="font-marcador text-xl tabular-nums" style={{ color: item.color, textShadow: `0 0 10px ${item.color}55` }}>
                {item.valor}
              </p>
            )}
            <p className="font-body text-[9px] tracking-widest text-borde mt-0.5">{item.label}</p>
          </div>
        ))}
      </div>

      <RachaAciertos />

      {data.avisos.length > 0 && (
        <div className="bg-premio/10 border border-premio/30 rounded-lg p-4 mb-6">
          <h2 className="font-body text-sm font-semibold text-premio mb-2">⚠️ Avisos</h2>
          <ul className="flex flex-col gap-1">
            {data.avisos.map((aviso, i) => (
              <li key={i} className="font-body text-sm text-texto">{aviso.mensaje}</li>
            ))}
          </ul>
        </div>
      )}

      <div className="grid grid-cols-1 md:grid-cols-2 gap-6 items-stretch">
        <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden flex flex-col">
          <TicketHeader
            titulo={data.proxima_jornada.numero ? `Jornada ${data.proxima_jornada.numero}` : 'Próxima jornada'}
            accion={data.proxima_jornada.numero && (
              <Link to={`/jornadas/${data.proxima_jornada.numero}`} className="font-body text-[10px] text-acento hover:underline">
                Pronosticar →
              </Link>
            )}
          />
          {data.proxima_jornada.partidos.length > 0 && (
            <div className="px-4 pt-3">
              <ProgresoJornada partidos={data.proxima_jornada.partidos} />
              <ProgresoGrupo jornada={data.proxima_jornada.numero} />
              <CuentaAtrasCierre cierreEn={data.proxima_jornada.cierre_en} />
            </div>
          )}
          <div className="px-4 flex-1">
            {data.proxima_jornada.partidos.length === 0 ? (
              <p className="font-body text-sm text-borde py-4 text-center">No hay más partidos programados 🎉</p>
            ) : (
              <div className="flex flex-col divide-y divide-borde/10">
                {data.proxima_jornada.partidos.map((p) => <FilaPartidoProximo key={p.id} p={p} />)}
              </div>
            )}
          </div>
        </div>
        <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden flex flex-col">
          <TicketHeader titulo={data.ultima_jornada_jugada ? `Jornada ${data.ultima_jornada_jugada} — Resultados` : 'Últimos resultados'} />
          <div className="px-4 flex-1">
            {data.ultimos_resultados.length === 0 ? (
              <p className="font-body text-sm text-borde py-4 text-center">Aún no hay resultados.</p>
            ) : (
              <div className="flex flex-col divide-y divide-borde/10">
                {data.ultimos_resultados.map((p) => (
                  <div key={p.id} className="flex items-center justify-between py-2.5">
                    <div className="flex items-center gap-2 min-w-0">
                      <Escudo url={p.escudo_local} alt={p.equipo_local} />
                      <div className="min-w-0">
                        <p className="font-body text-base text-texto truncate">
                          {p.equipo_local} <span className="font-marcador">{p.goles_casa}-{p.goles_fuera}</span> {p.equipo_visitante}
                        </p>
                        <p className="font-body text-xs text-borde">Tu pronóstico: {p.mi_pronostico ?? 'no enviado'}</p>
                      </div>
                      <Escudo url={p.escudo_visitante} alt={p.equipo_visitante} />
                    </div>
                    <span className={`font-marcador text-xs font-semibold px-2.5 py-1 rounded shrink-0 ml-2 ${
                      p.acerte === null ? 'bg-borde/10 text-borde' : p.acerte ? 'bg-acento/15 text-acento' : 'bg-red-500/10 text-red-500'
                    }`}>
                      {p.acerte === null ? '—' : `${p.acerte ? '✓' : '✗'} ${p.puntos}pt`}
                    </span>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      </div>

      <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden mt-6">
        <TicketHeader titulo="Evolución de puntos" />
        <div className="px-4 py-2">
          <EvolutionChart evolucion={data.evolucion} />
        </div>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-6">
        <Link to="/jornadas" className="group bg-fondo border border-borde/30 rounded-lg p-5 hover:border-acento transition flex items-center justify-between">
          <div>
            <p className="font-display text-base text-texto">Pronosticar jornada</p>
            <p className="font-body text-xs text-borde mt-0.5">Marca tus resultados antes del cierre</p>
          </div>
          <span className="font-body text-acento text-xl group-hover:translate-x-1 transition">→</span>
        </Link>
        <Link to="/clasificacion" className="group bg-fondo border border-borde/30 rounded-lg p-5 hover:border-acento transition flex items-center justify-between">
          <div>
            <p className="font-display text-base text-texto">Ver clasificación</p>
            <p className="font-body text-xs text-borde mt-0.5">Comprueba cómo vas contra el grupo</p>
          </div>
          <span className="font-body text-acento text-xl group-hover:translate-x-1 transition">→</span>
        </Link>
      </div>
    </div>
  )
}

export default DashboardPage
