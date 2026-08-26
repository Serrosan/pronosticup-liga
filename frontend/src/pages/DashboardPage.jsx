import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import client from '../api/client'
import { useAuth } from '../context/AuthContext'
import EvolutionChart from '../components/EvolutionChart'

function Escudo({ url, alt }) {
  if (!url) return <span className="w-6 h-6 rounded-full bg-borde/15 flex items-center justify-center text-xs shrink-0">⚽</span>
  return <img src={url} alt={alt} className="w-6 h-6 object-contain shrink-0" />
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

  if (isLoading) return <p className="font-body text-texto p-4">Cargando...</p>
  if (error) return <p className="font-body text-red-500 p-4">{error.response?.data?.message ?? 'Error al cargar el dashboard.'}</p>

  return (
    <div className="max-w-6xl mx-auto px-4 py-6">
      {/* Bienvenida */}
      <div className="bg-acento/10 border border-acento/30 rounded-lg p-5 mb-6 flex items-center justify-between flex-wrap gap-4">
        <div>
          <h1 className="font-display text-2xl text-texto">¡Hola, {usuario?.nombre}! ⚽</h1>
          <p className="font-body text-sm text-borde mt-1">
            Liga activa: <span className="font-semibold text-texto">{data.liga_nombre}</span>
            {data.posicion && (
              <> · Estás en la posición <span className="font-semibold text-texto">#{data.posicion}</span> de {data.total_participantes}</>
            )}
          </p>
        </div>
        <div className="text-right">
          <p className="font-marcador text-3xl text-acento">{data.puntos_totales}</p>
          <p className="font-body text-xs text-borde">puntos totales</p>
        </div>
      </div>

      {/* Avisos */}
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
        {/* Próxima jornada completa */}
        <div className="bg-fondo border border-borde/30 rounded-lg p-4">
          <div className="flex items-center justify-between mb-3">
            <h2 className="font-display text-lg text-texto">
              {data.proxima_jornada.numero ? `Jornada ${data.proxima_jornada.numero}` : 'Próxima jornada'}
            </h2>
            {data.proxima_jornada.numero && (
              <Link to={`/jornadas/${data.proxima_jornada.numero}`} className="font-body text-xs text-acento hover:underline">
                Pronosticar →
              </Link>
            )}
          </div>
          {data.proxima_jornada.partidos.length === 0 ? (
            <p className="font-body text-sm text-borde py-4 text-center">No hay más partidos programados 🎉</p>
          ) : (
            <div className="flex flex-col divide-y divide-borde/10">
              {data.proxima_jornada.partidos.map((p) => (
                <div key={p.id} className="flex items-center justify-between py-2.5 first:pt-0 last:pb-0">
                  <div className="flex items-center gap-2 min-w-0">
                    <Escudo url={p.escudo_local} alt={p.equipo_local} />
                    <p className="font-body text-sm text-texto truncate">{p.equipo_local} <span className="text-borde">vs</span> {p.equipo_visitante}</p>
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

        {/* Últimos resultados */}
        <div className="bg-fondo border border-borde/30 rounded-lg p-4">
          <h2 className="font-display text-lg text-texto mb-3">Últimos resultados</h2>
          {data.ultimos_resultados.length === 0 ? (
            <p className="font-body text-sm text-borde py-4 text-center">Aún no hay resultados.</p>
          ) : (
            <div className="flex flex-col divide-y divide-borde/10">
              {data.ultimos_resultados.map((p) => (
                <div key={p.id} className="flex items-center justify-between py-2.5 first:pt-0 last:pb-0">
                  <div className="flex items-center gap-2 min-w-0">
                    <Escudo url={p.escudo_local} alt={p.equipo_local} />
                    <div className="min-w-0">
                      <p className="font-body text-sm text-texto truncate">
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

      {/* Gráfica de evolución */}
      <div className="bg-fondo border border-borde/30 rounded-lg p-4 mt-6">
        <h2 className="font-display text-lg text-texto mb-3">Evolución de puntos</h2>
        <EvolutionChart evolucion={data.evolucion} />
      </div>

      {/* Accesos rápidos */}
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