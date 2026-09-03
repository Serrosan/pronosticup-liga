import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import client from '../api/client'
import { useAuth } from '../context/AuthContext'
import EvolutionChart from '../components/EvolutionChart'
import TicketHeader from '../components/TicketHeader'
import SkeletonLista from '../components/SkeletonLista'
import TickerNovedades from '../components/TickerNovedades'
import PermisoNotificaciones from '../components/PermisoNotificaciones'

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

      <PermisoNotificaciones />

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

      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden">
          <TicketHeader
            titulo={data.proxima_jornada.numero ? `Jornada ${data.proxima_jornada.numero}` : 'Próxima jornada'}
            accion={data.proxima_jornada.numero && (
              <Link to={`/jornadas/${data.proxima_jornada.numero}`} className="font-body text-[10px] text-acento hover:underline">
                Pronosticar →
              </Link>
            )}
          />
          <div className="px-4">
            {data.proxima_jornada.partidos.length === 0 ? (
              <p className="font-body text-sm text-borde py-4 text-center">No hay más partidos programados 🎉</p>
            ) : (
              <div className="flex flex-col divide-y divide-borde/10">
                {data.proxima_jornada.partidos.map((p) => (
                  <div key={p.id} className="flex items-center justify-between py-2.5">
                    <div className="flex items-center gap-2 min-w-0">
                      <Escudo url={p.escudo_local} alt={p.equipo_local} />
                      <p className="font-body text-base text-texto truncate">{p.equipo_local} <span className="text-borde">vs</span> {p.equipo_visitante}</p>
                      <Escudo url={p.escudo_visitante} alt={p.equipo_visitante} />
                    </div>
                    <span className="font-marcador text-xs text-borde tabular-nums shrink-0 ml-2">
                      {p.estado === 'Aplazado' ? 'Aplazado' : p.horario_estimado?.slice(5, 16).replace('T', ' · ')}
                    </span>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>

        <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden">
          <TicketHeader titulo={data.ultima_jornada_jugada ? `Jornada ${data.ultima_jornada_jugada} — Resultados` : 'Últimos resultados'} />
          <div className="px-4">
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
        <Link to="/jornadas/1" className="group bg-fondo border border-borde/30 rounded-lg p-5 hover:border-acento transition flex items-center justify-between">
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