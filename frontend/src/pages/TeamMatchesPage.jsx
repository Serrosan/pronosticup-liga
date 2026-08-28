import { useParams, useLocation, Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import client from '../api/client'
import EstadoVacio from '../components/EstadoVacio'

function Escudo({ url, alt, tamano = 'w-8 h-8' }) {
  if (!url) return <span className={`${tamano} rounded-full bg-borde/15 flex items-center justify-center text-sm shrink-0`}>⚽</span>
  return <img src={url} alt={alt} className={`${tamano} object-contain shrink-0`} />
}

const FONDO_ESTADO = {
  Jugado: 'bg-acento/5',
  Aplazado: 'bg-red-400/5',
  Programado: '',
}

function TeamMatchesPage() {
  const { idEquipo } = useParams()
  const location = useLocation()
  const infoInicial = location.state

  const { data, isLoading, error } = useQuery({
    queryKey: ['equipo-partidos', idEquipo],
    queryFn: async () => (await client.get(`/api/v1/equipos/${idEquipo}/partidos`)).data.data,
  })

  const nombreEquipo = data?.equipo.nombre ?? infoInicial?.nombre
  const escudoEquipo = data?.equipo.escudo_url ?? infoInicial?.escudo_url

  if (error) return <p className="font-body text-red-500 p-4">{error.response?.data?.message ?? 'Error al cargar.'}</p>

  return (
    <div className="max-w-2xl mx-auto px-4 py-8">
      <Link to="/jornadas/1" className="font-body text-sm text-acento hover:underline mb-4 inline-block">← Volver</Link>

      <div className="flex items-center gap-3 mb-6">
        <Escudo url={escudoEquipo} alt={nombreEquipo} tamano="w-12 h-12" />
        <h1 className="font-display text-2xl text-texto">{nombreEquipo ?? 'Cargando...'}</h1>
      </div>

      {(data?.equipo.camiseta_1 || data?.equipo.camiseta_2 || data?.equipo.camiseta_3) && (
        <div className="flex flex-wrap items-start gap-6 mb-8 justify-center">
          {[
            { anverso: data.equipo.camiseta_1, reverso: data.equipo.camiseta_1_reverso, label: 'Local' },
            { anverso: data.equipo.camiseta_2, reverso: data.equipo.camiseta_2_reverso, label: 'Visitante' },
            { anverso: data.equipo.camiseta_3, reverso: data.equipo.camiseta_3_reverso, label: 'Tercera' },
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
      )}

      {isLoading && <p className="font-body text-texto p-4">Cargando partidos...</p>}

      {data && (
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