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

const FONDO_ESTADO = {
  Jugado: 'bg-acento/5',
  Aplazado: 'bg-red-400/5',
  Programado: '',
}

const TABS = [
  { key: 'info', label: 'Información' },
  { key: 'plantilla', label: 'Plantilla' },
  { key: 'partidos', label: 'Partidos' },
]

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

function TeamMatchesPage() {
  const { idEquipo } = useParams()
  const location = useLocation()
  const infoInicial = location.state
  const [tab, setTab] = useState('info')

  const { data, isLoading, error } = useQuery({
    queryKey: ['equipo-partidos', idEquipo],
    queryFn: async () => (await client.get(`/api/v1/equipos/${idEquipo}/partidos`)).data.data,
  })

  const nombreEquipo = data?.equipo.nombre_corto ?? infoInicial?.nombre
  const escudoEquipo = data?.equipo.escudo_url ?? infoInicial?.escudo_url
  const colorPrimario = data?.equipo.color_primario ?? '#0E1B2B'

  if (error) return <p className="font-body text-red-500 p-4">{error.response?.data?.message ?? 'Error al cargar.'}</p>
  if (isLoading || !data) return <div className="max-w-3xl mx-auto px-4 py-8"><SkeletonLista /></div>

  const equipo = data.equipo

  return (
    <div className="max-w-3xl mx-auto px-4 py-8">
      <Link to="/jornadas/1" className="font-body text-sm text-acento hover:underline mb-4 inline-block">← Volver</Link>

      {/* Cabecera */}
      <div
        className="rounded-lg overflow-hidden mb-6 relative"
        style={{ background: `linear-gradient(135deg, ${colorPrimario}22, transparent)` }}
      >
        <div className="bg-fondo border border-borde/30 rounded-lg p-6 flex items-center gap-5">
          <Escudo url={escudoEquipo} alt={nombreEquipo} tamano="w-20 h-20" />
          <div className="min-w-0">
            <h1 className="font-display text-2xl text-texto truncate">{equipo.nombre}</h1>
            {equipo.apodo && <p className="font-body text-sm text-borde">"{equipo.apodo}"</p>}
            <div className="flex flex-wrap gap-x-4 gap-y-1 mt-2">
              {equipo.ciudad && <p className="font-body text-xs text-borde">📍 {equipo.ciudad}</p>}
              {equipo.año_fundacion && <p className="font-body text-xs text-borde">🏛️ Fundado en {equipo.año_fundacion}</p>}
              {equipo.siglas && <p className="font-body text-xs text-borde">{equipo.siglas}</p>}
            </div>
          </div>
        </div>
      </div>

      {/* Tabs */}
      <div className="flex gap-2 mb-6">
        {TABS.map((t) => (
          <button
            key={t.key}
            onClick={() => setTab(t.key)}
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
                <div className="mb-4">
                  <p className="font-body text-xs text-borde">Entrenador</p>
                  <p className="font-display text-base text-texto">{equipo.entrenador.nombre}</p>
                  {equipo.entrenador.nacionalidad && <p className="font-body text-xs text-borde">{equipo.entrenador.nacionalidad}</p>}
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
              <div className="flex flex-wrap items-start gap-6 justify-center">
                {[
                  { anverso: equipo.camiseta_1, reverso: equipo.camiseta_1_reverso, label: 'Local' },
                  { anverso: equipo.camiseta_2, reverso: equipo.camiseta_2_reverso, label: 'Visitante' },
                  { anverso: equipo.camiseta_3, reverso: equipo.camiseta_3_reverso, label: 'Tercera' },
                ].filter((c) => c.anverso).map((c) => (
                  <div key={c.label} className="text-center">
                    <div className="flex items-center gap-2">
                      <img src={c.anverso} alt={`Camiseta ${c.label}, delante`} className="w-20 h-20 object-contain" />
                      {c.reverso && <img src={c.reverso} alt={`Camiseta ${c.label}, detrás`} className="w-20 h-20 object-contain" />}
                    </div>
                    <p className="font-body text-xs text-borde mt-1">{c.label}</p>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>
      )}

      {/* Plantilla */}
      {tab === 'plantilla' && (
        <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden">
          {data.plantilla.length === 0 ? (
            <EstadoVacio icono="👥" titulo="Sin plantilla" texto="No hay jugadores activos cargados para este equipo." />
          ) : (
            data.plantilla.map((j) => (
              <div key={j.id} className="flex items-center gap-3 px-4 py-3 border-b border-borde/10 last:border-0 odd:bg-borde/5">
                <span className="font-marcador text-sm text-borde w-7 text-center shrink-0">{j.dorsal ?? '—'}</span>
                {j.foto_url ? (
                  <img src={j.foto_url} alt={j.nombre} className="w-9 h-9 rounded-full object-cover shrink-0" />
                ) : (
                  <span className="w-9 h-9 rounded-full bg-borde/15 flex items-center justify-center text-xs shrink-0">
                    {j.nombre?.[0]}
                  </span>
                )}
                <div className="min-w-0 flex-1">
                  <p className="font-body text-sm text-texto truncate">{j.nombre_camiseta || `${j.nombre} ${j.apellidos ?? ''}`}</p>
                  <p className="font-body text-[10px] text-borde">{j.posicion}</p>
                </div>
              </div>
            ))
          )}
        </div>
      )}

      {/* Partidos */}
      {tab === 'partidos' && (
        <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden">
          {data.partidos.length === 0 ? (
            <EstadoVacio icono="📅" titulo="Sin partidos" texto="Aún no hay partidos cargados para este equipo." />
          ) : (
            data.partidos.map((p) => (
              <div
                key={p.id}
                className={`flex items-center justify-between gap-3 px-4 py-4 border-b border-borde/10 last:border-0 flex-wrap sm:flex-nowrap odd:bg-borde/5 ${FONDO_ESTADO[p.estado] ?? ''}`}
              >
                <span className="font-body text-sm font-semibold text-borde w-10 shrink-0">J{p.jornada}</span>
                <div className="flex items-center gap-2 flex-1 min-w-0">
                  <span className="text-lg shrink-0" title={p.es_local ? 'En casa' : 'Fuera de casa'}>
                    {p.es_local ? '🏠' : '✈️'}
                  </span>
                  <Escudo url={p.escudo_rival} alt={p.rival} />
                  <p className="font-body text-base text-texto truncate">{p.rival}</p>
                </div>
                {p.estado === 'Jugado' ? (
                  <span className="font-marcador text-base font-bold text-texto shrink-0">{p.goles_casa}-{p.goles_fuera}</span>
                ) : (
                  <span className="font-marcador text-sm text-texto shrink-0">{p.horario_estimado?.slice(5, 16).replace('T', ' ')}</span>
                )}
              </div>
            ))
          )}
        </div>
      )}
    </div>
  )
}

export default TeamMatchesPage