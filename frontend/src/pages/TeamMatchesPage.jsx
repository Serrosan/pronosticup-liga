import { useState } from 'react'
import { useParams, useLocation, Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import client from '../api/client'
import EstadoVacio from '../components/EstadoVacio'
import SkeletonLista from '../components/SkeletonLista'

function Escudo({ url, alt, tamano = 'w-8 h-8' }) {
  if (!url) return <span className={`${tamano} rounded-full bg-borde/15 flex items-center justify-center text-sm shrink-0`}>⚽</span>
  return <img src={url} alt={alt} className={`${tamano} object-contain shrink-0`} />
}

const TABS = [
  { key: 'info', label: 'Información' },
  { key: 'plantilla', label: 'Plantilla' },
  { key: 'partidos', label: 'Partidos' },
]

const ORDEN_POSICION = ['Portero', 'Defensa', 'Centrocampista', 'Delantero']

function calcularEdad(fechaNacimiento) {
  if (!fechaNacimiento) return null
  const nacimiento = new Date(fechaNacimiento)
  const hoy = new Date()
  let edad = hoy.getFullYear() - nacimiento.getFullYear()
  const aunNoCumplidos = hoy.getMonth() < nacimiento.getMonth() ||
    (hoy.getMonth() === nacimiento.getMonth() && hoy.getDate() < nacimiento.getDate())
  if (aunNoCumplidos) edad--
  return edad
}

function BarraCapacidad({ capacidad, maxima }) {
  const porcentaje = Math.max(4, Math.round((capacidad / maxima) * 100))
  return (
    <div>
      <div className="flex items-baseline justify-between mb-1">
        <p className="font-body text-xs text-borde">Capacidad del estadio</p>
        <p className="font-marcador text-sm text-texto">{capacidad?.toLocaleString('es-ES')}</p>
      </div>
      <div className="w-full h-2.5 bg-borde/10 rounded-full overflow-hidden">
        <div className="h-full bg-acento rounded-full" style={{ width: `${porcentaje}%` }} />
      </div>
      <p className="font-body text-[10px] text-borde/60 mt-1">de {maxima?.toLocaleString('es-ES')} (el mayor de LaLiga)</p>
    </div>
  )
}

function TarjetaJugador({ jugador }) {
  const nombreMostrado = jugador.nombre_camiseta || `${jugador.nombre} ${jugador.apellidos ?? ''}`.trim()
  const edad = calcularEdad(jugador.fecha_nacimiento)

  return (
    <Link
      to={`/jugadores/${jugador.id}`}
      className="flex items-center gap-3 bg-borde/5 border border-borde/10 rounded-lg p-3 hover:border-acento/30 transition"
    >
      <div className="relative shrink-0">
        {jugador.foto_url ? (
          <img src={jugador.foto_url} alt={nombreMostrado} className="w-12 h-12 rounded-full object-cover" />
        ) : (
          <div className="w-12 h-12 rounded-full bg-acento/10 border border-acento/20 flex items-center justify-center">
            <span className="font-display text-sm text-acento">{jugador.nombre?.[0]}</span>
          </div>
        )}
        <span className="absolute -bottom-1 -right-1 bg-acento text-fondo font-marcador text-[10px] font-bold rounded-full w-5 h-5 flex items-center justify-center border-2 border-fondo">
          {jugador.dorsal ?? '—'}
        </span>
      </div>
      <div className="min-w-0 flex-1">
        <p className="font-body text-sm font-medium text-texto truncate">{nombreMostrado}</p>
        <div className="flex items-center gap-1.5 flex-wrap">
          <p className="font-body text-[11px] text-borde truncate">{jugador.nacionalidad ?? jugador.posicion}</p>
          {edad !== null && <span className="font-body text-[11px] text-borde/70">· {edad} años</span>}
        </div>
      </div>
    </Link>
  )
}

function TeamMatchesPage() {
  const { idEquipo } = useParams()
  const location = useLocation()
  const infoInicial = location.state
  const [tab, setTab] = useState(() => sessionStorage.getItem(`equipo-tab-${idEquipo}`) ?? 'info')

  function cambiarTab(clave) {
    setTab(clave)
    sessionStorage.setItem(`equipo-tab-${idEquipo}`, clave)
  }

  const { data, isLoading, error } = useQuery({
    queryKey: ['equipo-partidos', idEquipo],
    queryFn: async () => (await client.get(`/api/v1/equipos/${idEquipo}/partidos`)).data.data,
  })

  const nombreEquipo = data?.equipo.nombre_corto ?? infoInicial?.nombre
  const escudoEquipo = data?.equipo.escudo_url ?? infoInicial?.escudo_url
  const colorPrimario = data?.equipo.color_primario ?? '#0E1B2B'
  const colorSecundario = data?.equipo.color_secundario ?? colorPrimario

  if (error) return <p className="font-body text-red-500 p-4">{error.response?.data?.message ?? 'Error al cargar.'}</p>
  if (isLoading || !data) return <div className="max-w-3xl mx-auto px-4 py-8"><SkeletonLista /></div>

  const equipo = data.equipo

  const plantillaPorPosicion = ORDEN_POSICION.map((posicion) => ({
    posicion,
    jugadores: data.plantilla.filter((j) => j.posicion === posicion),
  })).filter((grupo) => grupo.jugadores.length > 0)

  const otrasPosiciones = data.plantilla.filter((j) => !ORDEN_POSICION.includes(j.posicion))

  return (
    <div className="max-w-3xl mx-auto px-4 py-8">
      <Link to="/jornadas/1" className="font-body text-sm text-acento hover:underline mb-4 inline-block">← Volver</Link>

      {/* Cabecera */}
      <div
        className="rounded-lg overflow-hidden mb-6 relative"
        style={{ background: `linear-gradient(135deg, ${colorPrimario}33, transparent 70%)` }}
      >
        <div className="bg-fondo border border-borde/30 rounded-lg p-8 flex items-center gap-6">
          <Escudo url={escudoEquipo} alt={nombreEquipo} tamano="w-28 h-28" />
          <div className="min-w-0">
            <h1 className="font-display text-4xl text-texto truncate leading-tight">{equipo.nombre}</h1>
            {equipo.apodo && <p className="font-body text-base text-borde mt-1">"{equipo.apodo}"</p>}
            <div className="flex flex-wrap gap-x-5 gap-y-1.5 mt-3">
              {equipo.ciudad && <p className="font-body text-sm text-borde">📍 {equipo.ciudad}</p>}
              {equipo.año_fundacion && <p className="font-body text-sm text-borde">🏛️ Fundado en {equipo.año_fundacion}</p>}
              {equipo.siglas && <p className="font-body text-sm text-borde font-semibold">{equipo.siglas}</p>}
            </div>
          </div>
        </div>
        <div className="h-1" style={{ background: `linear-gradient(90deg, ${colorPrimario}, ${colorSecundario})` }} />
      </div>

      {/* Tabs */}
      <div className="flex gap-2 mb-6">
        {TABS.map((t) => (
          <button
            key={t.key}
            onClick={() => cambiarTab(t.key)}
            className={`font-body text-sm px-4 py-2 rounded-full transition ${
              tab === t.key ? 'bg-acento text-fondo font-semibold' : 'text-texto border border-borde/40 hover:bg-borde/10'
            }`}
          >
            {t.label}
          </button>
        ))}
      </div>

      {/* Información */}
      {tab === 'info' && (
        <div className="flex flex-col gap-6">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div className="bg-fondo border border-borde/30 rounded-lg p-5">
              <p className="font-body text-xs uppercase tracking-widest text-borde mb-3">🏟️ Estadio</p>
              {equipo.estadio ? (
                <>
                  <p className="font-display text-base text-texto mb-3">{equipo.estadio.nombre}</p>
                  <BarraCapacidad capacidad={equipo.estadio.capacidad} maxima={equipo.capacidad_maxima_laliga} />
                  <div className="flex gap-4 mt-4 pt-4 border-t border-borde/10">
                    {equipo.estadio.anio_construccion && (
                      <div>
                        <p className="font-marcador text-sm text-texto">{equipo.estadio.anio_construccion}</p>
                        <p className="font-body text-[10px] text-borde">Construcción</p>
                      </div>
                    )}
                    {equipo.estadio.anio_ult_remodelacion && (
                      <div>
                        <p className="font-marcador text-sm text-texto">{equipo.estadio.anio_ult_remodelacion}</p>
                        <p className="font-body text-[10px] text-borde">Última reforma</p>
                      </div>
                    )}
                    {equipo.estadio.tamanio_campo && (
                      <div>
                        <p className="font-marcador text-sm text-texto">{equipo.estadio.tamanio_campo}</p>
                        <p className="font-body text-[10px] text-borde">Terreno de juego</p>
                      </div>
                    )}
                  </div>
                </>
              ) : (
                <p className="font-body text-sm text-borde">Sin datos de estadio.</p>
              )}
            </div>

            <div className="bg-fondo border border-borde/30 rounded-lg p-5">
              <p className="font-body text-xs uppercase tracking-widest text-borde mb-3">👔 Club</p>
              {equipo.entrenador && (
                <div className="flex items-center gap-3 mb-4">
                  {equipo.entrenador.foto_url ? (
                    <img src={equipo.entrenador.foto_url} alt={equipo.entrenador.nombre} className="w-12 h-12 rounded-full object-cover shrink-0" />
                  ) : (
                    <div className="w-12 h-12 rounded-full bg-acento/10 border border-acento/20 flex items-center justify-center shrink-0">
                      <span className="font-display text-sm text-acento">{equipo.entrenador.nombre?.[0]}</span>
                    </div>
                  )}
                  <div className="min-w-0">
                    <p className="font-body text-xs text-borde">Entrenador</p>
                    <p className="font-display text-base text-texto truncate">{equipo.entrenador.nombre}</p>
                    {equipo.entrenador.nacionalidad && <p className="font-body text-xs text-borde">{equipo.entrenador.nacionalidad}</p>}
                  </div>
                </div>
              )}
              {(equipo.num_socios || equipo.num_abonados) && (
                <div className="flex gap-6 pt-3 border-t border-borde/10">
                  {equipo.num_socios && (
                    <div>
                      <p className="font-marcador text-sm text-texto">{equipo.num_socios.toLocaleString('es-ES')}</p>
                      <p className="font-body text-[10px] text-borde">Socios</p>
                    </div>
                  )}
                  {equipo.num_abonados && (
                    <div>
                      <p className="font-marcador text-sm text-texto">{equipo.num_abonados.toLocaleString('es-ES')}</p>
                      <p className="font-body text-[10px] text-borde">Abonados</p>
                    </div>
                  )}
                </div>
              )}
              {!equipo.entrenador && !equipo.num_socios && !equipo.num_abonados && (
                <p className="font-body text-sm text-borde">Sin más datos disponibles.</p>
              )}
            </div>
          </div>

          {(equipo.camiseta_1 || equipo.camiseta_2 || equipo.camiseta_3) && (
            <div className="bg-fondo border border-borde/30 rounded-lg p-5">
              <p className="font-body text-xs uppercase tracking-widest text-borde mb-4">👕 Equipaciones</p>
              <div className="flex flex-wrap items-start gap-8 justify-center">
                {[
                  { anverso: equipo.camiseta_1, reverso: equipo.camiseta_1_reverso, label: 'Local' },
                  { anverso: equipo.camiseta_2, reverso: equipo.camiseta_2_reverso, label: 'Visitante' },
                  { anverso: equipo.camiseta_3, reverso: equipo.camiseta_3_reverso, label: 'Tercera' },
                ].filter((c) => c.anverso).map((c) => (
                  <div key={c.label} className="text-center">
                    <div className="flex items-center gap-3">
                      <img src={c.anverso} alt={`Camiseta ${c.label}, delante`} className="w-32 h-32 object-contain" />
                      {c.reverso && <img src={c.reverso} alt={`Camiseta ${c.label}, detrás`} className="w-32 h-32 object-contain" />}
                    </div>
                    <p className="font-body text-sm text-borde mt-2">{c.label}</p>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>
      )}

      {/* Plantilla */}
      {tab === 'plantilla' && (
        <div>
          {data.plantilla.length === 0 ? (
            <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden">
              <EstadoVacio icono="👥" titulo="Sin plantilla" texto="No hay jugadores activos cargados para este equipo." />
            </div>
          ) : (
            <div className="flex flex-col gap-6">
              <div className="bg-fondo border border-borde/30 rounded-lg p-4 flex items-center justify-around">
                <div className="text-center">
                  <p className="font-marcador text-xl text-acento">{data.plantilla_stats.total}</p>
                  <p className="font-body text-[10px] uppercase tracking-widest text-borde mt-0.5">Jugadores</p>
                </div>
                <div className="w-px h-8 bg-borde/20" />
                <div className="text-center">
                  <p className="font-marcador text-xl text-acento">
                    {data.plantilla_stats.edad_media ?? '—'}
                  </p>
                  <p className="font-body text-[10px] uppercase tracking-widest text-borde mt-0.5">Edad media</p>
                </div>
              </div>

              {plantillaPorPosicion.map((grupo) => (
                <div key={grupo.posicion}>
                  <div className="flex items-center gap-2 mb-3">
                    <p className="font-display text-sm text-texto tracking-wide">{grupo.posicion}s</p>
                    <span className="font-marcador text-xs text-borde bg-borde/10 rounded-full px-2 py-0.5">{grupo.jugadores.length}</span>
                  </div>
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    {grupo.jugadores.map((j) => <TarjetaJugador key={j.id} jugador={j} />)}
                  </div>
                </div>
              ))}

              {otrasPosiciones.length > 0 && (
                <div>
                  <p className="font-display text-sm text-texto tracking-wide mb-3">Otros</p>
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    {otrasPosiciones.map((j) => <TarjetaJugador key={j.id} jugador={j} />)}
                  </div>
                </div>
              )}
            </div>
          )}
        </div>
      )}

      {/* Partidos */}
      {tab === 'partidos' && (
        <div>
          {data.partidos.length === 0 ? (
            <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden">
              <EstadoVacio icono="📅" titulo="Sin partidos" texto="Aún no hay partidos cargados para este equipo." />
            </div>
          ) : (
            (() => {
              const jugados = data.partidos.filter((p) => p.estado === 'Jugado')
              const proximos = data.partidos.filter((p) => p.estado !== 'Jugado')

              function resultadoColor(p) {
                if (p.estado !== 'Jugado') return 'border-l-transparent'
                const golesFavor = p.es_local ? p.goles_casa : p.goles_fuera
                const golesContra = p.es_local ? p.goles_fuera : p.goles_casa
                if (golesFavor > golesContra) return 'border-l-acento'
                if (golesFavor < golesContra) return 'border-l-red-500'
                return 'border-l-borde/40'
              }

              const racha = jugados.reduce((acc, p) => {
                const golesFavor = p.es_local ? p.goles_casa : p.goles_fuera
                const golesContra = p.es_local ? p.goles_fuera : p.goles_casa
                if (golesFavor > golesContra) acc.victorias++
                else if (golesFavor < golesContra) acc.derrotas++
                else acc.empates++
                return acc
              }, { victorias: 0, empates: 0, derrotas: 0 })

              const ultimosCinco = jugados.slice(-5).map((p) => {
                const golesFavor = p.es_local ? p.goles_casa : p.goles_fuera
                const golesContra = p.es_local ? p.goles_fuera : p.goles_casa
                if (golesFavor > golesContra) return 'V'
                if (golesFavor < golesContra) return 'D'
                return 'E'
              })

              const gruposProximos = {}
              proximos.forEach((p) => {
                const clave = p.horario_estimado
                  ? new Date(p.horario_estimado).toLocaleDateString('es-ES', { month: 'long', year: 'numeric' })
                  : 'Sin fecha'
                if (!gruposProximos[clave]) gruposProximos[clave] = []
                gruposProximos[clave].push(p)
              })

              return (
                <div className="flex flex-col gap-6">
                  {jugados.length > 0 && (
                    <div>
                      <div className="flex items-center justify-between mb-3 flex-wrap gap-2">
                        <div className="flex items-center gap-2">
                          <p className="font-display text-sm text-texto tracking-wide">Resultados</p>
                          <span className="font-marcador text-xs text-borde bg-borde/10 rounded-full px-2 py-0.5">{jugados.length}</span>
                        </div>
                        <div className="flex items-center gap-3">
                          <p className="font-marcador text-xs text-borde">
                            <span className="text-acento font-bold">{racha.victorias}V</span>
                            {' · '}
                            <span className="text-borde/70 font-bold">{racha.empates}E</span>
                            {' · '}
                            <span className="text-red-500 font-bold">{racha.derrotas}D</span>
                          </p>
                          <div className="flex gap-1">
                            {ultimosCinco.map((r, i) => (
                              <span
                                key={i}
                                className={`w-2 h-2 rounded-full ${
                                  r === 'V' ? 'bg-acento' : r === 'D' ? 'bg-red-500' : 'bg-borde/40'
                                }`}
                                title={r === 'V' ? 'Victoria' : r === 'D' ? 'Derrota' : 'Empate'}
                              />
                            ))}
                          </div>
                        </div>
                      </div>
                      <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden">
                        {jugados.map((p) => (
                          <div
                            key={p.id}
                            className={`flex items-center gap-3 px-4 py-3 border-b border-l-4 border-borde/10 last:border-b-0 ${resultadoColor(p)}`}
                          >
                            <span className="font-marcador text-[11px] text-borde w-7 shrink-0">J{p.jornada}</span>
                            <span className="text-sm shrink-0" title={p.es_local ? 'En casa' : 'Fuera de casa'}>
                              {p.es_local ? '🏠' : '✈️'}
                            </span>
                            <Escudo url={p.escudo_rival} alt={p.rival} />
                            <p className="font-body text-sm text-texto truncate flex-1 min-w-0">{p.rival}</p>
                            <span className="font-marcador text-base font-bold text-texto shrink-0">{p.goles_casa}-{p.goles_fuera}</span>
                          </div>
                        ))}
                      </div>
                    </div>
                  )}

                  {proximos.length > 0 && (
                    <div>
                      <div className="flex items-center gap-2 mb-3">
                        <p className="font-display text-sm text-texto tracking-wide">Próximos partidos</p>
                        <span className="font-marcador text-xs text-borde bg-borde/10 rounded-full px-2 py-0.5">{proximos.length}</span>
                      </div>
                      {Object.entries(gruposProximos).map(([mes, partidosDelMes], indiceGrupo) => (
                        <div key={mes} className="mb-4 last:mb-0">
                          <p className="font-body text-[11px] uppercase tracking-widest text-borde/70 mb-1.5 px-1">{mes}</p>
                          <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden">
                            {partidosDelMes.map((p, indiceEnGrupo) => {
                              const esElProximo = indiceGrupo === 0 && indiceEnGrupo === 0
                              return (
                                <div
                                  key={p.id}
                                  className={`flex items-center gap-3 px-4 py-3 border-b border-l-4 last:border-b-0 ${
                                    esElProximo ? 'border-l-premio bg-premio/5' : p.estado === 'Aplazado' ? 'border-l-red-400/60 border-borde/10' : 'border-l-transparent border-borde/10'
                                  }`}
                                >
                                  <span className="font-marcador text-[11px] text-borde w-7 shrink-0">J{p.jornada}</span>
                                  <span className="text-sm shrink-0" title={p.es_local ? 'En casa' : 'Fuera de casa'}>
                                    {p.es_local ? '🏠' : '✈️'}
                                  </span>
                                  <Escudo url={p.escudo_rival} alt={p.rival} />
                                  <div className="flex-1 min-w-0">
                                    <p className="font-body text-sm text-texto truncate">{p.rival}</p>
                                    {esElProximo && <p className="font-body text-[10px] text-premio font-semibold">PRÓXIMO PARTIDO</p>}
                                  </div>
                                  {p.estado === 'Aplazado' ? (
                                    <span className="font-body text-xs text-red-400 shrink-0">Aplazado</span>
                                  ) : (
                                    <span className="font-marcador text-xs text-borde shrink-0">{p.horario_estimado?.slice(5, 16).replace('T', ' ')}</span>
                                  )}
                                </div>
                              )
                            })}
                          </div>
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              )
            })()
          )}
        </div>
      )}
    </div>
  )
}

export default TeamMatchesPage
